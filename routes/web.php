<?php

declare(strict_types=1);

use App\Http\Controllers\ConsultaGarantiaPublicaController;
use App\Http\Controllers\DocumentosImprimiblesController;
use App\Http\Controllers\PosAuthController;
use App\Http\Controllers\PosTicketController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WebhookCulqiController;
use App\Http\Middleware\EnsurePosAccess;
use App\Livewire\Ecommerce\AuthCliente;
use App\Livewire\Ecommerce\CarritoPagina;
use App\Livewire\Ecommerce\Catalogo;
use App\Livewire\Ecommerce\Checkout;
use App\Livewire\Ecommerce\DetalleProducto;
use App\Livewire\Ecommerce\Inicio;
use App\Livewire\Ecommerce\LibroReclamaciones;
use App\Livewire\Ecommerce\MiCuenta;
use App\Livewire\Ecommerce\PaginaInformativa;
use App\Livewire\Ecommerce\SeguimientoPedido;
use App\Livewire\Pos\Venta as PosVenta;
use Illuminate\Support\Facades\Route;

// Tienda Online Pública
Route::get('/', Inicio::class)->name('inicio');
Route::get('/catalogo', Catalogo::class)->name('catalogo');
Route::get('/producto/{slug}', DetalleProducto::class)->name('producto.detalle');
Route::get('/carrito', CarritoPagina::class)->name('carrito');
Route::get('/checkout', Checkout::class)->name('checkout');
Route::get('/pedido/{codigo}', SeguimientoPedido::class)->name('pedido.seguimiento');
Route::get('/libro-reclamaciones', LibroReclamaciones::class)->name('libro.reclamaciones');
Route::get('/pagina/{slug}', PaginaInformativa::class)->name('pagina.informativa');

// SEO
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

// Webhooks de Pasarelas (Excluidos de CSRF en bootstrap/app.php)
Route::post('/webhooks/culqi', [WebhookCulqiController::class, 'handle'])->name('webhooks.culqi');

// Autenticación de Clientes Web (guard cliente)
Route::middleware('guest:cliente')->group(function () {
    Route::get('/cliente/login', AuthCliente::class)->name('cliente.login');
});
Route::post('/cliente/logout', [AuthCliente::class, 'logout'])
    ->middleware('auth:cliente')
    ->name('cliente.logout');
Route::get('/mi-cuenta', MiCuenta::class)
    ->middleware('auth:cliente')
    ->name('mi-cuenta');

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

// Consulta pública de garantía (sin login, con rate limit de 5 req/min por IP)
Route::match(['get', 'post'], '/garantia', [ConsultaGarantiaPublicaController::class, 'index'])
    ->middleware('throttle:5,1')
    ->name('garantia.publica');

// Documentos imprimibles protegidos (autenticados)
Route::middleware('auth')->group(function () {
    Route::get('/devoluciones/{devolucion}/imprimir-a4', [DocumentosImprimiblesController::class, 'devolucionA4'])->name('devoluciones.imprimir_a4');
    Route::get('/devoluciones/{devolucion}/imprimir-ticket', [DocumentosImprimiblesController::class, 'devolucionTicket'])->name('devoluciones.imprimir_ticket');
    Route::get('/servicio/{orden}/imprimir-ingreso', [DocumentosImprimiblesController::class, 'servicioIngresoA4'])->name('servicio.imprimir_ingreso');
    Route::get('/servicio/{orden}/imprimir-cotizacion', [DocumentosImprimiblesController::class, 'servicioCotizacionA4'])->name('servicio.imprimir_cotizacion');
    Route::get('/servicio/{orden}/imprimir-acta', [DocumentosImprimiblesController::class, 'servicioActaEntregaA4'])->name('servicio.imprimir_acta');
});
