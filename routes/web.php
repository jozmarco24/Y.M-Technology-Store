<?php

declare(strict_types=1);

use App\Http\Controllers\PosAuthController;
use App\Http\Controllers\PosTicketController;
use App\Http\Middleware\EnsurePosAccess;
use App\Livewire\Pos\Venta as PosVenta;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Redirección de la raíz según autenticación y rol
Route::get('/', function () {
    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();
        if ($user->hasRole('vendedor')) {
            return redirect()->route('pos.index');
        }

        return redirect('/admin');
    }

    return redirect()->route('pos.login');
});

// Autenticación del POS
Route::get('/pos/login', [PosAuthController::class, 'showLogin'])->name('pos.login');
Route::post('/pos/login', [PosAuthController::class, 'login'])->name('pos.login.submit');
Route::post('/pos/logout', [PosAuthController::class, 'logout'])->name('pos.logout');

// Rutas protegidas del Punto de Venta (acceden vendedores y admin)
Route::middleware(['auth', EnsurePosAccess::class])->group(function () {
    Route::get('/pos', PosVenta::class)->name('pos.index');
    Route::get('/pos/ticket/{venta}', [PosTicketController::class, 'ticketVenta'])->name('pos.ticket');
    Route::get('/pos/caja/{cajaSesion}/ticket', [PosTicketController::class, 'ticketCaja'])->name('pos.ticket_caja');
});
