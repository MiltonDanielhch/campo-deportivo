<?php

namespace Database\Seeders;

use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TipoCampo;
use Illuminate\Database\Seeder;
use RuntimeException;

class ConvenioVarianteSeeder extends Seeder
{
    /**
     * Campos locales donde se colgarán las variantes de convenio.
     *
     * Si CV-001 en tu base en realidad es "Coliseo Cerrado" y no "Cancha Vieja",
     * invertí CV-001 / CC-001 en VARIANTES.
     */
    private const CAMPOS = [
        'CV-001' => [
            'nombre' => 'Cancha Vieja',
            'crear' => false,
        ],
        'PAL-001' => [
            'nombre' => 'Palacio de los Deportes',
            'crear' => false,
        ],
        'CC-001' => [
            'nombre' => 'Coliseo Cerrado Trinidad',
            'crear' => true,
        ],
    ];

    /**
     * Variantes de convenio.
     *
     * visible_publico=false => solo ventanilla.
     * unidad_medida=hora => reservable por franja horaria.
     * tipo_asociacion => departamental | municipal.
     */
    private const VARIANTES = [
        [
            'servicio' => 'SEDEDE-CV-DPTO',
            'campo' => 'CV-001',
            'tipo' => 'departamental',
            'nombre' => 'Convenio Departamental',
            'orden' => 10,
        ],
        [
            'servicio' => 'SEDEDE-CV-MUNI',
            'campo' => 'CV-001',
            'tipo' => 'municipal',
            'nombre' => 'Convenio Municipal',
            'orden' => 20,
        ],
        [
            'servicio' => 'SEDEDE-CC-DPTO',
            'campo' => 'CC-001',
            'tipo' => 'departamental',
            'nombre' => 'Convenio Departamental',
            'orden' => 10,
        ],
        [
            'servicio' => 'SEDEDE-CC-MUNI',
            'campo' => 'CC-001',
            'tipo' => 'municipal',
            'nombre' => 'Convenio Municipal',
            'orden' => 20,
        ],
        [
            'servicio' => 'SEDEDE-PAL-MUNI',
            'campo' => 'PAL-001',
            'tipo' => 'municipal',
            'nombre' => 'Convenio Municipal',
            'orden' => 10,
        ],
    ];

    public function run(): void
    {
        foreach (self::VARIANTES as $varianteConfig) {
            $campo = $this->resolverCampo($varianteConfig['campo']);

            if (! $campo) {
                $this->command?->warn(
                    "Campo {$varianteConfig['campo']} no existe y no se puede crear automáticamente. Se omite {$varianteConfig['servicio']}."
                );

                continue;
            }

            $atributos = [
                'campo_id' => $campo->id,
                'codigo' => $varianteConfig['servicio'],
                'servicio_sireb_codigo' => $varianteConfig['servicio'],
                'nombre' => $varianteConfig['nombre'],
                'unidad_medida' => 'hora',
                'visible_publico' => false,
                'activo' => true,
                'orden' => $varianteConfig['orden'],
                'tipo_asociacion' => $varianteConfig['tipo'],
            ];

            $variante = ArancelVariante::query()
                ->where('campo_id', $campo->id)
                ->where('servicio_sireb_codigo', $varianteConfig['servicio'])
                ->first();

            if ($variante) {
                $variante->forceFill($atributos)->save();
            } else {
                (new ArancelVariante())->forceFill($atributos)->save();
            }
        }
    }

    private function resolverCampo(string $codigo): ?CampoDeportivo
    {
        $campo = CampoDeportivo::query()
            ->where('codigo', $codigo)
            ->first();

        if ($campo) {
            return $campo;
        }

        $config = self::CAMPOS[$codigo] ?? null;

        if (! $config || ! ($config['crear'] ?? false)) {
            return null;
        }

        $tipoCampoId = TipoCampo::query()
            ->orderBy('nombre')
            ->value('id');

        if (! $tipoCampoId) {
            throw new RuntimeException(
                "No hay TipoCampo disponible para crear el campo {$codigo}. Creá un tipo de campo primero."
            );
        }

        $campo = new CampoDeportivo();

        $campo->forceFill([
            'tipo_campo_id' => $tipoCampoId,
            'codigo' => $codigo,
            'nombre' => $config['nombre'],
            'direccion' => 'Pendiente de completar por el operador',
            'latitud' => -14.8333,
            'longitud' => -64.9000,
            'estado' => 'activo',
            'modalidad_reserva' => 'sin_reserva_online',
        ])->save();

        return $campo;
    }
}
