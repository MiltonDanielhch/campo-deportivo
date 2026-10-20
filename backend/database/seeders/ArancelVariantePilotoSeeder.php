<?php

namespace Database\Seeders;

use App\Enums\ModalidadReservaCampo;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TipoCampo;
use Illuminate\Database\Seeder;
use App\Models\HorarioAtencion;

/**
 * Piloto de variantes arancelarias: Cancha Vieja.
 *
 * Caso canónico del arancel: UN espacio físico, DOS servicios SIREB según
 * quién lo usa (Asociación Departamental vs Municipal). El modelo viejo
 * "1 campo = 1 servicio" no lo expresa; este seeder lo materializa como
 * campo físico padre + N variantes, cada una apuntando a su servicio SIREB.
 *
 * El campo padre queda SIN servicio_sireb_id a propósito: el vínculo vive
 * en las variantes. Así el sync viejo (que filtra whereNotNull) no lo toca,
 * y el parche sincronizarVariantes() es el único que espeja sus tarifas.
 */
class ArancelVariantePilotoSeeder extends Seeder
{
    public function run(): void
    {
        $tipo = TipoCampo::firstOrCreate(
            ['nombre' => 'Fútbol'],
            ['estado' => 'activo']
        );

        $campo = CampoDeportivo::updateOrCreate(
            ['codigo' => 'CV-001'],
            [
                'tipo_campo_id' => $tipo->id,
                'nombre' => 'Coliseo Cancha Vieja',
                'direccion' => 'Trinidad',
                'latitud' => -14.84,
                'longitud' => -64.90,
                'estado' => 'activo',
                'modalidad_reserva' => ModalidadReservaCampo::FranjaHoraria,
                'servicio_sireb_id' => null,
                'servicio_sireb_codigo' => null,
            ]
        );

        foreach (range(1, 7) as $dia) {
            HorarioAtencion::updateOrCreate(
                [
                    'campo_id' => $campo->id,
                    'dia_semana' => $dia,
                ],
                [
                    'hora_apertura' => '08:00:00',
                    'hora_cierre' => '20:00:00',
                ]
            );
        }

        $variantes = [
            [
                'codigo' => 'DPTO',
                'nombre' => 'Asociaciones Departamentales',
                'tipo_uso' => 'asociacion',
                'servicio_sireb_codigo' => 'SEDEDE-CV-DPTO',
                'unidad_medida' => 'hora',
                'orden' => 10,
            ],
            [
                'codigo' => 'MUNI',
                'nombre' => 'Asociaciones Municipales',
                'tipo_uso' => 'asociacion',
                'servicio_sireb_codigo' => 'SEDEDE-CV-MUNI',
                'unidad_medida' => 'hora',
                'orden' => 20,
            ],
            [
                'codigo' => 'LIMPIEZA',
                'nombre' => 'Limpieza post-evento',
                'tipo_uso' => 'servicio_adicional',
                'servicio_sireb_codigo' => 'SEDEDE-CV-EVENTO-DEP-LIMPIEZA',
                'unidad_medida' => 'servicio',
                'orden' => 30,
            ],
            [
                'codigo' => 'ENERGIA_MONO',
                'nombre' => 'Energía monofásica',
                'tipo_uso' => 'servicio_adicional',
                'servicio_sireb_codigo' => 'SEDEDE-CV-EVENTO-NO-ENER-MONO',
                'unidad_medida' => 'servicio',
                'orden' => 40,
            ],
            [
                'codigo' => 'ENERGIA_TRI',
                'nombre' => 'Energía trifásica',
                'tipo_uso' => 'servicio_adicional',
                'servicio_sireb_codigo' => 'SEDEDE-CV-EVENTO-NO-ENER-TRI',
                'unidad_medida' => 'servicio',
                'orden' => 50,
            ],
        ];

        foreach ($variantes as $v) {
            ArancelVariante::updateOrCreate(
                ['campo_id' => $campo->id, 'codigo' => $v['codigo']],
                [
                    'nombre' => $v['nombre'],
                    'tipo_uso' => $v['tipo_uso'],
                    'servicio_sireb_codigo' => $v['servicio_sireb_codigo'],
                    'servicio_sireb_id' => null, // lo completa el sync desde SIREB
                    'unidad_medida' => $v['unidad_medida'],
                    'visible_publico' => false,   // solo ventanilla al principio
                    'activo' => true,
                    'orden' => $v['orden'],
                ]
            );
        }

        $this->command?->info("Piloto Cancha Vieja ({$campo->codigo}) con 2 variantes horarias y 3 servicios adicionales.");
    }
}
