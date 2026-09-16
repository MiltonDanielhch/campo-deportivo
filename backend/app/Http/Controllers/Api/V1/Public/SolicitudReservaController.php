<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\DTOs\SolicitudReservaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolicitudReservaRequest;
use App\Http\Resources\SolicitudReservaResource;
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
        $dto = SolicitudReservaDTO::desdeArray($request->validated());

        $solicitud = $this->service->crear($dto);

        return (new SolicitudReservaResource($solicitud->load('detalles')))
            ->additional([
                // Fase 4.2: aquí viajará la respuesta del Core
                // (referencia_recaudaciones, qr, checkoutUrl).
                'cobro' => null,
            ])
            ->response()
            ->setStatusCode(201);
    }
}
