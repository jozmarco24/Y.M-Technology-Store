<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\Configuracion;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosTicketController extends Controller
{
    public function __construct(
        protected CajaService $cajaService
    ) {}

    /**
     * Vista imprimible de ticket térmico de 80mm para una venta.
     */
    public function ticketVenta(Venta $venta, Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // Políticas de visualización: vendedor solo ve sus ventas, admin ve todas
        if ($user->hasRole('vendedor') && $venta->vendedor_id !== $user->id) {
            abort(403, 'No tienes permiso para ver o imprimir esta venta.');
        }

        $venta->load(['cliente', 'vendedor', 'detalles.serie', 'detalles.producto', 'pagos']);

        $config = [
            'nombre' => Configuracion::obtener('tienda_nombre', 'TIENDA DE TECNOLOGÍA & HARDWARE'),
            'ruc' => Configuracion::obtener('tienda_ruc', '20601234568'),
            'razon_social' => Configuracion::obtener('tienda_razon_social', 'SYS-COPM IMPORTADORA S.A.C.'),
            'direccion' => Configuracion::obtener('tienda_direccion', 'Av. Garcilaso de la Vega 1348, Int. 204, Lima'),
            'telefono' => Configuracion::obtener('tienda_telefono', '(01) 424-5566'),
            'email' => Configuracion::obtener('tienda_email', 'ventas@tienda.pe'),
            'mensaje_pie' => Configuracion::obtener('tienda_mensaje_pie', '¡Gracias por su preferencia!'),
        ];

        return view('pos.ticket', [
            'venta' => $venta,
            'config' => $config,
        ]);
    }

    /**
     * Resumen imprimible de cierre de caja (ticket 80mm).
     */
    public function ticketCaja(CajaSesion $cajaSesion, Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('vendedor') && $cajaSesion->user_id !== $user->id) {
            abort(403, 'No tienes permiso para ver o imprimir esta sesión de caja.');
        }

        $resumen = $this->cajaService->resumen($cajaSesion);

        $config = [
            'nombre' => Configuracion::obtener('tienda_nombre', 'TIENDA DE TECNOLOGÍA & HARDWARE'),
            'ruc' => Configuracion::obtener('tienda_ruc', '20601234568'),
            'direccion' => Configuracion::obtener('tienda_direccion', 'Av. Garcilaso de la Vega 1348, Int. 204, Lima'),
        ];

        return view('pos.ticket_caja', [
            'sesion' => $cajaSesion,
            'resumen' => $resumen,
            'config' => $config,
        ]);
    }
}
