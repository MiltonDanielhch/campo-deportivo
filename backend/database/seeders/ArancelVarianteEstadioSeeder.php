<?php

namespace Database\Seeders;

use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

class ArancelVarianteEstadioSeeder extends Seeder
{
    public function run(): void
    {
        $campo = CampoDeportivo::where('codigo', 'CD-002')->first();

        if (! $campo) {
            $this->command?->warn('Campo CD-002 no encontrado. Seed de variantes de Estadio omitido.');

            return;
        }

        $variantes = [
            [
                'codigo' => 'ENTREN_CAMPO',
                'nombre' => 'Entrenamiento - Campo de juego',
                'descripcion' => 'Alquiler de campo de juego para entrenamiento.',
                'tipo_uso' => 'entrenamiento',
                'servicio_sireb_codigo' => 'SEDEDE-EGM-ENTREN',
                'unidad_medida' => 'hora',
                'orden' => 10,
            ],
            [
                'codigo' => 'ENTREN_CAMPO_CAMERINOS',
                'nombre' => 'Entrenamiento - Campo + 2 camerinos',
                'descripcion' => 'Alquiler de campo de juego más dos camerinos.',
                'tipo_uso' => 'entrenamiento',
                'servicio_sireb_codigo' => 'SEDEDE-EGM-ENTREN-CAM',
                'unidad_medida' => 'hora',
                'orden' => 20,
            ],
            [
                'codigo' => 'ENTREN_CAMPO_CAMERINOS_DUCHA',
                'nombre' => 'Entrenamiento - Campo + 2 camerinos + ducha',
                'descripcion' => 'Alquiler de campo de juego, dos camerinos y ducha.',
                'tipo_uso' => 'entrenamiento',
                'servicio_sireb_codigo' => 'SEDEDE-EGM-ENTREN-CAM-DUCHA',
                'unidad_medida' => 'hora',
                'orden' => 30,
            ],
            [
                'codigo' => 'EVENTO_DEPORTIVO_ESCENARIO',
                'nombre' => 'Evento deportivo - Escenario',
                'descripcion' => 'Uso del estadio como escenario para evento deportivo.',
                'tipo_uso' => 'evento_deportivo',
                'servicio_sireb_codigo' => 'SEDEDE-EGM-EVEN-DEP-ESCENARIO',
                'unidad_medida' => 'hora',
                'orden' => 40,
            ],
        ];

        foreach ($variantes as $variante) {
            ArancelVariante::updateOrCreate(
                [
                    'campo_id' => $campo->id,
                    'codigo' => $variante['codigo'],
                ],
                [
                    'nombre' => $variante['nombre'],
                    'descripcion' => $variante['descripcion'],
                    'tipo_uso' => $variante['tipo_uso'],
                    'servicio_sireb_codigo' => $variante['servicio_sireb_codigo'],
                    'servicio_sireb_id' => null,
                    'unidad_medida' => $variante['unidad_medida'],
                    'visible_publico' => false,
                    'activo' => true,
                    'orden' => $variante['orden'],
                ]
            );
        }
    }
}