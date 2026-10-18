<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\SerieController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::get('/productos/{id}', [ProductoController::class, 'show']);
        Route::get('/series/{numero}', [SerieController::class, 'show']);
    });
