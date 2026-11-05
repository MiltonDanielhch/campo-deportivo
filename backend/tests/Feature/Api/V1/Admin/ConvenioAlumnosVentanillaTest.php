<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\SolicitudDetalleAlumno;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvenioAlumnosVentanillaTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;
    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $rolAdmin = Rol::create(['nombre' => 'admin_reservas', 'descripcion' => 'x', 'permisos' => ['*']]);
        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin', 'ci' => '1', 'usuario' => 'admin_v',
            'password_hash' => bcrypt('x'), 'rol_id' => $rolAdmin->id, 'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id, 'codigo' => 'CD-A', 'nombre' => 'Cancha A',
            'direccion' => 'Trinidad', 'latitud' => -14.84, 'longitud' => -64.90,
            'estado' => 'activo',
            'servicio_sireb_id' => '01a0f3e8-33bc-7130-9614-85a23ea26caf',
            'servicio_sireb_codigo' => 'SEDEDE-CS1',
        ]);
        $this->campo->horariosAtencion()->create(['dia_semana' => now()->addDay()->dayOfWeekIso, 'hora_apertura' => '08:00:00', 'hora_cierre' => '22:00:00']);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => '01a0f3e8-33bc-7130-9614-85a23ea26caf',
            'tarifa_sireb_id' => '01a0f3f8-29e8-71f8-acca-a3c23716c69f',
            'etiqueta_sireb' => 'Diurno', 'liquidable' => true, 'tarifario_estado' => 'vigente',
            'categorias_sireb' => [], 'perfil' => null, 'clasificacion' => 'diurna',
            'tipo_tarifa' => 'diurna', 'precio_por_hora' => 50.00,
            'vigente_desde' => now(), 'vigente_hasta' => null, 'sincronizado_en' => now(),
        ]);
    }

    /** asociacion_id NO es fillable en AsociacionAlumno: se setea por propiedad (igual el controller). */
    private function asociacionConAlumnos(string $ciResponsable, int $n = 2): array
    {
        $asociacion = Asociacion::create([
            'nit' => 'NIT-' . uniqid(),
            'nombre' => 'Asoc ' . uniqid(),
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-' . uniqid(),
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => '2030-12-31',
            'responsable_nombre' => 'Resp',
            'responsable_ci' => $ciResponsable,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);

        $alumnos = [];
        for ($i = 0; $i < $n; $i++) {
            $al = new AsociacionAlumno();
            $al->asociacion_id = $asociacion->id;
            $al->ci = (string) (44440000 + $i);
            $al->nombre_completo = 'Alumno ' . $i;
            $al->save();
            $alumnos[] = $al;
        }

        return [$asociacion, $alumnos];
    }

    private function payload(array $alumnoIds, string $ciPagador = '5551111'): array
    {
        return [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00:00',
                'hora_fin' => '11:00:00',
                'alumnos_ids' => $alumnoIds,
            ]],
            'nombre_pagador' => 'Presidente',
            'ci_nit_pagador' => $ciPagador,
            'telefono_pagador' => '70000001',
        ];
    }

    public function test_ventanilla_asignar_alumnos_escribe_la_puente(): void
    {
        [, $alumnos] = $this->asociacionConAlumnos('5551111', 2);
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload(
            array_map(fn ($a) => $a->id, $alumnos),
        ))->assertStatus(201);

        $detalle = SolicitudReserva::first()->detalles()->first();
        $puente = array_map('strval', SolicitudDetalleAlumno::where('detalle_id', $detalle->id)->pluck('alumno_id')->all());
        $esperado = array_map(fn ($a) => (string) $a->id, $alumnos);
        sort($puente);
        sort($esperado);

        $this->assertSame($esperado, $puente);
    }

    public function test_ventanilla_alumno_de_otra_asociacion_devuelve_422(): void
    {
        $this->asociacionConAlumnos('5551111', 1);          // la del pagador
        [, $ajenos] = $this->asociacionConAlumnos('5552222', 1); // otra asociacion
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload([$ajenos[0]->id]))
            ->assertStatus(422);

        $this->assertDatabaseCount('solicitudes_reserva', 0);
        $this->assertDatabaseCount('solicitud_detalle_alumno', 0);
    }

    public function test_ventanilla_sin_asociacion_no_puede_asignar_alumnos(): void
    {
        [, $alumnos] = $this->asociacionConAlumnos('5551111', 1);
        Sanctum::actingAs($this->admin);

        // pagador CI que NO es responsable de ninguna asociacion.
        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload([$alumnos[0]->id], '1239999'))
            ->assertStatus(422);
    }

    public function test_ventanilla_alumno_duplicado_en_payload_no_duplica_puente(): void
    {
        [, $alumnos] = $this->asociacionConAlumnos('5551111', 1);
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $id = $alumnos[0]->id;
        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload([$id, $id]))
            ->assertStatus(201);

        // array_unique en el service: 1 fila, no 2 (ni viola el unique(detalle,alumno)).
        $this->assertDatabaseCount('solicitud_detalle_alumno', 1);
    }

    public function test_ventanilla_reserva_sin_alumnos_sigue_creando_sin_puente(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload([]))
            ->assertStatus(201);

        $this->assertDatabaseCount('solicitud_detalle_alumno', 0);
    }
}
