<?php

namespace App\Services\Sireb;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;

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
    ) {
    }

    /**
     * Crea la liquidación en SIREB y persiste las referencias en el modelo.
     *
     * @param  SolicitudReserva|PiscinaReserva  $reserva
     * @param  array<int, array{tarifa_id: string|null, cantidad: int}>  $items
     * @param  string  $idempotencyPrefix  Ej. 'sedede:reserva' o 'sedede:piscina'
     * @param  string  $tablaAuditoria  Ej. 'solicitudes_reserva' o 'piscina_reservas'
     * @param  string  $accionAuditoria  Default 'liquidacion_creada_sireb'
     * @param  array<string, mixed>  $antesAuditoria  Default ['estado' => 'pendiente']
     *
     * @throws \Throwable Si SIREB no responde o la persistencia falla. El caller
     *         decide la política (reintento público / rechazo ventanilla).
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

        // 3. Persistir referencias.
        // SIREB es la fuente de verdad del monto: sobrescribimos el estimado
        // local con el autoritativo devuelto por el gateway. El shape de
        // datos_cobro_pendiente es SIEMPRE el mismo, inline o reintento, para
        // que el frontend (QR + código copiable) no tenga que distinguir.
        $reserva->update([
            'referencia_recaudaciones' => $liquidacion['codigo_publico'],
            'liquidacion_id' => $liquidacion['id'],
            'monto_total' => $liquidacion['monto'],
            'datos_cobro_pendiente' => [
                'codigo_publico' => $liquidacion['codigo_publico'],
                'qr_string' => 'SIREB:'.$liquidacion['codigo_publico'],
                'qr_image_base64' => null,
                'monto' => $liquidacion['monto'],
                'fecha_vencimiento' => $liquidacion['fecha_vencimiento'],
                'items' => $liquidacion['items'],
            ],
        ]);

        // 4. Auditoría.
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
    }
}