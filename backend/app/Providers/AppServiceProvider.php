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
        // El simulado SOLO en local/testing; en producción el cliente real.
        if ($this->app->environment('local', 'testing')) {
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
