<?php

namespace App\Services\Sireb;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * Helper central de liquidación SIREB.
 *
 * Responsabilidad ÚNICA: hablar con el Core de Recaudaciones y persistir el
 * resultado en la reserva/solicitud. Concentra en un solo lugar la lógica que
 * antes estaba duplicada en 4 sitios (service canchas, job canchas, service
 * piscina, job piscina) y que ya causó divergencias de shape:
 *   - qr_string ausente en el reintento
 *   - qr_image_base64 ausente
 *   - monto_total no sobrescrito con el autoritativo de SIREB
 *
 * Este helper NO decide:
 *   - reintentos (eso lo hace el caller con Reintentar*Job)
 *   - expiración / polling (jobs aparte)
 *   - rechazo ni liberación de franja/cupo (política de ventanilla, en el caller)
 *   - construcción de items (dominio de cada canal: canchas resuelve tarifa por
 *     campo/hora; piscina usa tarifa_sireb_id ya guardado en el detalle)
 *
 * Por eso ATRAPA NADA: cualquier excepción del client SIREB o de la BD se
 * propaga al caller, que es quien sabe si estamos en canal público (despachar
 * reintento) o en ventanilla (rechazar inline + liberar recurso + 422).
 */
class SirebLiquidacionService
{
    public function __construct(
        private RecaudacionesApiClientInterface $recaudaciones,
        private AuditoriaService $auditoria,
    ) {}

    /**
     * Crea la liquidación en SIREB y persiste las referencias en el modelo.
     *
     * @param  array<int, array{tarifa_id: string|null, cantidad: int}>  $items
     * @param  string  $idempotencyPrefix  Ej. 'sedede:reserva' o 'sedede:piscina'
     * @param  string  $tablaAuditoria  Ej. 'solicitudes_reserva' o 'piscina_reservas'
     * @param  string  $accionAuditoria  Default 'liquidacion_creada_sireb'
     * @param  array<string, mixed>  $antesAuditoria  Default ['estado' => 'pendiente']
     *
     * @throws \Throwable Si SIREB no responde o la persistencia falla. El caller
     *                    decide la política (reintento público / rechazo ventanilla).
     */
    public function crearYPersistir(
        SolicitudReserva|PiscinaReserva $reserva,
        array $items,
        string $idempotencyPrefix,
        string $tablaAuditoria,
        string $accionAuditoria = 'liquidacion_creada_sireb',
        array $antesAuditoria = ['estado' => 'pendiente'],
    ): void {
        // 1. Buscar o registrar cliente en SIREB.
        $clienteSireb = $this->recaudaciones->buscarCliente($reserva->ci_nit_pagador);

        if (! $clienteSireb) {
            $clienteSireb = $this->recaudaciones->registrarCliente([
                'ci_nit' => $reserva->ci_nit_pagador,
                'nombre_completo' => $reserva->nombre_pagador,
                'telefono' => $reserva->telefono_pagador,
            ]);
        }

        // 2. Crear liquidación con idempotencia.
        // La clave es estable por reserva: si el reintento corre dos veces por
        // la misma reserva, SIREB devuelve la liquidación existente en vez de
        // crear una nueva (no doble-cobra).
        $idempotencyKey = "{$idempotencyPrefix}:{$reserva->id}";

        $liquidacion = $this->recaudaciones->crearLiquidacion(
            items: $items,
            clienteId: $clienteSireb['id'],
            idempotencyKey: $idempotencyKey,
            referenciaExterna: $reserva->codigo_seguimiento,
        );

        // [FIX dinero #1] Validar el contrato de SIREB ANTES de pisar el
        // estimado local. Si la liquidación viene malformada (sin id, sin
        // codigo_publico, o con monto <= 0 / no numérico), NO sobrescribimos
        // monto_total ni armamos datos_cobro_pendiente: lanzamos y el caller
        // decide la política (público → ReintentarSolicitudJob; ventanilla →
        // rechazo inline + 422). Sin este guard, un tarifario mal cargado o un
        // bug del gateway que devuelva monto 0 hacía que el ciudadano viera un
        // QR de Bs 0, pagara 0 y la reserva naciera con monto_pagado = tarifa
        // local (Bs 50): cobro cero + reporte inflado. El reintento re-usa la
        // misma idempotency key, así que no doble-cobra.
        $montoAutoritativo = $liquidacion['monto'] ?? null;
        $codigoPublico = $liquidacion['codigo_publico'] ?? null;
        $liquidacionId = $liquidacion['id'] ?? null;

        if (blank($liquidacionId) || blank($codigoPublico)
            || ! is_numeric($montoAutoritativo)
            || (float) $montoAutoritativo <= 0) {
            throw new \RuntimeException(
                'SIREB devolvió una liquidación con contrato inválido '
                . '(id=' . var_export($liquidacionId, true)
                . ', codigo_publico=' . var_export($codigoPublico, true)
                . ', monto=' . var_export($montoAutoritativo, true)
                . ") para {$reserva->codigo_seguimiento}"
            );
        }

        $montoAutoritativo = (float) $montoAutoritativo;

        // 3+4. Persistir referencias + auditoría ATÓMICAMENTE. [25P02-fix]
        // SIREB es la fuente de verdad del monto: sobrescribimos el estimado
        // local con el autoritativo devuelto por el gateway (ya validado > 0).
        // El shape de datos_cobro_pendiente es SIEMPRE el mismo, inline o
        // reintento, para que el frontend (QR + código copiable) no tenga que
        // distinguir.
        //
        // OJO: el HTTP al Core (buscarCliente/registrarCliente/crearLiquidacion
        // + la validación de contrato) va ANTES y FUERA de esta transacción,
        // respetando el invariante del README ("recién DESPUÉS de que la tx
        // tiene éxito se llama a SIREB"). Meter el HTTP dentro sería el error
        // clásico que esta deuda nombra: sostiene la tx abierta a lo largo de
        // un round-trip de red (segundos, con locks) y, si el HTTP ya creó la
        // liquidación en el Core pero el write de abajo falla, el rollback
        // local deja una liquidación HUÉRFANA en el Core SIN referencia local
        // — peor que el estado actual. Por eso la atomicidad cubre SOLO los dos
        // writes locales: si la auditoría falla, el update se revierte y la
        // solicitud queda sin liquidacion_id, así el reintento re-persiste
        // limpio (la idempotency key 'sedede:reserva:{id}' hace que el Core
        // devuelva la liquidación existente, no doble-cobra). Si algún caller
        // ya envolviera esto en su propia DB::transaction (piscina), Laravel
        // anida como savepoint: el rollback solo deshace hasta el savepoint,
        // nunca la tx externa, así que no hay 25P02 en cascada ni se rompe al
        // caller.
        DB::transaction(function () use (
            $reserva,
            $codigoPublico,
            $liquidacionId,
            $montoAutoritativo,
            $liquidacion,
            $tablaAuditoria,
            $accionAuditoria,
            $antesAuditoria,
        ) {
            $reserva->update([
                'referencia_recaudaciones' => $codigoPublico,
                'liquidacion_id' => $liquidacionId,
                'monto_total' => $montoAutoritativo,
                'datos_cobro_pendiente' => [
                    'codigo_publico' => $codigoPublico,
                    'qr_string' => 'SIREB:' . $codigoPublico,
                    'qr_image_base64' => null,
                    'monto' => $montoAutoritativo,
                    'fecha_vencimiento' => $liquidacion['fecha_vencimiento'] ?? null,
                    'items' => $liquidacion['items'] ?? [],
                ],
            ]);

            $this->auditoria->registrar(
                $tablaAuditoria,
                $reserva->id,
                $accionAuditoria,
                null,
                $antesAuditoria,
                [
                    'liquidacion_id' => $liquidacion['id'],
                    'codigo_publico' => $liquidacion['codigo_publico'],
                    'monto' => $liquidacion['monto'],
                ],
            );
        });
    }
}
