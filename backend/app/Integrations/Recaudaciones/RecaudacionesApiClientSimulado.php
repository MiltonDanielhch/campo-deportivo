<?php

namespace App\Integrations\Recaudaciones;

use App\DTOs\EstadoCobroDTO;
use App\DTOs\RespuestaCobroDTO;
use App\DTOs\SolicitudCobroDTO;
use App\DTOs\WebhookPayloadDTO;
use App\Exceptions\RecaudacionesApiException;
use Illuminate\Http\Request;

/**
 * Cliente simulado EXCLUSIVO para desarrollo y pruebas.
 *
 * Implementa TODO el contrato de RecaudacionesApiClientInterface (incluidos
 * los métodos de SIREB v1) para que los tests corran el flujo real sin
 * depender de SIREB. El modo se controla por config:
 *
 *   services.recaudaciones.simulado_modo              exito | fallo_conexion | error_respuesta
 *   services.recaudaciones.simulado_cliente_existe    false (cliente nuevo) | true (existente)
 *   services.recaudaciones.simulado_liquidacion_estado pendiente | pagada | anulada | no_existe
 *   services.recaudaciones.simulado_anulacion_modo    exito | no_anulable
 *   services.recaudaciones.simulado_polling_modo      pendiente | pagado | no_existe
 *
 * NUNCA se registra en producción (ver AppServiceProvider).
 */
class RecaudacionesApiClientSimulado implements RecaudacionesApiClientInterface
{
    // UUIDs fijos y VÁLIDOS (la columna servicio_sireb_id es uuid en Postgres).
    private const SERVICIO_ID = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const TARIFA_DIURNA_ID = 'aaaaaaaa-0000-4000-8000-0000000000d1';
    private const TARIFA_NOCTURNA_ID = 'aaaaaaaa-0000-4000-8000-0000000000d2';
    private const CLIENTE_EXISTENTE_ID = 'aaaaaaaa-0000-4000-8000-0000000000c1';

    /** Catálogo fake: un servicio con tarifa diurna (150) y nocturna (200). */
    private const CATALOGO_FAKE = [
        [
            'id' => self::SERVICIO_ID,
            'codigo' => 'SIM-CS1',
            'nombre' => 'Cancha Simulada 1',
            'unidad_medida' => 'hora',
            'tarifas' => [
                ['id' => self::TARIFA_DIURNA_ID, 'monto' => '150.00', 'etiqueta' => 'Diurno', 'categorias' => []],
                ['id' => self::TARIFA_NOCTURNA_ID, 'monto' => '200.00', 'etiqueta' => 'Nocturno', 'categorias' => []],
            ],
        ],
    ];

    // ── Legacy (Módulo 4): se conserva por compatibilidad, ya no lo usa el service ──

    public function solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO
    {
        $modo = config('services.recaudaciones.simulado_modo', 'exito');

        if ($modo === 'fallo_conexion') {
            throw RecaudacionesApiException::conexionFallida(
                'Simulado: no hay conexión con el Core de Recaudaciones',
            );
        }

        if ($modo === 'error_respuesta') {
            throw RecaudacionesApiException::respuestaInvalida(
                500,
                'Simulado: el Core devolvió un error interno',
            );
        }

        $sufijo = strtoupper(substr(md5($datos->referenciaExterna), 0, 10));

        return new RespuestaCobroDTO(
            referenciaRecaudaciones: 'CORE-SIM-'.$sufijo,
            qrString: 'https://core.gob.bo/pago/sim/'.$datos->referenciaExterna,
            qrImageBase64: null,
            checkoutUrl: 'https://core.gob.bo/checkout/sim/'.$datos->referenciaExterna,
        );
    }

    public function verificarFirma(Request $request): bool
    {
        return $request->header('X-Test-Signature') === 'test';
    }

    public function parsearWebhook(Request $request): WebhookPayloadDTO
    {
        return WebhookPayloadDTO::fromArray($request->json()->all());
    }

    public function consultarEstado(string $referenciaRecaudaciones): ?EstadoCobroDTO
    {
        $modo = config('services.recaudaciones.simulado_polling_modo', 'pendiente');

        if ($modo === 'no_existe') {
            return null;
        }

        if ($modo === 'pagado') {
            return new EstadoCobroDTO(pagado: true, montoConfirmado: 300.00);
        }

        return new EstadoCobroDTO(pagado: false);
    }

    // ── SIREB v1 ──

    public function buscarCliente(?string $ciNit): ?array
    {
        if (blank($ciNit)) {
            return null;
        }

        // Por defecto simula cliente NUEVO (null) para ejercitar registrarCliente.
        if (config('services.recaudaciones.simulado_cliente_existe', false)) {
            return [
                'id' => self::CLIENTE_EXISTENTE_ID,
                'ci_nit' => $ciNit,
                'nombre_completo' => 'Cliente Existente Sim',
                'telefono' => '70000000',
                'email' => null,
            ];
        }

        return null;
    }

    public function registrarCliente(array $datos): array
    {
        return [
            'id' => 'aaaaaaaa-0000-4000-8000-'.substr(str_pad(dechex(crc32($datos['ci_nit'] ?? '')), 8, '0', STR_PAD_LEFT), 0, 8),
            'ci_nit' => $datos['ci_nit'] ?? null,
            'nombre_completo' => $datos['nombre_completo'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
        ];
    }

    public function listarCatalogo(int $pagina = 1, int $porPagina = 100): array
    {
        return self::CATALOGO_FAKE;
    }

    public function crearLiquidacion(
        array $items,
        string $clienteId,
        string $idempotencyKey,
        string $referenciaExterna,
        ?string $sucursalId = null,
    ): array {
        $modo = config('services.recaudaciones.simulado_modo', 'exito');

        if ($modo === 'fallo_conexion') {
            throw RecaudacionesApiException::conexionFallida(
                'Simulado: SIREB no responde al crear liquidación',
            );
        }

        if ($modo === 'error_respuesta') {
            throw RecaudacionesApiException::respuestaInvalida(
                500,
                'Simulado: SIREB devolvió error interno al crear liquidación',
            );
        }

        $monto = 0.0;
        $itemsRespuesta = [];

        foreach ($items as $item) {
            $tarifa = $this->buscarTarifaFake($item['tarifa_id'] ?? '');
            $precio = (float) ($tarifa['monto'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $monto += $precio * $cantidad;

            $itemsRespuesta[] = [
                'id' => 'aaaaaaaa-0000-4000-8000-'.substr(str_pad(dechex(crc32(($item['tarifa_id'] ?? '').$referenciaExterna)), 8, '0', STR_PAD_LEFT), 0, 8),
                'servicio' => ['id' => self::SERVICIO_ID],
                'tarifa_id' => $item['tarifa_id'] ?? null,
                'descripcion' => $tarifa['etiqueta'] ?? 'Simulada',
                'cantidad' => number_format($cantidad, 3, '.', ''),
                'unidad_medida' => 'hora',
                'precio_unitario' => number_format($precio, 2, '.', ''),
                'subtotal' => number_format($precio * $cantidad, 2, '.', ''),
            ];
        }

        // UUID válido: 8-4-4-4-12 caracteres hex
        $hash = md5($idempotencyKey);

        return [
            'id' => 'aaaaaaaa-0000-4000-8000-'.strtolower(substr($hash, 0, 12)),
            'codigo_publico' => 'SIM-'.strtoupper(substr($hash, 0, 4)).'-'.strtoupper(substr($hash, 4, 4)).'-'.strtoupper(substr($hash, 8, 3)),
            'monto' => number_format($monto, 2, '.', ''),
            'estado' => 'pendiente',
            'canal_origen' => 'api_sistema',
            'referencia_externa' => $referenciaExterna,
            'sucursal_id' => $sucursalId,
            'fecha_emision' => now()->toIso8601String(),
            'fecha_vencimiento' => now()->addDays(3)->toIso8601String(),
            'cliente' => ['id' => $clienteId],
            'items' => $itemsRespuesta,
        ];
    }
    
    public function consultarLiquidacionPorCodigo(string $codigoPublico): ?array
    {
        $estado = config('services.recaudaciones.simulado_liquidacion_estado', 'pendiente');

        if ($estado === 'no_existe') {
            return null;
        }

        return [
            'id' => 'aaaaaaaa-0000-4000-8000-'.strtolower(substr(str_pad(dechex(crc32($codigoPublico)), 8, '0', STR_PAD_LEFT), 0, 12)),
            'codigo_publico' => $codigoPublico,
            'estado' => $estado,
            'monto' => '150.00',
            'tiene_pago_registrado' => $estado === 'pagada',
        ];
    }

    public function consultarLiquidacionDetalle(string $liquidacionId): ?array
    {
        return $this->consultarLiquidacionPorCodigo($liquidacionId);
    }

    public function anularLiquidacion(string $liquidacionId, string $motivo): array
    {
        if (config('services.recaudaciones.simulado_anulacion_modo', 'exito') === 'no_anulable') {
            throw RecaudacionesApiException::respuestaInvalida(
                422,
                'LIQUIDACION_NO_ANULABLE: la liquidación ya tiene pago activo',
            );
        }

        return ['id' => $liquidacionId, 'estado' => 'anulada', 'motivo_anulacion' => $motivo];
    }

    public function registrarPagoManual(string $liquidacionId, string $numeroBoleta, string $entidadBancaria): array
    {
        return [
            'liquidacion_id' => $liquidacionId,
            'numero_boleta' => $numeroBoleta,
            'entidad_bancaria' => $entidadBancaria,
            'estado' => 'pendiente_validacion',
        ];
    }

    private function buscarTarifaFake(string $tarifaId): ?array
    {
        foreach (self::CATALOGO_FAKE as $servicio) {
            foreach ($servicio['tarifas'] as $tarifa) {
                if ($tarifa['id'] === $tarifaId) {
                    return $tarifa;
                }
            }
        }

        return null;
    }
}
