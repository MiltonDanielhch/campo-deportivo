<?php

namespace App\Services\Piscina;

use App\Enums\EstadoPiscinaReserva;
use App\Models\PiscinaReserva;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de confirmación de cobro para piscina.
 *
 * Similar a ConfirmacionCobroService pero para el módulo piscina.
 */
class PiscinaConfirmacionCobroService
{
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function confirmar(PiscinaReserva $reserva, ?float $montoConfirmado = null): void
    {
        // Idempotencia: si ya está confirmada, no hay nada más que hacer
        if ($reserva->estado === EstadoPiscinaReserva::Confirmada) {
            return;
        }

        // Regla de confirmación tardía: si ya expiró/canceló/rechazó, NO revivir
        if ($reserva->estado !== EstadoPiscinaReserva::Pendiente) {
            $this->registrarConfirmacionTardia($reserva, $montoConfirmado);
            return;
        }

        // Detectar discrepancia de monto (no bloquea, solo audita)
        if ($montoConfirmado !== null && abs($montoConfirmado - (float) $reserva->monto_total) > 0.01) {
            AuditoriaService::registrar(
                'piscina_reservas',
                $reserva->id,
                'discrepancia_monto_confirmado',
                null,
                ['monto_total' => $reserva->monto_total],
                ['monto_confirmado' => $montoConfirmado]
            );
        }

        try {
            DB::transaction(function () use ($reserva, $montoConfirmado) {
                $reserva->update([
                    'estado' => EstadoPiscinaReserva::Confirmada,
                    'monto_confirmado' => $montoConfirmado,
                    'confirmado_en' => now(),
                ]);

                // NOTA: Para piscina no creamos tabla de reservas confirmadas
                // como en canchas, porque la reserva de piscina ya tiene los detalles.
                // El código de reserva se puede generar aquí si se necesita.

                AuditoriaService::registrar(
                    'piscina_reservas',
                    $reserva->id,
                    'confirmar_pago',
                    null,
                    ['estado' => 'pendiente'],
                    ['estado' => 'confirmada', 'monto_confirmado' => $montoConfirmado],
                );
            });
        } catch (QueryException $e) {
            // 23505 = unique_violation: ya se procesó (idempotencia por restricción de BD)
            if ($e->getCode() === '23505') {
                return;
            }
            throw $e;
        }
    }

    /**
     * Registra una confirmación que llegó después de que la reserva ya
     * había expirado/cancelado/rechazado. No revive la reserva, solo
     * deja el rastro para que el administrador gestione por fuera.
     */
    private function registrarConfirmacionTardia(
        PiscinaReserva $reserva,
        ?float $montoConfirmado
    ): void {
        AuditoriaService::registrar(
            'piscina_reservas',
            $reserva->id,
            'confirmacion_tardia_no_conciliada',
            null,
            ['estado' => $reserva->estado->value],
            ['monto_confirmado' => $montoConfirmado]
        );
    }
}
