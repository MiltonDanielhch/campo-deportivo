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
        // Binding condicional: simulador vs cliente real
        $usarSimulador = config('services.recaudaciones.simulador_habilitado', true);

        if ($usarSimulador) {
            $this->app->singleton(
                \App\Integrations\Recaudaciones\RecaudacionesApiClientInterface::class,
                \App\Integrations\Recaudaciones\RecaudacionesApiClientSimulado::class,
            );
        } else {
            $this->app->singleton(
                \App\Integrations\Recaudaciones\RecaudacionesApiClientInterface::class,
                \App\Integrations\Recaudaciones\RecaudacionesApiClient::class,
            );
        }
    }

    public function boot(): void
    {
        // (conservá cualquier contenido que ya tuviera)
    }
}
