<?php

namespace Database\Seeders;

use App\Enums\ModalidadReservaCampo;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\HorarioAtencion;
use App\Models\TipoCampo;
use Illuminate\Database\Seeder;

/**
 * P8.4A: extiende el modelo de variantes arancelarias (probado en CV-001)
 * a los dos campos físicos del arancel que SIREB modela como
 * "varios servicios sobre un mismo espacio": Palacio de los Deportes y
 * Estadio Gran Mamoré.
 *
 * NO toca CV-001 (ya pilotado). NO crea campos de servicio único
 * (Cancha Sintética / Frontón): esos se promueven por UI, no por seeder.
 *
 * Patrón idéntico al piloto: campo padre SIN servicio_sireb_id (el vínculo
 * vive en las variantes), variantes con servicio_sireb_codigo y
 * servicio_sireb_id=null (lo completa sireb:sincronizar-tarifas).
 * Así el sync viejo (whereNotNull) no los duplica y
 * desactivarCamposDuplicadosDeVariantes() inactiva cualquier SIR-* residual.
 */
class ArancelVarianteCatalogoCompletoSeeder extends Seeder
{
    public function run(): void
    {
        $tipo = TipoCampo::firstOrCreate(
            ['nombre' => 'Fútbol'],
            ['estado' => 'activo']
        );

        $campos = [
            [
                'codigo' => 'PAL-001',
                'nombre' => 'Palacio de los Deportes',
                'horarias' => [
                    ['PAL-MUNI', 'Asociaciones Municipales', 'asociacion', 'SEDEDE-PAL-MUNI', 10],
                    ['PAL-GENERAL', 'Escenario General', 'general', 'SEDEDE-PAL-GENERAL', 20],
                    ['PAL-EVENTO-DEP', 'Evento Deportivo', 'deportivo', 'SEDEDE-PAL-EVENTO-DEP', 30],
                ],
                'servicios' => [
                    ['LIMPIEZA', 'Limpieza', 'SEDEDE-PAL-EVENTO-NO-LIMPIEZA', 40],
                    ['ENERGIA_MONO', 'Energía monofásica', 'SEDEDE-PAL-EVENTO-NO-ENER-MONO', 50],
                    ['ENERGIA_TRI', 'Energía trifásica', 'SEDEDE-PAL-EVENTO-NO-ENER-TRI', 60],
                    ['ESCENARIO', 'Escenario', 'SEDEDE-PAL-EVENTO-NO-ESCENARIO', 70],
                ],
            ],
            [
                'codigo' => 'EGM-001',
                'nombre' => 'Estadio Gran Mamoré',
                'horarias' => [
                    ['EGM-ENTREN', 'Entrenamiento Campo de Juego', 'entrenamiento', 'SEDEDE-EGM-ENTREN', 10],
                    ['EGM-ENTREN-CAM', 'Campo + 2 Camerinos', 'entrenamiento', 'SEDEDE-EGM-ENTREN-CAM', 20],
                    ['EGM-ENTREN-CAM-DUCHA', 'Campo + 2 Camerinos + Ducha', 'entrenamiento', 'SEDEDE-EGM-ENTREN-CAM-DUCHA', 30],
                ],
                'servicios' => [
                    ['LIMPIEZA_DEP', 'Limpieza evento deportivo', 'SEDEDE-EGM-EVEN-DEP-LIMPIEZA', 40],
                    ['ENERGIA_DEP', 'Energía evento deportivo', 'SEDEDE-EGM-EVEN-DEP-ENERGIA', 50],
                    ['ESCENARIO_DEP', 'Escenario evento deportivo', 'SEDEDE-EGM-EVEN-DEP-ESCENARIO', 60],
                    ['LIMPIEZA_NO_DEP', 'Limpieza evento no deportivo', 'SEDEDE-EGM-EVENTO-NO-LIMPIEZA', 70],
                ],
            ],
        ];

        foreach ($campos as $c) {
            $campo = CampoDeportivo::updateOrCreate(
                ['codigo' => $c['codigo']],
                [
                    'tipo_campo_id' => $tipo->id,
                    'nombre' => $c['nombre'],
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
                    ['campo_id' => $campo->id, 'dia_semana' => $dia],
                    ['hora_apertura' => '08:00:00', 'hora_cierre' => '22:00:00']
                );
            }

            foreach ($c['horarias'] as [$codigo, $nombre, $tipoUso, $sireb, $orden]) {
                ArancelVariante::updateOrCreate(
                    ['campo_id' => $campo->id, 'codigo' => $codigo],
                    [
                        'nombre' => $nombre,
                        'tipo_uso' => $tipoUso,
                        'servicio_sireb_codigo' => $sireb,
                        'servicio_sireb_id' => null, // lo completa el sync
                        'unidad_medida' => 'hora',
                        'visible_publico' => false,
                        'activo' => true,
                        'orden' => $orden,
                    ]
                );
            }

            foreach ($c['servicios'] as [$codigo, $nombre, $sireb, $orden]) {
                ArancelVariante::updateOrCreate(
                    ['campo_id' => $campo->id, 'codigo' => $codigo],
                    [
                        'nombre' => $nombre,
                        'tipo_uso' => 'servicio_adicional',
                        'servicio_sireb_codigo' => $sireb,
                        'servicio_sireb_id' => null, // lo completa el sync
                        'unidad_medida' => 'servicio',
                        'visible_publico' => false,
                        'activo' => true,
                        'orden' => $orden,
                    ]
                );
            }

            $this->command?->info(
                "P8.4A {$campo->codigo} ({$campo->nombre}): "
                .count($c['horarias'])." horarias + ".count($c['servicios'])." servicios."
            );
        }
    }
}
