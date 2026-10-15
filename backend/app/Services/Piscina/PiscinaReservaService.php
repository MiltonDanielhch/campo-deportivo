<?php

namespace App\Services\Piscina;

use App\DTOs\PiscinaReservaDTO;
use App\Enums\EstadoPiscinaReserva;
use App\Enums\ModalidadReservaCampo;
use App\Exceptions\FranjaNoDisponibleException;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Jobs\ExpirarPiscinaReservaJob;
use App\Jobs\PollingPiscinaReservaJob;
use App\Jobs\ReintentarPiscinaReservaJob;
use App\Models\CampoDeportivo;
use App\Models\ParametroSistema;
use App\Models\PiscinaReserva;
use App\Models\PiscinaReservaDetalle;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de creación de reservas de piscina.
 *
 * Flujo:
 * 1. Validar campo, turno, items
 * 2. Validar disponibilidad (cupo)
 * 3. Resolver tarifas desde espejo SIREB
 * 4. Calcular monto
 * 5. Transacción: crear reserva + detalles + reservar cupo
 * 6. Crear liquidación SIREB
 * 7. Dispatch jobs (expiración, polling, reintentos)
 */
class PiscinaReservaService
{
    private const VENTANA_DIAS = 60;
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const TIMEZONE_BOLIVIA = 'America/La_Paz';

    public function __construct(
        private PiscinaTarifaResolver $tarifaResolver,
        private RecaudacionesApiClientInterface $recaudaciones,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(PiscinaReservaDTO $datos): array
    {
        // Validar items
        $datos->validarItems();

        // Obtener turno para validar y obtener campo
        $turno = PiscinaTurno::with('campo')->find($datos->turnoId);
        if (!$turno) {
            throw new \InvalidArgumentException('Turno no encontrado');
        }

        $campo = $turno->campo;
        if (!$campo) {
            throw new \InvalidArgumentException('Campo no encontrado');
        }

        // Validar modalidad
        if ($campo->modalidad_reserva?->value !== ModalidadReservaCampo::TurnoPorPerfil->value) {
            throw new \InvalidArgumentException('Este campo no acepta reservas de piscina');
        }

        // Validar turno activo y futuro
        if ($turno->estado !== 'activo') {
            throw new \InvalidArgumentException('El turno no está activo');
        }

        $fechaTurno = Carbon::parse($turno->fecha);
        $hoy = Carbon::now(self::TIMEZONE_BOLIVIA)->startOfDay();

        if ($fechaTurno->lt($hoy)) {
            throw new \InvalidArgumentException('No se pueden reservar turnos pasados');
        }

        if ($fechaTurno->gt($hoy->copy()->addDays(self::VENTANA_DIAS))) {
            throw new \InvalidArgumentException('La fecha excede el máximo de anticipación de ' . self::VENTANA_DIAS . ' días');
        }

        // Validar items y calcular monto
        $itemsValidados = $this->validarItemsYCalcularMonto($turno, $datos->items);
        $montoTotal = collect($itemsValidados)->sum('subtotal');

        // Obtener minutos de expiración
        $expiracionMinutos = $this->minutosExpiracion();

        try {
            // Transacción principal
            $reserva = DB::transaction(function () use ($datos, $turno, $itemsValidados, $montoTotal, $expiracionMinutos) {
                // Crear reserva principal
                $reserva = PiscinaReserva::create([
                    'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
                    'campo_id' => $turno->campo_id,
                    'estado' => EstadoPiscinaReserva::Pendiente,
                    'monto_total' => $montoTotal,
                    'nombre_pagador' => $datos->nombrePagador,
                    'telefono_pagador' => $datos->telefonoPagador,
                    'ci_nit_pagador' => $datos->ciNitPagador,
                    'ip_cobrador' => $datos->ipCobrador,
                    'expira_en' => now()->addMinutes($expiracionMinutos),
                ]);

                // Crear detalles y reservar cupo
                foreach ($itemsValidados as $item) {
                    // Reservar cupo con lock para evitar overbooking
                    $perfilTurno = PiscinaTurnoPerfil::where('turno_id', $turno->id)
                        ->where('perfil', $item['perfil'])
                        ->lockForUpdate()
                        ->first();

                    if (!$perfilTurno) {
                        throw new \InvalidArgumentException('Perfil no disponible en este turno');
                    }

                    // Validar disponibilidad
                    if ($item['cantidad'] > $perfilTurno->disponible) {
                        throw new FranjaNoDisponibleException(
                            "No hay cupo suficiente para {$item['perfil']}. Disponible: {$perfilTurno->disponible}, solicitado: {$item['cantidad']}"
                        );
                    }

                    // Incrementar reservado
                    $perfilTurno->increment('reservado', $item['cantidad']);

                    // Crear detalle
                    PiscinaReservaDetalle::create([
                        'reserva_id' => $reserva->id,
                        'turno_id' => $turno->id,
                        'perfil' => $item['perfil'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                        'tarifa_sireb_id' => $item['tarifa_sireb_id'],
                        'servicio_sireb_id' => $item['servicio_sireb_id'],
                        'etiqueta_sireb' => $item['etiqueta_sireb'],
                        'hora_inicio' => $turno->hora_inicio,
                        'hora_fin' => $turno->hora_fin,
                        'fecha' => $turno->fecha,
                    ]);
                }

                // Auditoría
                $this->auditoria->registrar(
                    'piscina_reservas',
                    $reserva->id,
                    'crear_reserva',
                    null,
                    null,
                    [
                        'codigo_seguimiento' => $reserva->codigo_seguimiento,
                        'monto_total' => $montoTotal,
                        'turno_id' => $turno->id,
                        'items' => $itemsValidados,
                    ]
                );

                return $reserva;
            });

            // Después del commit, crear liquidación en SIREB
            $this->crearLiquidacionEnSireb($reserva);

            // Dispatch jobs
            ExpirarPiscinaReservaJob::dispatch($reserva->id)->delay($reserva->expira_en);
            PollingPiscinaReservaJob::dispatch($reserva->id);

            return [
                'reserva_id' => $reserva->id,
                'codigo_seguimiento' => $reserva->codigo_seguimiento,
                'monto_total' => (float) $reserva->monto_total,
                'expira_en' => $reserva->expira_en->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::channel('sireb')->error('Error al crear reserva de piscina', [
                'error' => $e->getMessage(),
                'datos' => $datos,
            ]);
            throw $e;
        }
    }

    /**
     * Valida items, resuelve tarifas y calcula montos.
     */
    private function validarItemsYCalcularMonto(PiscinaTurno $turno, array $items): array
    {
        $itemsValidados = [];

        foreach ($items as $item) {
            $perfil = $item['perfil'];
            $cantidad = $item['cantidad'];

            // Resolver tarifa
            $tarifa = $this->tarifaResolver->resolver(
                $turno->campo_id,
                $perfil,
                $turno->hora_inicio,
            );

            if (!$tarifa) {
                throw new \InvalidArgumentException("No hay tarifa vigente para el perfil {$perfil}");
            }

            $itemsValidados[] = [
                'perfil' => $perfil,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $tarifa->precio_por_hora,
                'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                'servicio_sireb_id' => $tarifa->servicio_sireb_id,
                'etiqueta_sireb' => $tarifa->etiqueta_sireb,
                'subtotal' => (float) $tarifa->precio_por_hora * $cantidad,
            ];
        }

        return $itemsValidados;
    }

    /**
     * Crea liquidación en SIREB después de crear la reserva local.
     * Si falla, despacha job de reintento.
     */
    private function crearLiquidacionEnSireb(PiscinaReserva $reserva): void
    {
        try {
            // Buscar o registrar cliente en SIREB
            $clienteSireb = $this->recaudaciones->buscarCliente($reserva->ci_nit_pagador);
            if (!$clienteSireb) {
                $clienteSireb = $this->recaudaciones->registrarCliente([
                    'ci_nit' => $reserva->ci_nit_pagador,
                    'nombre_completo' => $reserva->nombre_pagador,
                    'telefono' => $reserva->telefono_pagador,
                ]);
            }

            // Construir items (uno por perfil con tarifa_id resuelto)
            $items = [];
            foreach ($reserva->detalles as $detalle) {
                $items[] = [
                    'tarifa_id' => $detalle->tarifa_sireb_id,
                    'cantidad' => $detalle->cantidad,
                ];
            }

            // Crear liquidación con idempotencia
            $idempotencyKey = "sedede:piscina:{$reserva->id}";
            $liquidacion = $this->recaudaciones->crearLiquidacion(
                items: $items,
                clienteId: $clienteSireb['id'],
                idempotencyKey: $idempotencyKey,
                referenciaExterna: $reserva->codigo_seguimiento,
            );

            // Persistir referencias (SIREB manda el monto autoritativo)
            $reserva->update([
                'referencia_recaudaciones' => $liquidacion['codigo_publico'],
                'liquidacion_id' => $liquidacion['id'],
                'monto_total' => $liquidacion['monto'],
                'datos_cobro_pendiente' => [
                    'codigo_publico' => $liquidacion['codigo_publico'],
                    'qr_string' => 'SIREB:' . $liquidacion['codigo_publico'],
                    'qr_image_base64' => null,
                    'monto' => $liquidacion['monto'],
                    'fecha_vencimiento' => $liquidacion['fecha_vencimiento'],
                    'items' => $liquidacion['items'],
                ],
            ]);

            $this->auditoria->registrar(
                'piscina_reservas',
                $reserva->id,
                'liquidacion_creada_sireb',
                null,
                ['estado' => 'pendiente'],
                [
                    'liquidacion_id' => $liquidacion['id'],
                    'codigo_publico' => $liquidacion['codigo_publico'],
                    'monto' => $liquidacion['monto'],
                ],
            );
        } catch (\Exception $e) {
            Log::channel('sireb')->error('Error al crear liquidación SIREB en primera intento', [
                'reserva_id' => $reserva->id,
                'error' => $e->getMessage(),
            ]);

            // Despachar job de reintento si falla
            ReintentarPiscinaReservaJob::dispatch($reserva->id);
        }
    }

    /**
     * Genera código de seguimiento único.
     */
    private function generarCodigoSeguimiento(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < 6; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'PIS-' . now()->format('Ymd') . '-' . $aleatorio;
        } while (PiscinaReserva::where('codigo_seguimiento', $codigo)->exists());

        return $codigo;
    }

    /**
     * Obtiene minutos de expiración desde parámetros.
     */
    private function minutosExpiracion(): int
    {
        return (int) ParametroSistema::where('clave', 'vigencia_minutos_solicitud')->value('valor') ?: 20;
    }
}
