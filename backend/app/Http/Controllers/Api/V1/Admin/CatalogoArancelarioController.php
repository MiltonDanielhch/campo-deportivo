<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CatalogoSirebService;
use App\Services\Sireb\ClasificadorServicioSireb;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo arancelario clasificado.
 *
 * P8.1: solo lectura. No crea reservas, no toca tarifas locales, no modifica SIREB.
 * Sirve para validar visualmente qué servicios son base, adicionales, mensuales,
 * hospedaje, piscina, vivanderos, espectáculos o no clasificados.
 */
class CatalogoArancelarioController extends Controller
{
    public function __construct(
        private CatalogoSirebService $catalogo,
        private ClasificadorServicioSireb $clasificador,
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            $servicios = $this->catalogo->catalogoCacheado();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo obtener el catálogo SIREB.',
                'error' => $e->getMessage(),
            ], 503);
        }

        $grupos = [
            ClasificadorServicioSireb::ALQUILER_BASE => [],
            ClasificadorServicioSireb::SERVICIO_ADICIONAL => [],
            ClasificadorServicioSireb::ARRIENDO_PERIODICO => [],
            ClasificadorServicioSireb::HOSPEDAJE => [],
            ClasificadorServicioSireb::PISCINA => [],
            ClasificadorServicioSireb::SERVICIO_ESPECIAL => [],
            ClasificadorServicioSireb::EVENTO_ESPECIAL => [],
            ClasificadorServicioSireb::NO_CLASIFICADO => [],
        ];

        foreach ($servicios as $servicio) {
            $clasificacion = $this->clasificador->clasificar($servicio);
            $categoria = $clasificacion['categoria'];

            if (! array_key_exists($categoria, $grupos)) {
                $categoria = ClasificadorServicioSireb::NO_CLASIFICADO;
            }

            $grupos[$categoria][] = $this->presentarServicio($servicio, $clasificacion);
        }

        foreach ($grupos as &$lista) {
            usort($lista, fn (array $a, array $b) => strcmp(
                (string) ($a['codigo'] ?? ''),
                (string) ($b['codigo'] ?? '')
            ));
        }

        unset($lista);

        return response()->json([
            'data' => $grupos,
            'meta' => [
                'total_servicios' => count($servicios),
                'por_categoria' => array_map(
                    fn (array $lista) => count($lista),
                    $grupos
                ),
                'ultima_actualizacion' => $this->catalogo->ultimaActualizacion(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $servicio
     * @param  array{categoria: string, motivo: string}  $clasificacion
     * @return array<string, mixed>
     */
    private function presentarServicio(array $servicio, array $clasificacion): array
    {
        return [
            'id' => $servicio['id'] ?? null,
            'codigo' => $servicio['codigo'] ?? null,
            'nombre' => $servicio['nombre'] ?? null,
            'descripcion' => $servicio['descripcion'] ?? null,
            'unidad_medida' => $servicio['unidad_medida'] ?? null,
            'rubro' => $servicio['rubro'] ?? null,
            'modo_tarifa' => $servicio['modo_tarifa'] ?? null,
            'estado' => $servicio['estado'] ?? null,
            'requiere_factura' => $servicio['requiere_factura'] ?? null,
            'plazo_pago_dias' => $servicio['plazo_pago_dias'] ?? null,
            'categoria_p8' => $clasificacion['categoria'],
            'motivo_clasificacion' => $clasificacion['motivo'],
            'tarifas' => collect($servicio['tarifas'] ?? [])
                ->map(fn (array $tarifa) => [
                    'id' => $tarifa['id'] ?? null,
                    'monto' => $tarifa['monto'] ?? null,
                    'etiqueta' => $tarifa['etiqueta'] ?? null,
                    'estado' => $tarifa['estado'] ?? null,
                    'tarifario_estado' => $tarifa['tarifario_estado'] ?? null,
                    'liquidable' => $tarifa['liquidable'] ?? null,
                    'categorias' => $tarifa['categorias'] ?? [],
                ])
                ->values()
                ->all(),
        ];
    }
}