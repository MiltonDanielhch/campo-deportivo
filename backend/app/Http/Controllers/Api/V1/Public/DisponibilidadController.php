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

        // [FIX #2 tz — controller público] El gate de ventana debe comparar en
        // el MISMO tz que DisponibilidadService usa para etiquetar 'pasada'
        // (America/La_Paz). Antes usaba Carbon::today() = tz del servidor
        // (UTC en Docker), así que a las 23:30 Bolivia el "hoy" del controller
        // ya era mañana y rechazaba 422 una fecha legítima.
        // TODO: unificar con DisponibilidadService en App\Support\FechaBolivia
        // (mismo helper que propone el TODO de ese service). No se extrae acá
        // para no tocar el service verde (224 tests) por higiene.
        $timezoneBolivia = 'America/La_Paz';

        $fecha = Carbon::parse($data['fecha'], $timezoneBolivia)->startOfDay();
        $hoy = Carbon::now($timezoneBolivia)->startOfDay();
        $limite = $hoy->copy()->addDays(60); // sincronizar con VENTANA_DIAS si el PO lo mueve

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
