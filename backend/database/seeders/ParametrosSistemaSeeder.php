<?php

namespace Database\Seeders;

use App\Models\ParametroSistema;
use Illuminate\Database\Seeder;

class ParametrosSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            [
                'clave' => 'solicitud_reserva_expiracion_minutos',
                'valor' => '15',
                'descripcion' => 'Minutos de vigencia de una solicitud antes de expirar si el Core no confirma el pago.',
            ],
            [
                'clave' => 'polling_intervalo_segundos',
                'valor' => '20',
                'descripcion' => 'Intervalo de consulta activa contra el Core, si se implementa polling además del webhook.',
            ],
            [
                'clave' => 'recaudaciones_timeout_segundos',
                'valor' => '8',
                'descripcion' => 'Timeout en segundos para las llamadas al Core de Recaudaciones.',
            ],
            [
                'clave' => 'max_franjas_por_solicitud',
                'valor' => '6',
                'descripcion' => 'Tope de franjas en una sola solicitud pública (M1 anti-hoarding).',
            ],
            [
                'clave' => 'max_pendientes_por_ip',
                'valor' => '3',
                'descripcion' => 'Tope de solicitudes pendientes activas simultáneas por IP (M2 anti-hoarding).',
            ],
            [
                'clave' => 'umbral_expiraciones_cooldown',
                'valor' => '3',
                'descripcion' => 'Cantidad de expiraciones en 1h que disparan cooldown por IP (M3).',
            ],
            [
                'clave' => 'cooldown_expiracion_minutos',
                'valor' => '30',
                'descripcion' => 'Minutos que dura el bloqueo de IP tras alcanzar el umbral de expiraciones.',
            ],
        ];

        foreach ($parametros as $parametro) {
            ParametroSistema::updateOrCreate(
                ['clave' => $parametro['clave']],
                $parametro,
            );
        }
    }
}
