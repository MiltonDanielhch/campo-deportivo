<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CrearCampoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampoDeportivoRequest;
use App\Models\CampoDeportivo;
use App\Services\CampoDeportivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampoDeportivoController extends Controller
{
    /** Disco y carpeta donde se guardan las fotos de los campos. */
    private const DISCO_IMAGENES = 'public';
    private const CARPETA_IMAGENES = 'campos';

    public function __construct(
        private CampoDeportivoService $service
    ) {}

    /**
     * GET /api/v1/campos-deportivos
     */
    public function index(Request $request): JsonResponse
    {
        $campos = $this->service->listar(
            tipoCampoId: $request->query('tipo_campo_id'),
            estado: $request->query('estado'),
        );

        return response()->json($campos);
    }

    /**
     * GET /api/v1/campos-deportivos/{campoDeportivo}
     */
    public function show(CampoDeportivo $campoDeportivo): JsonResponse
    {
        $campo = $this->service->obtenerDetalle($campoDeportivo);

        return response()->json(['data' => $campo]);
    }

    /**
     * POST /api/v1/campos-deportivos
     * Acepta multipart/form-data con archivo opcional en "imagen".
     */
    public function store(StoreCampoDeportivoRequest $request): JsonResponse
    {
        // Quitamos imagen/quitar_imagen del payload que va al DTO/servicio
        $datos = $request->safe()->except(['imagen', 'quitar_imagen']);

        $dto = CrearCampoDTO::fromArray($datos);
        $campo = $this->service->crear($dto);
        $campo = $this->procesarImagen($request, $campo);

        return response()->json([
            'message' => 'Campo deportivo creado exitosamente',
            'data' => $campo,
        ], 201);
    }

    /**
     * PUT /api/v1/campos-deportivos/{campoDeportivo}
     * Edita datos generales, NO el estado.
     * Acepta multipart/form-data con archivo opcional en "imagen".
     */
    public function update(StoreCampoDeportivoRequest $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $datos = $request->safe()->except(['imagen', 'quitar_imagen']);

        $campo = $this->service->actualizar($campoDeportivo, $datos);
        $campo = $this->procesarImagen($request, $campo);

        return response()->json([
            'message' => 'Campo deportivo actualizado exitosamente',
            'data' => $campo,
        ]);
    }

    /**
     * PATCH /api/v1/campos-deportivos/{campoDeportivo}/estado
     * Cambia el estado (activo/mantenimiento/inactivo).
     */
    public function cambiarEstado(Request $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in(['activo', 'mantenimiento', 'inactivo'])],
        ]);

        if ($campoDeportivo->estado === $validated['estado']) {
            return response()->json([
                'message' => 'El campo ya se encuentra en ese estado',
                'data' => $campoDeportivo,
            ]);
        }

        $campo = $this->service->cambiarEstado($campoDeportivo, $validated['estado']);

        return response()->json([
            'message' => 'Estado del campo actualizado',
            'data' => $campo,
        ]);
    }

    /**
     * PATCH /api/v1/campos-deportivos/{campoDeportivo}/hora-noche
     * Actualiza SOLO la hora de corte diurna/nocturna del campo.
     */
    public function actualizarHoraNoche(Request $request, CampoDeportivo $campoDeportivo): JsonResponse
    {
        $validated = $request->validate([
            'hora_inicio_noche' => ['required', 'date_format:H:i'],
        ]);

        $campoDeportivo->update([
            'hora_inicio_noche' => $validated['hora_inicio_noche'] . ':00',
        ]);

        return response()->json([
            'message' => 'Hora de inicio nocturno actualizada',
            'data' => $campoDeportivo,
        ]);
    }

    /**
     * Sube, reemplaza o elimina la foto del campo.
     *
     * Persiste SOLO el path relativo dentro del disco (ej: "campos/abc.jpg").
     * La URL pública se calcula en runtime vía accessor del modelo, lo que hace
     * el cambio de driver (local → s3 → gcs) transparente sin migración de datos.
     */
    private function procesarImagen(Request $request, CampoDeportivo $campo): CampoDeportivo
    {
        if ($request->hasFile('imagen')) {
            $this->borrarImagenActual($campo);

            // store() devuelve el path relativo dentro del disco (ej: "campos/ThHm...jpg")
            $path = $request->file('imagen')->store(
                self::CARPETA_IMAGENES,
                self::DISCO_IMAGENES
            );

            $campo->update(['imagen_url' => $path]);

            return $campo;
        }

        if ($request->boolean('quitar_imagen')) {
            $this->borrarImagenActual($campo);
            $campo->update(['imagen_url' => null]);
        }

        return $campo;
    }

    /**
     * Elimina el archivo físico de la foto actual (si existe).
     * Como ahora guardamos solo el path, ya no hace falta parse_url.
     */
    private function borrarImagenActual(CampoDeportivo $campo): void
    {
        if (! $campo->imagen_url) {
            return;
        }

        $path = $campo->getRawOriginal('imagen_url');

        if ($path && Storage::disk(self::DISCO_IMAGENES)->exists($path)) {
            Storage::disk(self::DISCO_IMAGENES)->delete($path);
        }
    }
}
