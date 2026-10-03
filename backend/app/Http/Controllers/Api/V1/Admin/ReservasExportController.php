<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\SolicitudFiltrosDTO;
use App\Http\Controllers\Controller;
use App\Services\SolicitudReservaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación CSV de reservas/franjas.
 *
 * Fase 7.3:
 * - Solo admin_parametricas y admin_reservas.
 * - funcionario_control NO puede exportar.
 * - Reutiliza filtros del listado admin.
 * - Incluye asistencia marcada desde Fase 7.2.
 */
class ReservasExportController extends Controller
{
    public function __construct(
        private SolicitudReservaService $solicitudes,
    ) {
    }

    /**
     * GET /v1/admin/reservas/export?formato=csv
     */
    public function csv(Request $request): StreamedResponse
    {
        $formato = $request->query('formato', 'csv');

        if ($formato !== 'csv') {
            abort(422, 'Formato no soportado. Use formato=csv.');
        }

        /** @var \App\Models\Funcionario $funcionario */
        $funcionario = $request->user();

        $filtros = SolicitudFiltrosDTO::fromRequest($request);

        $filas = $this->solicitudes->exportarCsv($filtros, $funcionario);

        $filename = 'reservas-'.now()->format('Y-m-d').'.csv';

        return response()->stream(
            function () use ($filas) {
                $handle = fopen('php://output', 'w');

                // BOM UTF-8 para que Excel reconozca acentos/ñ correctamente.
                fwrite($handle, "\xEF\xBB\xBF");

                foreach ($filas as $fila) {
                    fputcsv($handle, $fila);
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-cache, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
