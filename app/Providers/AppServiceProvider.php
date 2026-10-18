<?php

namespace App\Providers;

use App\Services\Facturacion\EmisorComprobante;
use App\Services\Facturacion\EmisorSimulado;
use App\Services\Facturacion\NubefactEmisor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            EmisorComprobante::class,
            function () {
                $modo = config('facturacion.modo', 'simulado');

                return match ($modo) {
                    'demo', 'produccion' => new NubefactEmisor,
                    default => new EmisorSimulado,
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
