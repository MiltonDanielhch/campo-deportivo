<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitudReserva;
use App\Models\AsignacionFuncionario;
use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\ParametroSistema;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_campo_crud(): void
    {
        $tipo = TipoCampo::create([
            'nombre' => 'Fútbol',
            'descripcion' => 'Cancha de fútbol',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('tipos_campo', ['id' => $tipo->id, 'nombre' => 'Fútbol']);
        $this->assertEquals('Fútbol', $tipo->fresh()->nombre);
    }

    public function test_campo_deportivo_con_relaciones(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $this->assertEquals($tipo->id, $campo->tipoCampo->id);
        $this->assertCount(1, $tipo->camposDeportivos);
    }

    public function test_horario_atencion(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $horario = HorarioAtencion::create([
            'campo_id' => $campo->id,
            'dia_semana' => 1, // Lunes
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '20:00:00',
        ]);

        $this->assertEquals($campo->id, $horario->campo->id);
        $this->assertCount(1, $campo->horariosAtencion);
    }

    public function test_tarifa_campo_con_versionado(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $rol = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Administrador de paramétricas',
            'permisos' => ['*'],
        ]);
        $funcionario = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'password_hash' => bcrypt('password'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $tarifa = TarifaCampo::create([
            'campo_id' => $campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 150.50,
            'vigente_desde' => now(),
            'creado_por' => $funcionario->id,
        ]);

        $this->assertEquals('150.50', $tarifa->precio_por_hora);
        $this->assertEquals($funcionario->id, $tarifa->creadoPor->id);
        $this->assertCount(1, $campo->tarifas);
    }

    public function test_asignacion_funcionario(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $rol = Rol::create([
            'nombre' => 'funcionario_control',
            'descripcion' => 'Funcionario de control',
            'permisos' => ['ver-reservas'],
        ]);
        $funcionario = Funcionario::create([
            'nombre_completo' => 'Control Test',
            'ci' => '87654321',
            'usuario' => 'control_test',
            'password_hash' => bcrypt('password'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $asignacion = AsignacionFuncionario::create([
            'funcionario_id' => $funcionario->id,
            'campo_id' => $campo->id,
            'asignado_en' => now(),
        ]);

        $this->assertEquals($campo->id, $asignacion->campo->id);
        $this->assertCount(1, $funcionario->asignaciones);
        $this->assertCount(1, $campo->asignacionesFuncionario);
    }

    public function test_solicitud_reserva_con_enum(): void
    {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'SOL-2026-001',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '78901234',
            'ci_nit_pagador' => '12345678',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        $this->assertEquals(EstadoSolicitudReserva::Pendiente, $solicitud->estado);
        $this->assertEquals('300.00', $solicitud->monto_total);
        $this->assertEquals('pendiente', $solicitud->estado->value);
    }

    public function test_solicitud_reserva_detalle(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'SOL-2026-001',
            'monto_total' => 150.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '78901234',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => '2026-09-15',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'tarifa_aplicada' => 150.00,
        ]);

        $this->assertEquals($solicitud->id, $detalle->solicitud->id);
        $this->assertEquals($campo->id, $detalle->campo->id);
        $this->assertCount(1, $solicitud->detalles);
    }

    public function test_reserva(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'SOL-2026-001',
            'monto_total' => 150.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '78901234',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => '2026-09-15',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'tarifa_aplicada' => 150.00,
        ]);

        $reserva = Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RES-2026-001',
            'campo_id' => $campo->id,
            'fecha_reserva' => '2026-09-15',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'monto_pagado' => 150.00,
            'confirmado_en' => now(),
        ]);

        $this->assertEquals($solicitud->id, $reserva->solicitud->id);
        $this->assertEquals($detalle->id, $reserva->detalle->id);
        $this->assertEquals($campo->id, $reserva->campo->id);
        $this->assertCount(1, $solicitud->reservas);
        $this->assertCount(1, $campo->reservas);
    }

    public function test_parametro_sistema(): void
    {
        $parametro = ParametroSistema::create([
            'clave' => 'tiempo_expiracion_minutos',
            'valor' => '15',
            'descripcion' => 'Tiempo de expiración de solicitudes',
        ]);

        $this->assertEquals('tiempo_expiracion_minutos', $parametro->clave);
        $this->assertEquals('15', $parametro->valor);
    }

    public function test_auditoria(): void
    {
        $rol = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Administrador de paramétricas',
            'permisos' => ['*'],
        ]);
        $funcionario = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'password_hash' => bcrypt('password'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $auditoria = Auditoria::create([
            'tabla' => 'tipos_campo',
            'registro_id' => fake()->uuid(),
            'accion' => 'crear',
            'usuario_id' => $funcionario->id,
            'datos_anteriores' => null,
            'datos_nuevos' => ['nombre' => 'Fútbol', 'estado' => 'activo'],
            'fecha' => now(),
        ]);

        $this->assertIsArray($auditoria->datos_nuevos);
        $this->assertEquals('Fútbol', $auditoria->datos_nuevos['nombre']);
        $this->assertEquals($funcionario->id, $auditoria->usuario->id);
    }
}
