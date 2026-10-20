<?php

namespace Tests\Unit;

use App\Services\Sireb\ClasificadorServicioSireb;
use Tests\TestCase;

class ClasificadorServicioSirebTest extends TestCase
{
    private ClasificadorServicioSireb $clasificador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clasificador = new ClasificadorServicioSireb();
    }

    public function test_clasifica_cancha_sintetica_como_alquiler_base(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-CS1',
            'nombre' => 'Cancha Sintética N° 1',
            'descripcion' => 'Alquiler de cancha sintética por hora',
            'unidad_medida' => 'hora',
        ]);

        $this->assertSame(ClasificadorServicioSireb::ALQUILER_BASE, $resultado['categoria']);
    }

    public function test_clasifica_escenario_de_evento_como_alquiler_base(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-CV-EVENTO-NO-ESCENARIO',
            'nombre' => 'Cancha Vieja - Evento No Deportivo - Escenario',
            'unidad_medida' => 'hora',
        ]);

        $this->assertSame(ClasificadorServicioSireb::ALQUILER_BASE, $resultado['categoria']);
    }

    public function test_clasifica_limpieza_como_servicio_adicional(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-CV-EVENTO-NO-LIMPIEZA',
            'nombre' => 'Cancha Vieja - Evento No Deportivo - Limpieza',
            'unidad_medida' => 'actividad',
        ]);

        $this->assertSame(ClasificadorServicioSireb::SERVICIO_ADICIONAL, $resultado['categoria']);
    }

    public function test_clasifica_energia_monofasica_como_servicio_adicional(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-CV-EVENTO-NO-ENER-MONO',
            'nombre' => 'Cancha Vieja - Energía Monofásica',
            'unidad_medida' => 'hora',
        ]);

        $this->assertSame(ClasificadorServicioSireb::SERVICIO_ADICIONAL, $resultado['categoria']);
    }

    public function test_clasifica_energia_trifasica_como_servicio_adicional(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-CC-EVENTO-NO-ENER-TRI',
            'nombre' => 'Coliseo Cerrado Trinidad - Energía Trifásica',
            'unidad_medida' => 'hora',
        ]);

        $this->assertSame(ClasificadorServicioSireb::SERVICIO_ADICIONAL, $resultado['categoria']);
    }

    public function test_clasifica_kiosco_como_arriendo_periodico(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-KIOSCO-CANCHA-SINTETICA',
            'nombre' => 'Kiosco Cancha Sintética',
            'unidad_medida' => 'mes',
        ]);

        $this->assertSame(ClasificadorServicioSireb::ARRIENDO_PERIODICO, $resultado['categoria']);
    }

    public function test_clasifica_depositio_como_arriendo_periodico(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-EGM-DEPOSITO-MED',
            'nombre' => 'Estadio - Ambiente Depósito Mediano',
            'unidad_medida' => 'mes',
        ]);

        $this->assertSame(ClasificadorServicioSireb::ARRIENDO_PERIODICO, $resultado['categoria']);
    }

    public function test_clasifica_habitacional_no_afiliado_como_hospedaje(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-HAB-NO-AFILIADO',
            'nombre' => 'Complejo Habitacional - No afiliado',
            'unidad_medida' => 'persona_noche',
        ]);

        $this->assertSame(ClasificadorServicioSireb::HOSPEDAJE, $resultado['categoria']);
    }

    public function test_clasifica_colchon_como_hospedaje(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-HAB-COLCHON',
            'nombre' => 'Complejo Habitacional - Alquiler de colchón',
            'unidad_medida' => 'dia',
        ]);

        $this->assertSame(ClasificadorServicioSireb::HOSPEDAJE, $resultado['categoria']);
    }

    public function test_clasifica_piscina_olimpica_como_piscina(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => '0005',
            'nombre' => 'H. PISCINA OLIMPICA',
            'descripcion' => 'Bañista mayores de 12 años',
            'unidad_medida' => 'hora',
        ]);

        $this->assertSame(ClasificadorServicioSireb::PISCINA, $resultado['categoria']);
    }

    public function test_clasifica_vivandero_como_servicio_especial(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-EGM-VIV-DEP-COM-DENTRO',
            'nombre' => 'Estadio - Vivandero Deportivo - Comida dentro',
            'unidad_medida' => 'actividad',
        ]);

        $this->assertSame(ClasificadorServicioSireb::SERVICIO_ESPECIAL, $resultado['categoria']);
    }

    public function test_clasifica_espectaculo_internacional_como_evento_especial(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-EGM-ESPECT-ART-INTL',
            'nombre' => 'Estadio - Espectáculo Artístico Internacional',
            'unidad_medida' => 'actividad',
        ]);

        $this->assertSame(ClasificadorServicioSireb::EVENTO_ESPECIAL, $resultado['categoria']);
    }

    public function test_clasifica_pista_sin_boletos_como_evento_especial(): void
    {
        $resultado = $this->clasificador->clasificar([
            'codigo' => 'SEDEDE-EGM-PISTA-SIN-BOLETOS',
            'nombre' => 'Estadio - Pista Atlética - Sin venta de boletos',
            'unidad_medida' => 'actividad',
        ]);

        $this->assertSame(ClasificadorServicioSireb::EVENTO_ESPECIAL, $resultado['categoria']);
    }
}