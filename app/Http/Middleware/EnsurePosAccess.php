<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('pos.login'));
        }

        // Acceden usuarios activos con rol vendedor o admin
        if (! $user->hasRole(['vendedor', 'admin'])) {
            abort(403, 'No tienes permisos para acceder al Punto de Venta (POS).');
        }

        return $next($request);
    }
}
