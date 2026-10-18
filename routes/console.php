<?php

use App\Jobs\EnviarComprobanteJob;
use App\Models\ComprobanteElectronico;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $maxIntentos = (int) config('facturacion.max_intentos', 5);
    $comprobantes = ComprobanteElectronico::reintentables($maxIntentos)->get();

    foreach ($comprobantes as $comprobante) {
        EnviarComprobanteJob::dispatch($comprobante->venta_id)
            ->onQueue(config('facturacion.cola', 'facturacion'));
    }
})->everyTenMinutes()->name('reintentar-comprobantes-electronicos');
