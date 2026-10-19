<?php

use App\Jobs\EnviarComprobanteJob;
use App\Models\Carrito;
use App\Models\ComprobanteElectronico;
use App\Services\GarantiaService;
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

Schedule::call(function () {
    app(GarantiaService::class)->marcarVencidas();
})->dailyAt('00:01')->name('marcar-garantias-vencidas');

Schedule::command('pedidos:expirar')->everyMinute()->name('expirar-pedidos-vencidos');

// Limpieza de carritos de invitados mayores a 30 días
Schedule::call(function () {
    Carrito::whereNull('cliente_web_id')
        ->where('updated_at', '<', now()->subDays(30))
        ->delete();
})->dailyAt('03:00')->name('limpiar-carritos-invitado-vencidos');

// Conciliación de pagos cada hora
Schedule::command('pagos:conciliar --dias=1')->hourly()->name('conciliar-pagos-pasarela');
