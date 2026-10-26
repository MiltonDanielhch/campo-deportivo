<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegularizacionReservaTest extends TestCase
{
    use RefreshDatabase;

    // IDs FIJOS que el catálogo del simulador SIREB reconoce (mismos que usa
    // SolicitudReservaVentanillaTest, que da 201). Un Str::uuid() random NO está
    // en el catálogo → el simulador devuelve monto 0.00 → contrato inválido (500).
    private const SERVICIO_SIREB = '01a0f3e8-33bc-7130-9614-85a23ea26caf';
    private const TARIFA_DIURNA_SIREB = '01a0f3f8-29e8-71f8-acca-a3c23716c69f';
    // Nocturna: nunca se cobra en estos tests (09:00 y 00:00 son diurnas), solo
    // existe para que la grilla clasifique. Id fijo igualmente, por consistencia.
    private const TARIFA_NOCTURNA_SIREB = '01a0f3f8-29e8-71f8-acca-a3c23716c69e';

    private TipoCampo $tipo;
    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        // [FIX] espejo del test de ventanilla: sin 'exito' el simulador devuelve 0.00.
        config(['services.recaudaciones.simulado_modo' => 'exito']);
        // [FIX] sin fake, PollingSolicitudJob corre en sync y puede confirmar la
        // solicitud → la aserción 'estado = pendiente' fallaría.
        Queue::fake();

        $this->tipo = TipoCampo::create(['nombre' => 'Fútbol Reg', 'estado' => 'activo']);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $this->tipo->id,
            'codigo' => 'REG-' . Str::upper(Str::random(6)),
            'nombre' => 'Cancha Regularización',
            'direccion' => 'Dir',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
            // [FIX] servicio base espejado, igual que ventanilla.
            'servicio_sireb_id' => self::SERVICIO_SIREB,
            'servicio_sireb_codigo' => 'SEDEDE-CS1',
        ]);
    }

    public function test_regulariza_franja_de_ayer_con_motivo(): void
    {
        $admin = $this->crearAdmin();
        $this->configurarCampo('08:00', '20:00', (int) Carbon::yesterday()->dayOfWeekIso);

        $ayer = Carbon::yesterday()->toDateString();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva/regularizar', [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => $ayer,
                'hora_inicio' => '09:00:00',
                'hora_fin' => '10:00:00',
            ]],
            'nombre_pagador' => 'juan uso',
            'ci_nit_pagador' => '12345678',
            'motivo' => 'Caída del sistema, el ciudadano usó y no pudo pagar.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.codigo_seguimiento', fn ($v) => str_starts_with((string) $v, 'RES-'));
        // [FIX] con Queue::fake() la solicitud queda pendiente (no la confirma el polling).
        $response->assertJsonPath('data.referencia_recaudaciones', fn ($v) => is_string($v) && str_starts_with($v, 'SIM-'));

        $solicitud = SolicitudReserva::where('codigo_seguimiento', $response->json('data.codigo_seguimiento'))->first();
        $this->assertEquals(EstadoSolicitudReserva::Pendiente, $solicitud->estado);

        $aud = Auditoria::where('tabla', 'solicitudes_reserva')
            ->where('registro_id', $solicitud->id)
            ->where('accion', 'regularizacion_uso_post_caida')
            ->first();
        $this->assertNotNull($aud, 'La regularización debe quedar auditada con el motivo.');
        $this->assertStringContainsString('Caída del sistema', $aud->datos_nuevos['motivo']);
    }

    public function test_regulariza_franja_de_hoy_ya_pasada(): void
    {
        $admin = $this->crearAdmin();
        // Apertura 00:00 → el bloque 00:00-01:00 de HOY siempre está 'pasada' (o 'libre'
        // si corre antes de la 1am; regularización tolera ambos). 00:00 < 18:00 → diurna.
        $this->configurarCampo('00:00', '23:00', (int) Carbon::now('America/La_Paz')->dayOfWeekIso);

        $hoy = Carbon::now('America/La_Paz')->toDateString();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva/regularizar', [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => $hoy,
                'hora_inicio' => '00:00:00',
                'hora_fin' => '01:00:00',
            ]],
            'nombre_pagador' => 'maria uso',
            'ci_nit_pagador' => '87654321',
            'motivo' => 'SIREB sin respuesta durante el uso de esta mañana.',
        ]);

        $response->assertStatus(201);
    }

    public function test_no_regulariza_sobre_franja_ocupada(): void
    {
        $admin = $this->crearAdmin();
        $ayer = Carbon::yesterday();
        $this->configurarCampo('08:00', '20:00', (int) $ayer->dayOfWeekIso);

        // Alguien más ya tiene esta franja confirmada ayer.
        $this->crearSolicitudConfirmada($ayer->toDateString(), '09:00:00', '10:00:00');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva/regularizar', [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => $ayer->toDateString(),
                'hora_inicio' => '09:00:00',
                'hora_fin' => '10:00:00',
            ]],
            'nombre_pagador' => 'pedro doble',
            'ci_nit_pagador' => '11111111',
            'motivo' => 'Intento de regularizar sobre franja ya tomada.',
        ]);

        // 'ocupada' NUNCA se tolera → 409 (la integridad de disponibilidad no se relaja).
        // Este test revienta ANTES de la liquidación, por eso pasaba incluso sin el fix.
        $response->assertStatus(409);
    }

    public function test_regularizar_requiere_motivo(): void
    {
        $admin = $this->crearAdmin();
        $this->configurarCampo('08:00', '20:00', (int) Carbon::yesterday()->dayOfWeekIso);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/solicitudes-reserva/regularizar', [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => Carbon::yesterday()->toDateString(),
                'hora_inicio' => '09:00:00',
                'hora_fin' => '10:00:00',
            ]],
            'nombre_pagador' => 'sin motivo',
            'ci_nit_pagador' => '22222222',
        ])->assertStatus(422)->assertJsonValidationErrors('motivo');
    }

    public function test_funcionario_control_no_puede_regularizar(): void
    {
        $rol = Rol::query()->firstOrCreate(['nombre' => 'funcionario_control'], ['descripcion' => 'r', 'permisos' => []]);
        $control = Funcionario::create([
            'nombre_completo' => 'Control Reg',
            'ci' => '999999',
            'usuario' => 'control-reg-' . Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($control);

        $this->postJson('/api/v1/admin/solicitudes-reserva/regularizar', [
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => Carbon::yesterday()->toDateString(),
                'hora_inicio' => '09:00:00',
                'hora_fin' => '10:00:00',
            ]],
            'nombre_pagador' => 'x',
            'ci_nit_pagador' => '1',
            'motivo' => 'motivo suficiente para pasar validacion',
        ])->assertForbidden();
    }

    // ── Helpers ─

    private function crearAdmin(): Funcionario
    {
        $rol = Rol::query()->firstOrCreate(['nombre' => 'admin_reservas'], ['descripcion' => 'r', 'permisos' => ['*']]);

        return Funcionario::create([
            'nombre_completo' => 'Admin Reg',
            'ci' => '888888',
            'usuario' => 'admin-reg-' . Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);
    }

    private function configurarCampo(string $apertura, string $cierre, int $diaSemana): void
    {
        HorarioAtencion::create([
            'campo_id' => $this->campo->id,
            'dia_semana' => $diaSemana,
            'hora_apertura' => $apertura,
            'hora_cierre' => $cierre,
        ]);

        // Diurna + nocturna con IDs FIJOS del catálogo (mismo shape que ventanilla).
        $this->crearTarifa('diurna', 50.00, self::TARIFA_DIURNA_SIREB, 'Diurno');
        $this->crearTarifa('nocturna', 150.00, self::TARIFA_NOCTURNA_SIREB, 'Nocturno');
    }

    private function crearTarifa(string $clasificacion, float $precio, string $tarifaSirebId, string $etiqueta): TarifaCampo
    {
        return TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SERVICIO_SIREB,
            'tarifa_sireb_id' => $tarifaSirebId,
            'etiqueta_sireb' => $etiqueta,
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => $clasificacion,
            'tipo_tarifa' => $clasificacion,
            'precio_por_hora' => $precio,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => null,
            'sincronizado_en' => now(),
            'creado_por' => null,
        ]);
    }

    private function crearSolicitudConfirmada(string $fecha, string $ini, string $fin): SolicitudReserva
    {
        $s = SolicitudReserva::create([
            'codigo_seguimiento' => 'OCUP-' . Str::upper(Str::random(6)),
            'monto_total' => 150.00,
            'nombre_pagador' => 'Ocupante',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '00000000',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $s->id,
            'campo_id' => $this->campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $ini,
            'hora_fin' => $fin,
            'tarifa_aplicada' => 150.00,
        ]);

        $s->update(['estado' => EstadoSolicitudReserva::Confirmada]);

        return $s->fresh();
    }
}
