<?php

namespace App\Console\Commands;

use App\Enums\EstadoSolicitudReserva;
use App\Models\SolicitudReserva;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpirarSolicitudesVencidas extends Command
{
    protected $signature = 'solicitudes:expirar-vencidas';
    protected $description = 'Expira solicitudes pendientes con expira_en < now()';

    public function handle(): int
    {
        $vencidas = SolicitudReserva::where('estado', EstadoSolicitudReserva::Pendiente)
            ->where('expira_en', '<', Carbon::now())
            ->get();

        $expiradas = 0;

        foreach ($vencidas as $solicitud) {
            DB::transaction(function () use ($solicitud) {
                $solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

                AuditoriaService::registrar(
                    'solicitudes_reserva',
                    $solicitud->id,
                    'expirar_por_barrido',
                    null,
                    ['estado' => 'pendiente'],
                    ['estado' => 'expirada']
                );
            });

            $expiradas++;
        }

        $this->info("Expiradas {$expiradas} solicitudes vencidas");

        return Command::SUCCESS;
    }
}
