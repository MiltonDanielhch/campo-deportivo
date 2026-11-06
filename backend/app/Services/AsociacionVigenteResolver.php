<?php

namespace App\Services;

use App\Models\Asociacion;

/**
 * Llave canonica del convenio: CI/NIT -> asociacion VIGENTE del GAD.
 *
 * Unifica:
 * - SolicitudReservaService::resolverAsociacionVigente
 * - CiudadanoAuthController::resolverAsociacionVigentePorCi
 *
 * NO unifica AsociacionController::porResponsable porque ese contrato es
 * "asociacion ACTIVA del responsable" (puede devolver vigente=false), no
 * "asociacion VIGENTE". Mezclarlos cambiaria la ventanilla sin necesidad.
 */
final class AsociacionVigenteResolver
{
    public function porCi(?string $ciNit): ?Asociacion
    {
        if (blank($ciNit)) {
            return null;
        }

        $asociacion = Asociacion::where('responsable_ci', $ciNit)
            ->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('convenio_hasta')
                    ->orWhere('convenio_hasta', '>=', now()->startOfDay());
            })
            ->first();

        return $asociacion?->esVigente() ? $asociacion : null;
    }
}