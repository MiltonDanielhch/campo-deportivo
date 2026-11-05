<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\ParametroSistema;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\Asociacion;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;   // ← facade, no Illuminate\Queue\Queue
use Tests\TestCase;

/**
 * Recepción pública de solicitudes de reserva multi-franja (HU-D1, HU-D2).
 */
class SolicitudReservaControllerTest extends TestCase
{
    use RefreshDatabase;

    private TipoCampo $tipo;
    private CampoDeportivo $campo;
    private Funcionario $adminFicticio;

    protected function setUp(): void
    {
        parent::setUp();

        // Rol con permisos globales para el test (firma quien fija la tarifa)
        $rol = Rol::create([
            'nombre' => 'admin_parametricas_test',
            'permisos' => ['*'],
        ]);

        $this->adminFicticio = Funcionario::create([
            'nombre_completo' => 'Admin Test ' . uniqid(),
            'ci' => 'TEST-' . uniqid(),
            'usuario' => 'admin_test_' . uniqid(),
            'password_hash' => bcrypt('not-used'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $this->tipo = TipoCampo::create([
            'nombre' => 'Fútbol Test',
            'estado' => 'activo',
        ]);

        $this->campo = $this->crearCampoConHorariosYTarifa();

        Queue::fake(); // ← los jobs no corren inline; la solicitud queda pendiente
    }

    /**
     * Helper: campo activo con horarios los 7 días (08:00-20:00) y tarifa vigente.
     * Con $precio = 0 se crea SIN tarifa (para probar el 422).
     */
    private function crearCampoConHorariosYTarifa(float $precio = 150.0): CampoDeportivo
    {
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $this->tipo->id,
            'codigo' => 'TEST-' . uniqid(),
            'nombre' => 'Campo ' . uniqid(),
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00',
                'hora_cierre' => '20:00',
            ]);
        }

        if ($precio > 0) {
            TarifaCampo::create([
                'campo_id' => $campo->id,
                'tipo_tarifa' => 'diurna',
                'precio_por_hora' => $precio,
                'vigente_desde' => now(),
                'creado_por' => $this->adminFicticio->id,
            ]);
        }

        return $campo;
    }

    private function payloadFranjas(array $franjas): array
    {
        return [
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'franjas' => $franjas,
        ];
    }

    private function franja(CampoDeportivo $campo, string $fecha, string $inicio = '10:00', string $fin = '11:00'): array
    {
        return [
            'campo_id' => $campo->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ];
    }

    public function test_solicitud_multifranja_crea_cabecera_y_detalles_con_monto_correcto(): void
    {
        $fecha1 = Carbon::tomorrow()->toDateString();
        $fecha2 = Carbon::tomorrow()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([
            $this->franja($this->campo, $fecha1),
            $this->franja($this->campo, $fecha2),
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.codigo_seguimiento', fn ($codigo) => preg_match('/^RES-\d{8}-[A-Z2-9]{6}$/', $codigo) === 1);
        $response->assertJsonCount(2, 'data.franjas');

        $this->assertDatabaseCount('solicitudes_reserva', 1);
        $this->assertDatabaseCount('solicitud_reserva_detalle', 2);

        $solicitud = SolicitudReserva::first();
        $this->assertSame(300.0, (float) $solicitud->monto_total, 'El monto debe ser la suma de las 2 tarifas de 150');
        $this->assertSame('pendiente', $solicitud->estado->value);

        // Cada detalle congela la tarifa aplicada
        foreach ($solicitud->detalles as $detalle) {
            $this->assertSame(150.0, (float) $detalle->tarifa_aplicada);
        }
    }

    public function test_franja_ya_cubierta_por_solicitud_activa_responde_409(): void
    {
        $fecha = Carbon::tomorrow()->toDateString();
        $franja = $this->franja($this->campo, $fecha);

        // Primera solicitud: gana la franja
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([$franja]))
            ->assertStatus(201);

        // Segunda solicitud sobre la misma franja: 409
        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([$franja]));

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'franja_no_disponible');
        $this->assertDatabaseCount('solicitudes_reserva', 1);
    }

    public function test_expira_en_corresponde_al_parametro_vigente(): void
    {
        // Fijamos un valor distinto del fallback para probar que LEE el parámetro
        ParametroSistema::updateOrCreate(
            ['clave' => 'solicitud_reserva_expiracion_minutos'],
            ['valor' => '30', 'descripcion' => 'Test de expiración'],
        );

        $fecha = Carbon::tomorrow()->toDateString();

        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([
            $this->franja($this->campo, $fecha),
        ]))->assertStatus(201);

        $solicitud = SolicitudReserva::first();

        // expira_en ≈ ahora + 30 minutos (tolerancia de 5 segundos de ejecución)
        $this->assertEqualsWithDelta(
            30 * 60,
            now()->diffInSeconds($solicitud->expira_en),
            5,
        );
    }

    public function test_campo_en_mantenimiento_responde_422(): void
    {
        $campo = $this->crearCampoConHorariosYTarifa();
        $campo->update(['estado' => 'mantenimiento']);

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([
            $this->franja($campo, Carbon::tomorrow()->toDateString()),
        ]));

        $response->assertStatus(422);
    }

    public function test_campo_sin_tarifa_vigente_responde_422(): void
    {
        $campo = $this->crearCampoConHorariosYTarifa(precio: 0);

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payloadFranjas([
            $this->franja($campo, Carbon::tomorrow()->toDateString()),
        ]));

        $response->assertStatus(422);
    }

    public function test_asociacion_vigente_en_canal_publico_paga_plazo_publico_no_convenio(): void
    {
        // [convenio-fix #4] El plazo largo es del CANAL ventanilla (boleta que
        // "queda a la espera de validacion"), no de la identidad "es asociacion".
        // Una asociacion que reserva por web-public paga online con QR -> plazo
        // publico normal, NO 3 dias. Si el fix regresara (plazo atado a identidad),
        // esto daria ~4320 min y el assert de 30 min falla.
        ParametroSistema::updateOrCreate(
            ['clave' => 'solicitud_reserva_expiracion_minutos'],
            ['valor' => '30', 'descripcion' => 'Test expiracion canal publico'],
        );
        ParametroSistema::updateOrCreate(
            ['clave' => 'convenio_expiracion_dias'],
            ['valor' => '3', 'descripcion' => 'Test expiracion convenio'],
        );

        $ciResponsable = '7654321';
        Asociacion::create([
            'nit' => 'NIT-' . uniqid(),
            'nombre' => 'Asociacion Test Publico',
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-TEST-PUB',
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->addYear()->toDateString(),
            'responsable_nombre' => 'Responsable Test',
            'responsable_ci' => $ciResponsable,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);

        $fecha = Carbon::tomorrow()->toDateString();

        // Payload inline (no payloadFranjas) porque el CI del pagador debe
        // coincidir con responsable_ci de la asociacion.
        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Responsable Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ciResponsable,
            'franjas' => [$this->franja($this->campo, $fecha)],
        ])->assertStatus(201);

        $solicitud = SolicitudReserva::first();

        $this->assertEqualsWithDelta(
            30 * 60,
            now()->diffInSeconds($solicitud->expira_en),
            5,
        );
    }
}
