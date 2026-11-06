<?php

namespace Tests\Feature;

use App\Models\Asociacion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsociacionUnicidadCITest extends TestCase
{
    use RefreshDatabase;

    private function crear(string $ci, bool $activo, string $sufijo): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-' . $sufijo,
            'nombre' => 'Asoc ' . $sufijo,
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-' . $sufijo,
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => '2030-12-31',
            'responsable_nombre' => 'Resp ' . $sufijo,
            'responsable_ci' => $ci,
            'responsable_telefono' => '70000000',
            'activo' => $activo,
        ]);
    }

    public function test_dos_asociaciones_activas_con_el_mismo_ci_rechinan(): void
    {
        $this->crear('70000001', true, 'A');

        // Savepoint anidado: si el INSERT viola el unique, PostgreSQL aborta
        // solo el savepoint, no la transaccion exterior de RefreshDatabase.
        // Sin esto, el count() de abajo caeria en 25P02.
        $excepcion = null;
        try {
            DB::transaction(function () {
                $this->crear('70000001', true, 'B');
            });
        } catch (QueryException $e) {
            $excepcion = $e;
        }

        $this->assertNotNull(
            $excepcion,
            'El indice unico parcial debio rechazar la segunda activa con el mismo CI.'
        );
        $this->assertStringContainsString('23505', $excepcion->getMessage());

        // La transaccion exterior sigue viva (el savepoint revirtio solo).
        $this->assertSame(
            1,
            Asociacion::where('responsable_ci', '70000001')->count()
        );
    }

    public function test_activa_mas_inactiva_con_el_mismo_ci_si_pasan(): void
    {
        // El indice es parcial (WHERE activo = true): una inactiva no lo toca.
        // Esto es lo que permite al GAD tener historicas inhabilitadas del
        // mismo presidente sin chocar con la activa vigente.
        $this->crear('70000002', false, 'Vieja');
        $this->crear('70000002', true, 'Nueva');

        $this->assertSame(2, Asociacion::where('responsable_ci', '70000002')->count());
        $this->assertSame(
            1,
            Asociacion::where('responsable_ci', '70000002')->where('activo', true)->count()
        );
    }
}
