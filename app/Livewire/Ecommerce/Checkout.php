<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Enums\Pasarela;
use App\Models\Carrito;
use App\Models\ClienteWeb;
use App\Models\DireccionCliente;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Models\ZonaEnvio;
use App\Services\CarritoService;
use App\Services\CuponService;
use App\Services\EnvioService;
use App\Services\PagoOnlineService;
use App\Services\PedidoService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Component;

class Checkout extends Component
{
    public int $paso = 1;

    // Paso 1: Contacto y Comprobante
    public string $email = '';

    public string $telefono = '';

    public string $tipoComprobante = 'boleta'; // boleta o factura

    public string $documentoTipo = 'DNI'; // DNI, RUC, CE

    public string $documentoNumero = '';

    public string $razonSocial = '';

    public string $direccionFiscal = '';

    // Paso 2: Entrega
    public string $metodoEntrega = 'recojo_tienda';

    public ?int $direccionId = null;

    public string $departamento = 'LIMA';

    public string $provincia = 'LIMA';

    public string $distrito = '';

    public string $direccion = '';

    public string $referencia = '';

    public string $destinatario = '';

    public ?int $zonaEnvioId = null;

    // Paso 3: Pago
    public string $pasarela = 'simulada'; // simulada, culqi, niubiz, yape_manual, transferencia

    public string $tokenTarjeta = '';

    // Totales y Cupon
    public string $codigoCupon = '';

    public float $descuentoCupon = 0.00;

    public float $costoEnvio = 0.00;

    public bool $aceptaTerminos = false;

    public ?Pedido $pedidoCreado = null;

    public ?int $tiempoRestanteSegundos = null;

    public ?string $errorCheckout = null;

    public ?Carrito $carrito = null;

    /** @var Collection<int, ZonaEnvio> */
    public Collection $zonasEnvio;

    /** @var Collection<int, DireccionCliente> */
    public Collection $direccionesGuardadas;

    public function mount(CarritoService $carritoService, EnvioService $envioService): void
    {
        $this->zonasEnvio = ZonaEnvio::where('activo', true)->get();
        $this->direccionesGuardadas = collect();

        $cliente = auth('cliente')->user();
        if ($cliente instanceof ClienteWeb) {
            $this->email = $cliente->email;
            $this->destinatario = $cliente->nombre;
            $this->telefono = $cliente->telefono ?? '';
            $this->direccionesGuardadas = $cliente->direcciones()->get();

            $principal = $this->direccionesGuardadas->firstWhere('es_principal', true);
            if ($principal) {
                $this->seleccionarDireccion($principal->id);
            }
        }

        $token = (string) Cookie::get('carrito_token');
        $this->carrito = $carritoService->obtenerOCrear($cliente, $token ?: null);

        if ($this->carrito->items->isEmpty()) {
            $this->redirect(route('carrito'));

            return;
        }

        $this->recalcularEnvio($envioService);
    }

    public function seleccionarDireccion(int $id): void
    {
        $dir = $this->direccionesGuardadas->firstWhere('id', $id);
        if ($dir) {
            $this->direccionId = $dir->id;
            $this->destinatario = $dir->destinatario;
            $this->telefono = $dir->telefono;
            $this->departamento = $dir->departamento;
            $this->provincia = $dir->provincia;
            $this->distrito = $dir->distrito;
            $this->direccion = $dir->direccion;
            $this->referencia = $dir->referencia ?? '';
            $this->recalcularEnvio(app(EnvioService::class));
        }
    }

    public function avanzarPaso(): void
    {
        $this->errorCheckout = null;

        if ($this->paso === 1) {
            $this->validate([
                'email' => 'required|email',
                'telefono' => 'required|string|min:6',
                'tipoComprobante' => 'required|in:boleta,factura',
                'documentoNumero' => 'required|string',
            ]);

            if ($this->tipoComprobante === 'factura') {
                if (strlen($this->documentoNumero) !== 11 || ! str_starts_with($this->documentoNumero, '10') && ! str_starts_with($this->documentoNumero, '20')) {
                    $this->addError('documentoNumero', 'Para Factura se requiere un RUC válido de 11 dígitos.');

                    return;
                }
                $this->validate([
                    'razonSocial' => 'required|string|min:3',
                    'direccionFiscal' => 'required|string|min:5',
                ]);
            }

            $this->paso = 2;

            return;
        }

        if ($this->paso === 2) {
            if ($this->metodoEntrega !== 'recojo_tienda') {
                $this->validate([
                    'destinatario' => 'required|string',
                    'distrito' => 'required|string',
                    'direccion' => 'required|string',
                ]);
            }
            $this->paso = 3;

            return;
        }

        if ($this->paso === 3) {
            $this->paso = 4;
        }
    }

    public function retrocederPaso(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    public function updatedMetodoEntrega(): void
    {
        $this->recalcularEnvio(app(EnvioService::class));
    }

    public function updatedZonaEnvioId(): void
    {
        $this->recalcularEnvio(app(EnvioService::class));
    }

    public function recalcularEnvio(EnvioService $envioService): void
    {
        $cotizacion = $envioService->cotizar($this->metodoEntrega, $this->zonaEnvioId);
        $this->costoEnvio = (float) $cotizacion['costo'];
    }

    public function aplicarCupon(CuponService $cuponService, CarritoService $carritoService): void
    {
        if (! $this->carrito || empty(trim($this->codigoCupon))) {
            return;
        }

        $totales = $carritoService->calcularTotalesYValidar($this->carrito);
        $res = $cuponService->validar($this->codigoCupon, $totales['total']);

        if ($res['valido']) {
            $this->descuentoCupon = (float) $res['descuento'];
            $this->dispatch('notificar', ['tipo' => 'success', 'mensaje' => 'Cupón aplicado con éxito.']);
        } else {
            $this->descuentoCupon = 0.00;
            $this->dispatch('notificar', ['tipo' => 'warning', 'mensaje' => $res['mensaje']]);
        }
    }

    public function procesarPedido(PedidoService $pedidoService, PagoOnlineService $pagoOnlineService): void
    {
        $this->errorCheckout = null;

        if (! $this->aceptaTerminos) {
            $this->errorCheckout = 'Debes aceptar los términos y condiciones para continuar.';

            return;
        }

        if (! $this->carrito || $this->carrito->items->isEmpty()) {
            $this->errorCheckout = 'El carrito está vacío.';

            return;
        }

        try {
            $clienteWeb = auth('cliente')->user();

            $datosPedido = [
                'cliente_web_id' => $clienteWeb?->id,
                'metodo_entrega' => $this->metodoEntrega,
                'zona_envio_id' => $this->zonaEnvioId,
                'direccion_snapshot' => [
                    'destinatario' => $this->destinatario ?: $clienteWeb?->nombre,
                    'telefono' => $this->telefono,
                    'departamento' => $this->departamento,
                    'provincia' => $this->provincia,
                    'distrito' => $this->distrito,
                    'direccion' => $this->direccion,
                    'referencia' => $this->referencia,
                ],
                'codigo_cupon' => $this->codigoCupon ?: null,
                'tipo_comprobante' => $this->tipoComprobante,
                'documento_tipo' => $this->documentoTipo,
                'documento_numero' => $this->documentoNumero,
                'razon_social' => $this->razonSocial ?: null,
                'direccion_fiscal' => $this->direccionFiscal ?: null,
                'email_contacto' => $this->email,
                'telefono_contacto' => $this->telefono,
            ];

            // 1. Crear Pedido y reservar series
            $this->pedidoCreado = $pedidoService->crearDesdeCarrito($this->carrito, $datosPedido);

            // 2. Iniciar Pasarela de Pago
            $pasarelaEnum = Pasarela::tryFrom($this->pasarela) ?? Pasarela::SIMULADA;
            $inicioPago = $pagoOnlineService->iniciar($this->pedidoCreado, $pasarelaEnum);
            $pago = PagoOnline::find($inicioPago['pago_id']);

            // Si es pasarela simulada o modo de prueba inmediato:
            if ($pasarelaEnum === Pasarela::SIMULADA) {
                $pagoOnlineService->confirmar($pago, ['token' => 'tok_simulado_ok', 'referencia' => 'OP-SIMULADA-'.Str::random(6)]);
                $this->redirect(route('pedido.seguimiento', ['codigo' => $this->pedidoCreado->codigo]));

                return;
            }

            // Para transferencias o yape manual:
            if (in_array($pasarelaEnum, [Pasarela::YAPE_MANUAL, Pasarela::TRANSFERENCIA], true)) {
                $this->redirect(route('pedido.seguimiento', ['codigo' => $this->pedidoCreado->codigo]));

                return;
            }

            // Si es pasarela externa (Culqi / Niubiz), redirección o retorno a seguimiento
            $this->redirect(route('pedido.seguimiento', ['codigo' => $this->pedidoCreado->codigo]));
        } catch (Exception $e) {
            $this->errorCheckout = $e->getMessage();
        }
    }

    public function render(CarritoService $carritoService): View
    {
        $totales = $this->carrito ? $carritoService->calcularTotalesYValidar($this->carrito) : [
            'subtotal' => 0.00,
            'total' => 0.00,
        ];

        $subtotal = $totales['total'];
        $totalFinal = max(0.00, $subtotal - $this->descuentoCupon + $this->costoEnvio);

        return view('livewire.ecommerce.checkout', [
            'totales' => $totales,
            'subtotal' => $subtotal,
            'totalFinal' => $totalFinal,
        ])->layout('layouts.ecommerce', [
            'metaTitle' => 'Finalizar Compra | Checkout Seguro | SYS-COPM',
            'metaDescription' => 'Completa tus datos de envío y pago de forma rápida y 100% segura.',
        ]);
    }
}
