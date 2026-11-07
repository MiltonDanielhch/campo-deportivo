<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\DTOs\SolicitudReservaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolicitudReservaRequest;
use App\Http\Resources\SolicitudReservaResource;
use App\Models\Ciudadano;
use App\Services\SolicitudReservaService;
use Illuminate\Http\JsonResponse;

/**
 * Recepción pública de solicitudes de reserva (HU-D1).
 * Sin autenticación: el ciudadano no se registra.
 */
class SolicitudReservaController extends Controller
{
    public function __construct(private SolicitudReservaService $service)
    {
    }

    /**
     * POST /api/v1/public/solicitudes-reserva
     *
     * 201 → solicitud creada y franjas bloqueadas
     * 409 → una franja ya no está disponible (FranjaNoDisponibleException)
     * 422 → validación de forma o de reglas de negocio
     * 503 → el Core de cobro no respondió (Fase 4.2, HU-D8)
     */
    public function store(StoreSolicitudReservaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['ip_cobrador'] = $request->ip();

        // [Fase 6a] Si el middleware resolvió un Ciudadano logueado, el CI/NIT
        // del pagador sale del TOKEN, nunca del payload. Esto cierra el agujero
        // "tipear CI de asociación ajena para obtener Bs 25": sin token, el
        // payload manda (anónimo, como hoy); con token, el payload se ignora.
        /** @var Ciudadano|null $ciudadano */
        $ciudadano = $request->attributes->get('ciudadano');
        $convenioAsociacion = $request->attributes->get('convenio_asociacion');

        if ($ciudadano) {
            $validated['ci_nit_pagador'] = $ciudadano->ci;
            $validated['nombre_pagador'] = $ciudadano->nombre_completo;
            if (filled($ciudadano->telefono)) {
                $validated['telefono_pagador'] = $ciudadano->telefono;
            }
        }

        $dto = SolicitudReservaDTO::desdeArray($validated);

        // [Fase 6a] 3er arg: asociación vigente del token (null = anónimo).
        // El service la usa para: abrir visibility gate de convenio, dar plazo
        // largo, y validar pertenencia de alumnos contra su id.
        $resultado = $this->service->crear($dto, false, $convenioAsociacion);

        $cobro = $resultado->cobro;

        return (new SolicitudReservaResource($resultado->solicitud->load('detalles')))
            ->additional([
                'cobro' => $cobro ? [
                    'referencia_recaudaciones' => $cobro->referenciaRecaudaciones,
                    'qr_string' => $cobro->qrString,
                    'qr_image_base64' => $cobro->qrImageBase64,
                    'checkout_url' => $cobro->checkoutUrl,
                ] : null,
            ])
            ->response()
            ->setStatusCode(201);
    }
}
