<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DevolucionController;
use App\Http\Controllers\Api\V1\GarantiaController;
use App\Http\Controllers\Api\V1\OrdenServicioController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        // Diagnóstico y Salud del Sistema (Solo Admin)
        Route::get('/salud', function () {
            /** @var User $user */
            $user = auth()->user();
            if (! $user->hasRole('admin')) {
                abort(403, 'Acceso restringido a administradores.');
            }

            return response()->json([
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'base_de_datos' => 'operativa',
                'espacio_libre_gb' => round(disk_free_space(base_path()) / (1024 * 1024 * 1024), 2),
                'jobs_pendientes' => DB::table('jobs')->count(),
                'jobs_fallidos' => DB::table('failed_jobs')->count(),
                'pedidos_hoy' => Pedido::whereDate('created_at', today())->count(),
            ]);
        });
    });
