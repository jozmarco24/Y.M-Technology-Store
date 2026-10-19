<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-2xl font-extrabold text-gray-900 mb-8">Tu Carrito de Compras</h1>

    @if($totales['cambio_precio'])
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-3">
            <span class="text-xl">⚠️</span>
            <span>Algunos productos en tu carrito han actualizado sus precios vigentes. Los totales han sido recalculados automáticamente.</span>
        </div>
    @endif

    @if($totales['cambio_stock'])
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-3">
            <span class="text-xl">🚨</span>
            <span>La disponibilidad de algunos productos cambió debido a compras recientes. Las cantidades fueron ajustadas al stock real.</span>
        </div>
    @endif

    @if($carrito && $carrito->items->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LISTADO DE ITEMS -->
            <div class="lg:col-span-2 space-y-4">
                @foreach($carrito->items as $item)
                    <div class="bg-white p-5 rounded-3xl border border-gray-200 flex flex-col sm:flex-row items-center gap-5 justify-between">
                        <div class="flex items-center gap-4 w-full sm:w-auto">
                            <div class="w-20 h-20 bg-gray-50 rounded-2xl overflow-hidden shrink-0 flex items-center justify-center p-2 border border-gray-100">
                                @if($item->producto?->imagen)
                                    <img src="{{ asset('storage/' . $item->producto->imagen) }}" alt="{{ $item->producto->nombre }}" class="max-h-full max-w-full object-contain">
                                @else
                                    <span class="text-xs text-gray-400">Sin foto</span>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                                    {{ $item->producto?->marca?->nombre ?? 'Marca' }}
                                </span>
                                <h3 class="text-sm font-bold text-gray-900 truncate max-w-xs">{{ $item->producto?->nombre }}</h3>
                                <p class="text-xs text-gray-500 mt-1">Precio unitario: S/ {{ number_format((float) $item->precio_unitario, 2) }}</p>
                            </div>
                        </div>

                        <!-- Selector de Cantidad y Subtotal -->
                        <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto pt-3 sm:pt-0 border-t sm:border-0 border-gray-100">
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                                <button wire:click="cambiarCantidad({{ $item->id }}, {{ $item->cantidad - 1 }})" class="px-3 py-1.5 text-gray-600 hover:bg-gray-200 font-bold text-xs">-</button>
                                <span class="px-3 py-1.5 text-xs font-bold text-gray-900">{{ $item->cantidad }}</span>
                                <button wire:click="cambiarCantidad({{ $item->id }}, {{ $item->cantidad + 1 }})" class="px-3 py-1.5 text-gray-600 hover:bg-gray-200 font-bold text-xs">+</button>
                            </div>

                            <div class="text-right">
                                <span class="text-sm font-extrabold text-blue-600 block">
                                    S/ {{ number_format((float) $item->subtotal(), 2) }}
                                </span>
                            </div>

                            <button wire:click="quitarItem({{ $item->id }})" class="text-gray-400 hover:text-rose-600 p-1" title="Quitar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- RESUMEN DE COMPRA, CUPÓN Y ESTIMACIÓN DE ENVÍO -->
            <div class="bg-white p-6 rounded-3xl border border-gray-200 h-fit space-y-6">
                <h2 class="text-base font-extrabold text-gray-900">Resumen del Pedido</h2>

                <!-- Cupón de Descuento -->
                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1.5">¿Tienes un cupón?</label>
                    <div class="flex gap-2">
                        <input type="text" 
                               wire:model="codigoCupon" 
                               placeholder="Ej: PROMO10" 
                               class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 outline-none uppercase font-bold focus:border-blue-500">
                        <button wire:click="aplicarCupon" class="bg-gray-900 hover:bg-black text-white text-xs font-bold px-4 py-2.5 rounded-xl transition">
                            Aplicar
                        </button>
                    </div>
                    @if($mensajeCupon)
                        <p class="text-[11px] mt-1.5 {{ $descuentoCupon > 0 ? 'text-emerald-600' : 'text-rose-500' }} font-semibold">
                            {{ $mensajeCupon }}
                        </p>
                    @endif
                </div>

                <!-- Estimación de Envío -->
                <div class="pt-4 border-t border-gray-100">
                    <label class="text-xs font-bold text-gray-700 block mb-1.5">Método de entrega preferido</label>
                    <select wire:model.live="metodoEntrega" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none mb-3">
                        <option value="recojo_tienda">Recojo en Tienda (Gratis - Wilson)</option>
                        <option value="delivery_lima">Delivery en Lima Metropolitana</option>
                        <option value="envio_provincia">Envío a Provincias (Olva / Shalom)</option>
                    </select>

                    @if($metodoEntrega !== 'recojo_tienda')
                        <label class="text-[11px] text-gray-500 block mb-1">Zona o Departamento</label>
                        <select wire:model.live="zonaEnvioId" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                            <option value="">Selecciona tu zona...</option>
                            @foreach($zonasEnvio as $z)
                                <option value="{{ $z->id }}">{{ $z->nombre }} (S/ {{ number_format((float) $z->costo, 2) }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <!-- Desglose de Totales -->
                <div class="pt-4 border-t border-gray-100 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal Productos</span>
                        <span class="font-bold text-gray-900">S/ {{ number_format((float) $subtotalProductos, 2) }}</span>
                    </div>

                    @if($descuentoCupon > 0)
                        <div class="flex justify-between text-emerald-600 font-bold">
                            <span>Descuento Cupón</span>
                            <span>- S/ {{ number_format((float) $descuentoCupon, 2) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-gray-600">
                        <span>Costo de Envío</span>
                        <span class="font-bold text-gray-900">{{ $costoEnvio > 0 ? 'S/ ' . number_format($costoEnvio, 2) : 'GRATIS' }}</span>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex justify-between items-center text-sm">
                        <span class="font-extrabold text-gray-900">Total a Pagar</span>
                        <span class="text-xl font-extrabold text-blue-600">S/ {{ number_format($totalFinal, 2) }}</span>
                    </div>
                </div>

                <!-- Botón de Checkout -->
                <a href="{{ route('checkout') }}" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-2xl text-sm transition shadow-lg shadow-blue-500/25">
                    Continuar al Checkout
                </a>
            </div>
        </div>
    @else
        <!-- Carrito Vacío -->
        <div class="bg-white rounded-3xl p-16 text-center border border-gray-200 max-w-lg mx-auto">
            <span class="text-6xl block mb-4">🛒</span>
            <h2 class="text-lg font-extrabold text-gray-900 mb-2">Tu carrito está actualmente vacío</h2>
            <p class="text-xs text-gray-500 mb-8 leading-relaxed">
                Revisa nuestro catálogo online y añade los mejores componentes, periféricos o laptops a tu orden.
            </p>
            <a href="{{ route('catalogo') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-md">
                Ver Catálogo de Productos
            </a>
        </div>
    @endif
</div>
