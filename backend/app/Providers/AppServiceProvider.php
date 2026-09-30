<?php

namespace App\Providers;

use App\Integrations\Recaudaciones\RecaudacionesApiClient;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Integrations\Recaudaciones\RecaudacionesApiClientSimulado;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding condicional por flag de config (no por env)
        // Si simulador_habilitado=true → inyecta Simulado
        // Si simulador_habilitado=false → inyecta cliente real
        $usarSimulador = config('services.recaudaciones.simulador_habilitado', true);

        if ($usarSimulador) {
            $this->app->singleton(
                RecaudacionesApiClientInterface::class,
                RecaudacionesApiClientSimulado::class,
            );
        } else {
            $this->app->singleton(
                RecaudacionesApiClientInterface::class,
                RecaudacionesApiClient::class,
            );
        }
    }

    public function boot(): void
    {
        // (conservá cualquier contenido que ya tuviera)
    }
}
