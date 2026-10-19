<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DevolucionController;
use App\Http\Controllers\Api\V1\GarantiaController;
use App\Http\Controllers\Api\V1\OrdenServicioController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\SerieController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::get('/productos/{id}', [ProductoController::class, 'show']);
        Route::get('/series/{numero}', [SerieController::class, 'show']);

        // Fase 3C: Garantías, Órdenes de Servicio y Devoluciones
        Route::get('/garantias/{numero_serie}', [GarantiaController::class, 'show']);
        Route::get('/ordenes-servicio', [OrdenServicioController::class, 'index']);
        Route::get('/ordenes-servicio/{codigo}', [OrdenServicioController::class, 'show']);
        Route::post('/devoluciones', [DevolucionController::class, 'store']);
    });
