<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\DisponibilidadResource;
use App\Models\CampoDeportivo;
use App\Services\DisponibilidadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilidad horaria pública por campo y fecha (Épica C, HU-C2).
 * Sin autenticación: ciudadanos anónimos consultan franjas libres.
 */
class DisponibilidadController extends Controller
{
    public function __construct(private DisponibilidadService $service)
    {
    }

    /**
     * GET /api/v1/public/campos/{campo}/disponibilidad?fecha=YYYY-MM-DD
     */
    public function show(Request $request, string $campo): DisponibilidadResource
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        // Validar ventana: hoy ≤ fecha ≤ hoy + 60 días
        $fecha = Carbon::parse($data['fecha'])->startOfDay();
        $hoy = Carbon::today();
        $limite = $hoy->copy()->addDays(60);

        if ($fecha->lt($hoy) || $fecha->gt($limite)) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha debe estar entre hoy y 60 días en el futuro.',
            ]);
        }

        // Campo visible al público (activo o mantenimiento; inactivo → 404)
        $campo = CampoDeportivo::query()
            ->whereIn('estado', ['activo', 'mantenimiento'])
            ->with(['horariosAtencion', 'tarifas' => fn ($q) => $q->whereNull('vigente_hasta')])
            ->findOrFail($campo);

        $grilla = $this->service->calcularGrilla($campo, $fecha);

        return new DisponibilidadResource($campo, $grilla);
    }
}