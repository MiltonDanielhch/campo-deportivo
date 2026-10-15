<?php

namespace Tests\Unit;

use App\Services\Sireb\ClasificadorTarifaSireb;
use Tests\TestCase;

class ClasificadorTarifaSirebTest extends TestCase
{
    private ClasificadorTarifaSireb $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = new ClasificadorTarifaSireb();
    }

    public function test_canchas_diurna_nocturna_por_etiqueta(): void
    {
        $servicio = $this->servicioConTarifas([
            ['id' => 't1', 'monto' => '50.00', 'etiqueta' => 'Diurno', 'liquidable' => true, 'categorias' => []],
            ['id' => 't2', 'monto' => '100.00', 'etiqueta' => 'Nocturno', 'liquidable' => true, 'categorias' => []],
        ]);

        $diurna = $this->clasificador->clasificar($servicio['tarifas'][0], $servicio);
        $nocturna = $this->clasificador->clasificar($servicio['tarifas'][1], $servicio);

        $this->assertSame('diurna', $diurna->tipo->value);
        $this->assertNull($diurna->perfil);
        $this->assertSame('etiqueta', $diurna->senal);

        $this->assertSame('nocturna', $nocturna->tipo->value);
        $this->assertNull($nocturna->perfil);
        $this->assertSame('etiqueta', $nocturna->senal);
    }

    public function test_cs3_tarifa_unica_cuando_etiqueta_es_null_y_hay_una_sola_liquidable(): void
    {
        $servicio = $this->servicioConTarifas([
            ['id' => 't3', 'monto' => '60.00', 'etiqueta' => null, 'liquidable' => true, 'categorias' => []],
        ]);

        $unica = $this->clasificador->clasificar($servicio['tarifas'][0], $servicio);

        $this->assertSame('unica', $unica->tipo->value);
        $this->assertNull($unica->perfil);
        $this->assertSame('unica', $unica->senal);
    }

    public function test_piscina_limpia_clasifica_perfil_y_horario_por_etiqueta(): void
    {
        $servicio = $this->servicioConTarifas([
            ['id' => 'p1', 'monto' => '10.00', 'etiqueta' => 'Bañista mayores de 12 (diurno)', 'liquidable' => true, 'categorias' => []],
            ['id' => 'p2', 'monto' => '15.00', 'etiqueta' => 'Bañista mayores de 12 (nocturno)', 'liquidable' => true, 'categorias' => []],
            ['id' => 'p3', 'monto' => '10.00', 'etiqueta' => 'Bañista menores de 12 (diurno)', 'liquidable' => true, 'categorias' => []],
            ['id' => 'p4', 'monto' => '10.00', 'etiqueta' => 'Bañista menores de 12 (nocturno)', 'liquidable' => true, 'categorias' => []],
            ['id' => 'p5', 'monto' => '20.00', 'etiqueta' => 'Institución (diurno)', 'liquidable' => true, 'categorias' => []],
            ['id' => 'p6', 'monto' => '30.00', 'etiqueta' => 'Institución (nocturno)', 'liquidable' => true, 'categorias' => []],
        ]);

        $expectativas = [
            ['diurna', 'mayor_12'],
            ['nocturna', 'mayor_12'],
            ['diurna', 'menor_12'],
            ['nocturna', 'menor_12'],
            ['diurna', 'institucion'],
            ['nocturna', 'institucion'],
        ];

        foreach ($servicio['tarifas'] as $i => $tarifa) {
            $r = $this->clasificador->clasificar($tarifa, $servicio);
            $this->assertSame($expectativas[$i][0], $r->tipo->value);
            $this->assertSame($expectativas[$i][1], $r->perfil);
            $this->assertSame('etiqueta', $r->senal);
        }
    }

    public function test_fail_closed_no_adivina_por_monto_cuando_la_etiqueta_no_tiene_horario(): void
    {
        $servicio = $this->servicioConTarifas([
            ['id' => 'old1', 'monto' => '10.00', 'etiqueta' => 'Bañista mayores de 12 años', 'liquidable' => true, 'categorias' => []],
            ['id' => 'old2', 'monto' => '15.00', 'etiqueta' => 'Bañista mayores de 12 años', 'liquidable' => true, 'categorias' => []],
        ]);

        $r1 = $this->clasificador->clasificar($servicio['tarifas'][0], $servicio);
        $r2 = $this->clasificador->clasificar($servicio['tarifas'][1], $servicio);

        $this->assertSame('no_clasificada', $r1->tipo->value);
        $this->assertSame('mayor_12', $r1->perfil);

        $this->assertSame('no_clasificada', $r2->tipo->value);
        $this->assertSame('mayor_12', $r2->perfil);
    }

    private function servicioConTarifas(array $tarifas): array
    {
        return [
            'id' => 'servicio-test',
            'codigo' => 'TEST',
            'nombre' => 'Servicio test',
            'unidad_medida' => 'hora',
            'rubro' => 'alquiler_espacio',
            'modo_tarifa' => 'fija',
            'estado' => 'activo',
            'tarifas' => $tarifas,
        ];
    }
}
