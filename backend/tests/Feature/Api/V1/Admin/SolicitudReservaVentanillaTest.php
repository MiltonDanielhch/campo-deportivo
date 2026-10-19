<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitudReservaVentanillaTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;

    private Funcionario $control;

    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $rolAdmin = Rol::create(['nombre' => 'admin_reservas', 'descripcion' => 'x', 'permisos' => ['*']]);
        $rolControl = Rol::create(['nombre' => 'funcionario_control', 'descripcion' => 'x', 'permisos' => []]);

        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin', 'ci' => '1', 'usuario' => 'admin_v',
            'password_hash' => bcrypt('x'), 'rol_id' => $rolAdmin->id, 'estado' => 'activo',
        ]);
        $this->control = Funcionario::create([
            'nombre_completo' => 'Control', 'ci' => '2', 'usuario' => 'control_v',
            'password_hash' => bcrypt('x'), 'rol_id' => $rolControl->id, 'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id, 'codigo' => 'CD-V', 'nombre' => 'Cancha V',
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

    private function payload(): array
    {
        $fecha = now()->addDay()->toDateString();

        return [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => $fecha,
                'hora_inicio' => '10:00:00',
                'hora_fin' => '11:00:00',
            ]],
            'nombre_pagador' => 'Vecino Ventanilla',
            'ci_nit_pagador' => '9876543',
            'telefono_pagador' => '70000001',
        ];
    }

    public function test_admin_crea_reserva_y_recibe_codigo_de_pago(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.codigo_seguimiento', fn ($v) => is_string($v) && str_starts_with($v, 'RES-'))
            ->assertJsonPath('data.referencia_recaudaciones', fn ($v) => is_string($v) && str_starts_with($v, 'SIM-'));

        $this->assertDatabaseHas('solicitudes_reserva', ['estado' => 'pendiente']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'solicitud_creada_en_ventanilla']);
    }

    public function test_funcionario_control_no_puede_crear_desde_panel(): void
    {
        Sanctum::actingAs($this->control);

        $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload())
            ->assertStatus(403);
    }
}
