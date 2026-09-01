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
        ];

        foreach ($parametros as $parametro) {
            ParametroSistema::updateOrCreate(
                ['clave' => $parametro['clave']],
                $parametro,
            );
        }
    }
}
