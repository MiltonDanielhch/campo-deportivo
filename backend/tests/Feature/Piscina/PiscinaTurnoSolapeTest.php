<?php

namespace Tests\Feature\Piscina;

use App\Enums\ModalidadReservaCampo;
use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\TipoCampo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Prueba la garantía de solape a nivel BD (migración no_solape_turno_piscina),
 * NO el gate PHP. Por eso inserta directo vía modelo, saltándose el controller:
 * si la restricción BD existiera o se rompiera, estos tests lo detectan.
 *
 * Precedente: SolicitudReservaCobroTest ya prueba una violación de exclusión
 * (SQLSTATE 23P01) bajo RefreshDatabase sin cascada 25P02; acá seguimos el mismo
 * patrón (el insert fallido aborta la tx del test, pero no corremos más queries
 * después del catch, y el rollback de teardown limpia).
 */
class PiscinaTurnoSolapeTest extends TestCase
{
    use RefreshDatabase;

    private function campo(): CampoDeportivo
    {
        $tipo = TipoCampo::query()->firstOrCreate(
            ['nombre' => 'Piscina'],
            ['descripcion' => 'Tipo piscina', 'estado' => 'activo'],
        );

        return CampoDeportivo::create([
            'tipo_campo_id'     => $tipo->id,
            'codigo'            => 'PISC-SOLAPE-'.Str::upper(Str::random(4)),
            'nombre'            => 'Piscina Solape',
            'direccion'         => 'Dirección de prueba',
            'latitud'           => -14.83333333,
            'longitud'          => -64.90000000,
            'modalidad_reserva' => ModalidadReservaCampo::TurnoPorPerfil,
            'estado'            => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);
    }

    public function test_bd_rechaza_dos_turnos_activos_solapados(): void
    {
        $campo = $this->campo();

        PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-11-15',
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'cupo_total'  => 10,
            'estado'      => 'activo',
        ]);

        // [11:00,13:00) solapa con [10:00,12:00). La BD debe rechazarlo aunque el
        // PHP gate no intervenga (inserción directa).
        try {
            PiscinaTurno::create([
                'campo_id'    => $campo->id,
                'fecha'       => '2026-11-15',
                'hora_inicio' => '11:00:00',
                'hora_fin'    => '13:00:00',
                'cupo_total'  => 10,
                'estado'      => 'activo',
            ]);
            $this->fail('La BD debió rechazar el solape con no_solape_turno_piscina');
        } catch (QueryException $e) {
            $this->assertStringContainsString('no_solape_turno_piscina', $e->getMessage());
        }
    }

    public function test_bd_permite_turnos_contiguos_sin_solape(): void
    {
        $campo = $this->campo();

        PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-11-15',
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'cupo_total'  => 10,
            'estado'      => 'activo',
        ]);

        // [12:00,14:00) es contiguo, NO solapa con el bound half-open '[)'. Debe pasar.
        $turno2 = PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-11-15',
            'hora_inicio' => '12:00:00',
            'hora_fin'    => '14:00:00',
            'cupo_total'  => 10,
            'estado'      => 'activo',
        ]);

        $this->assertNotNull($turno2->id);
    }

    public function test_bd_permite_solape_si_un_turno_esta_inactivo(): void
    {
        $campo = $this->campo();

        PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-11-15',
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'cupo_total'  => 10,
            'estado'      => 'inactivo',
        ]);

        // El inactivo queda fuera de la restricción parcial WHERE estado='activo'.
        $turno2 = PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-11-15',
            'hora_inicio' => '11:00:00',
            'hora_fin'    => '13:00:00',
            'cupo_total'  => 10,
            'estado'      => 'activo',
        ]);

        $this->assertNotNull($turno2->id);
    }

    public function test_bd_permite_mismo_horario_en_campos_distintos(): void
    {
        $campoA = $this->campo();
        $campoB = $this->campo();

        foreach ([$campoA, $campoB] as $c) {
            PiscinaTurno::create([
                'campo_id'    => $c->id,
                'fecha'       => '2026-11-15',
                'hora_inicio' => '10:00:00',
                'hora_fin'    => '12:00:00',
                'cupo_total'  => 10,
                'estado'      => 'activo',
            ]);
        }

        // Mismo horario, distinto campo: sin conflicto (campo_id WITH =).
        $this->assertSame(2, PiscinaTurno::count());
    }
}
