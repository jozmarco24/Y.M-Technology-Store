<div class="h-screen w-screen flex flex-col bg-slate-950 text-slate-100 overflow-hidden"
     x-data="{
        initShortcuts() {
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    document.getElementById('pos-search-input')?.focus();
                } else if (e.key === 'F4') {
                    e.preventDefault();
                    $wire.abrirModalCobro();
                } else if (e.key === 'Escape') {
                    $wire.set('mostrarModalCobro', false);
                    $wire.set('mostrarModalSeries', false);
                    $wire.set('mostrarModalNuevoCliente', false);
                    $wire.set('mostrarModalMovimiento', false);
                    $wire.set('mostrarModalCierreCaja', false);
                    $wire.set('mostrarModalHistorial', false);
                }
            });
        }
     }"
     x-init="initShortcuts()">

    <!-- BARRA SUPERIOR (HEADER) -->
    <header class="h-14 bg-slate-900 border-b border-slate-800 px-4 flex items-center justify-between shrink-0 shadow-md">
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full {{ $cajaSesion ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' }}"></span>
                <span class="font-bold tracking-wider text-emerald-400 text-sm md:text-base">SYS-COPM POS</span>
            </div>

            <div class="hidden sm:flex items-center space-x-2 text-xs text-slate-400 border-l border-slate-800 pl-4">
                <span>Cajero:</span>
                <span class="font-semibold text-slate-200">{{ auth()->user()->name }}</span>
            </div>

            @if ($cajaSesion)
                <div class="hidden md:flex items-center space-x-2 text-xs bg-slate-800/80 px-2.5 py-1 rounded-md border border-slate-700/60">
                    <span class="text-slate-400">Caja #{{ $cajaSesion->id }}</span>
                    <span class="text-slate-500">•</span>
                    <span class="text-emerald-400 font-mono">Apertura: S/ {{ number_format((float)$cajaSesion->monto_apertura, 2) }}</span>
                </div>
            @endif
        </div>

        <!-- ACCIONES DE CAJA Y NAVEGACIÓN -->
        <div class="flex items-center space-x-2">
            @if ($cajaSesion)
                <button wire:click="abrirModalMovimiento"
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Movimiento Caja</span>
                </button>

                <button wire:click="abrirModalCierre"
                    class="px-3 py-1.5 bg-rose-950/60 hover:bg-rose-900/80 text-rose-300 text-xs font-medium rounded-lg border border-rose-800/60 transition flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Cerrar Caja</span>
                </button>
            @else
                <button wire:click="$set('mostrarModalAbrirCaja', true)"
                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow transition flex items-center space-x-1.5">
                    <span>Abrir Sesión de Caja</span>
                </button>
            @endif

            <button wire:click="abrirModalGarantia"
                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg border border-slate-700 transition flex items-center space-x-1">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Garantías</span>
            </button>

            <button wire:click="abrirModalServicio"
                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg border border-slate-700 transition flex items-center space-x-1">
                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                <span>Servicio Técnico</span>
            </button>

            <button wire:click="$set('mostrarModalHistorial', true)"
                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg border border-slate-700 transition flex items-center space-x-1">
                <svg class="w-3.5 h-3.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Mis Ventas</span>
            </button>

            @if (auth()->user()->hasRole('admin'))
                <a href="/admin" class="px-2.5 py-1.5 bg-indigo-950 hover:bg-indigo-900 text-indigo-300 text-xs rounded-lg border border-indigo-800 transition">
                    Admin
                </a>
            @endif

            <form method="POST" action="{{ route('pos.logout') }}">
                @csrf
                <button type="submit" title="Cerrar sesión" class="p-1.5 text-slate-400 hover:text-rose-400 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </header>

    <!-- ALERTAS FLASH LOCALES -->
    @if ($mensajeError)
        <div class="bg-rose-900/90 border-b border-rose-700 text-rose-100 px-4 py-2 text-sm flex items-center justify-between z-50">
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 shrink-0 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-medium">{{ $mensajeError }}</span>
            </div>
            <button wire:click="limpiarMensajes" class="text-rose-300 hover:text-white">&times;</button>
        </div>
    @endif
    @if ($mensajeExito)
        <div class="bg-emerald-900/90 border-b border-emerald-700 text-emerald-100 px-4 py-2 text-sm flex items-center justify-between z-50">
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 shrink-0 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="font-medium">{{ $mensajeExito }}</span>
            </div>
            <button wire:click="limpiarMensajes" class="text-emerald-300 hover:text-white">&times;</button>
        </div>
    @endif

    <!-- CONTENEDOR PRINCIPAL: 2 COLUMNAS (IZQ: CATÁLOGO Y BUSCADOR | DER: CARRITO Y TOTALES) -->
    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">

        <!-- ================================================================= -->
        <!-- COLUMNA IZQUIERDA: BUSCADOR & CATÁLOGO -->
        <!-- ================================================================= -->
        <div class="w-full md:w-7/12 lg:w-3/5 flex flex-col border-r border-slate-800 bg-slate-900/40 overflow-hidden">
            
            <!-- BUSCADOR PRINCIPAL (F2) -->
            <div class="p-3 bg-slate-900 border-b border-slate-800">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input id="pos-search-input"
                           type="text"
                           wire:model.live.debounce.300ms="busqueda"
                           wire:keydown.enter="procesarBusquedaEnter"
                           placeholder="Escanear serie, SKU o buscar por nombre... [F2] [Enter]"
                           autofocus
                           class="w-full pl-11 pr-24 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 font-medium text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center space-x-1">
                        <span class="hidden sm:inline-block px-2 py-0.5 text-[10px] font-mono bg-slate-800 border border-slate-700 text-slate-400 rounded">Enter</span>
                        <span class="hidden sm:inline-block px-2 py-0.5 text-[10px] font-mono bg-slate-800 border border-slate-700 text-slate-400 rounded">F2</span>
                    </div>
                </div>

                <!-- FILTRO RÁPIDO DE CATEGORÍAS -->
                <div class="flex items-center space-x-2 overflow-x-auto pt-2.5 pb-1 no-scrollbar text-xs">
                    <button wire:click="$set('categoriaSeleccionada', null)"
                            class="px-3 py-1.5 rounded-lg whitespace-nowrap transition font-medium {{ $categoriaSeleccionada === null ? 'bg-emerald-600 text-white shadow' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                        Todas las Categorías
                    </button>
                    @foreach ($categorias as $cat)
                        <button wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                class="px-3 py-1.5 rounded-lg whitespace-nowrap transition font-medium {{ $categoriaSeleccionada === $cat->id ? 'bg-emerald-600 text-white shadow' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                            {{ $cat->nombre }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- GRILLA DE PRODUCTOS -->
            <div class="flex-1 overflow-y-auto p-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @forelse ($productos as $prod)
                        @php
                            $stock = app(\App\Services\InventarioService::class)->stockDisponible($prod);
                        @endphp
                        <div wire:click="seleccionarProducto({{ $prod->id }})"
                             class="bg-slate-900 border border-slate-800 hover:border-emerald-500/60 rounded-xl p-3 flex flex-col justify-between cursor-pointer transition transform active:scale-95 group relative shadow-sm hover:shadow-emerald-950/20">
                            
                            <div>
                                <div class="flex items-center justify-between text-[11px] mb-1.5">
                                    <span class="font-mono text-slate-400 truncate max-w-[65%]">{{ $prod->sku }}</span>
                                    @if ($prod->controla_series)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-950/70 text-purple-300 border border-purple-800/60">SERIES</span>
                                    @endif
                                </div>
                                <h3 class="font-semibold text-xs text-slate-200 group-hover:text-emerald-400 line-clamp-2 transition leading-tight mb-2">
                                    {{ $prod->nombre }}
                                </h3>
                            </div>

                            <div class="pt-2 border-t border-slate-800/80 flex items-end justify-between">
                                <div>
                                    <span class="text-[10px] text-slate-500 block leading-none">Precio</span>
                                    <span class="text-sm font-bold text-white font-mono">S/ {{ number_format((float)$prod->precio_venta, 2) }}</span>
                                </div>
                                <span class="text-[11px] font-medium {{ $stock > 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $stock }} disp.
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            <p class="text-sm">No se encontraron productos con el criterio de búsqueda.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $productos->links() }}
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- COLUMNA DERECHA: CARRITO & TOTALES -->
        <!-- ================================================================= -->
        <div class="w-full md:w-5/12 lg:w-2/5 flex flex-col bg-slate-900 overflow-hidden shadow-2xl">
            
            <!-- SELECTORES DE CABECERA: CLIENTE Y COMPROBANTE -->
            <div class="p-3 bg-slate-900 border-b border-slate-800 space-y-2.5">
                <!-- CLIENTE -->
                <div class="flex items-center space-x-2">
                    <div class="flex-1 relative">
                        <select wire:model.live="clienteId"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs font-medium text-slate-200 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            @foreach ($clientesList as $cli)
                                <option value="{{ $cli->id }}">
                                    {{ $cli->nombre_razon_social }} ({{ $cli->tipo_documento->value }}: {{ $cli->numero_documento ?? 'S/N' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="$set('mostrarModalNuevoCliente', true)"
                            title="Nuevo Cliente Rápido"
                            class="p-2 bg-slate-800 hover:bg-slate-700 text-emerald-400 border border-slate-700 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </button>
                    <button wire:click="seleccionarClientesVarios"
                            title="Clientes Varios"
                            class="px-2.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 rounded-lg text-xs font-mono transition">
                        C/V
                    </button>
                </div>

                <!-- TIPO DE COMPROBANTE (BOLETA / FACTURA / NOTA DE VENTA) -->
                <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-950 rounded-lg border border-slate-800 text-xs font-medium">
                    <button wire:click="$set('tipoComprobante', 'boleta')"
                            class="py-1.5 rounded-md transition text-center {{ $tipoComprobante === 'boleta' ? 'bg-emerald-600 text-white font-bold shadow' : 'text-slate-400 hover:text-slate-200' }}">
                        BOLETA
                    </button>
                    <button wire:click="$set('tipoComprobante', 'factura')"
                            class="py-1.5 rounded-md transition text-center {{ $tipoComprobante === 'factura' ? 'bg-emerald-600 text-white font-bold shadow' : 'text-slate-400 hover:text-slate-200' }}">
                        FACTURA
                    </button>
                    <button wire:click="$set('tipoComprobante', 'nota_venta')"
                            class="py-1.5 rounded-md transition text-center {{ $tipoComprobante === 'nota_venta' ? 'bg-emerald-600 text-white font-bold shadow' : 'text-slate-400 hover:text-slate-200' }}">
                        NOTA VENTA
                    </button>
                </div>

                <!-- ALERTA SI FACTURA Y NO ES RUC -->
                @if ($tipoComprobante === 'factura' && $clienteActual && $clienteActual->tipo_documento->value !== 'RUC')
                    <div class="text-[11px] bg-amber-950/70 border border-amber-800/80 text-amber-300 p-2 rounded-lg flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>La Factura exige un cliente con RUC válido.</span>
                    </div>
                @endif
            </div>

            <!-- LISTA DE LÍNEAS DEL CARRITO -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                @forelse ($carrito as $index => $item)
                    <div class="bg-slate-950 border border-slate-800 rounded-xl p-2.5 flex items-center justify-between text-xs space-x-2">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-200 truncate">{{ $item['producto_nombre'] }}</div>
                            <div class="flex items-center space-x-2 text-[10px] text-slate-400 mt-0.5">
                                @if ($item['controla_series'])
                                    <span class="text-purple-400 font-mono font-semibold">SN: {{ $item['serie_numero'] }}</span>
                                @else
                                    <span>S/ {{ number_format((float)$item['precio_unitario'], 2) }} c/u</span>
                                @endif
                                @if ((float)$item['descuento'] > 0)
                                    <span class="text-amber-400 font-mono">(-S/ {{ number_format((float)$item['descuento'], 2) }})</span>
                                @endif
                            </div>
                        </div>

                        <!-- CONTROL DE CANTIDAD O INDIQUE SERIE -->
                        <div class="flex items-center space-x-1 shrink-0">
                            @if (! $item['controla_series'])
                                <button wire:click="actualizarCantidad({{ $index }}, -1)"
                                        class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold">
                                    -
                                </button>
                                <span class="w-7 text-center font-mono font-semibold text-white">{{ $item['cantidad'] }}</span>
                                <button wire:click="actualizarCantidad({{ $index }}, 1)"
                                        class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold">
                                    +
                                </button>
                            @else
                                <span class="px-2 py-0.5 bg-slate-800 text-slate-300 rounded font-mono text-[10px]">1 ud</span>
                            @endif
                        </div>

                        <!-- SUBTOTAL LÍNEA -->
                        <div class="text-right shrink-0 w-20">
                            <span class="font-bold text-white font-mono text-xs block">S/ {{ number_format((float)$item['subtotal'], 2) }}</span>
                            <button wire:click="quitarLinea({{ $index }})" class="text-[10px] text-rose-400 hover:text-rose-300 hover:underline">
                                Quitar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-slate-600 text-center py-16">
                        <svg class="w-14 h-14 mb-2 text-slate-700 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <p class="text-sm font-medium">El carrito está vacío</p>
                        <p class="text-xs text-slate-500 mt-1">Escanee un código de barras o seleccione productos</p>
                    </div>
                @endforelse
            </div>

            <!-- PANEL DE TOTALES Y BOTÓN DE COBRO -->
            <div class="p-3 bg-slate-950 border-t border-slate-800 space-y-2">
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal (sin IGV):</span>
                        <span class="font-mono">S/ {{ number_format($this->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>I.G.V. (18%):</span>
                        <span class="font-mono">S/ {{ number_format($this->igv, 2) }}</span>
                    </div>
                    @if ($this->descuentoTotal > 0)
                        <div class="flex justify-between text-amber-400">
                            <span>Descuentos aplicados:</span>
                            <span class="font-mono">- S/ {{ number_format($this->descuentoTotal, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-white font-bold text-lg pt-1 border-t border-slate-800">
                        <span>TOTAL A COBRAR:</span>
                        <span class="font-mono text-emerald-400">S/ {{ number_format($this->total, 2) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-2 pt-1">
                    <button wire:click="limpiarCarrito"
                            @disabled(empty($carrito))
                            class="col-span-1 py-3 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-slate-300 font-semibold text-xs rounded-xl transition">
                        Limpiar
                    </button>
                    <button wire:click="abrirModalCobro"
                            @disabled(empty($carrito))
                            class="col-span-3 py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-bold text-sm md:text-base rounded-xl shadow-lg shadow-emerald-950/40 transition flex items-center justify-center space-x-2">
                        <span>Cobrar S/ {{ number_format($this->total, 2) }}</span>
                        <span class="text-xs font-mono opacity-80">[F4]</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 1: ABRIR CAJA -->
    <!-- ===================================================================== -->
    @if ($mostrarModalAbrirCaja)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6">
                <div class="text-center mb-6">
                    <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-xl flex items-center justify-center mx-auto mb-3 ring-1 ring-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">Apertura de Sesión de Caja</h2>
                    <p class="text-xs text-slate-400 mt-1">Ingrese el monto inicial en efectivo para iniciar el turno de ventas</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Monto de Apertura (S/)</label>
                        <input type="number" step="0.01" wire:model="montoAperturaInput"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white font-mono text-xl text-center focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Observaciones (Opcional)</label>
                        <input type="text" wire:model="observacionesApertura" placeholder="Ej. Inicio de turno mañana"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-white text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <button wire:click="abrirCaja"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg transition">
                        Confirmar y Abrir Caja
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 2: REGISTRO DE MOVIMIENTO DE CAJA -->
    <!-- ===================================================================== -->
    @if ($mostrarModalMovimiento)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-white">Movimiento Manual de Caja</h2>
                    <button wire:click="$set('mostrarModalMovimiento', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Tipo de Movimiento</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="$set('tipoMovimientoInput', 'ingreso')"
                                    class="py-2 rounded-lg font-bold transition {{ $tipoMovimientoInput === 'ingreso' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                                INGRESO (+)
                            </button>
                            <button wire:click="$set('tipoMovimientoInput', 'egreso')"
                                    class="py-2 rounded-lg font-bold transition {{ $tipoMovimientoInput === 'egreso' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                                EGRESO (-)
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Concepto / Motivo</label>
                        <input type="text" wire:model="conceptoMovimientoInput" placeholder="Ej. Pago de fotocopias, cambio de sencillo"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Monto (S/)</label>
                        <input type="number" step="0.01" wire:model="montoMovimientoInput" placeholder="0.00"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white font-mono text-base">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Método de Pago</label>
                        <select wire:model="metodoPagoMovimientoInput" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                            <option value="efectivo">Efectivo</option>
                            <option value="yape">Yape</option>
                            <option value="plin">Plin</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>

                    <div class="pt-2 flex space-x-2">
                        <button wire:click="$set('mostrarModalMovimiento', false)" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl">
                            Cancelar
                        </button>
                        <button wire:click="registrarMovimientoCaja" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl">
                            Registrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 3: CIERRE DE CAJA -->
    <!-- ===================================================================== -->
    @if ($mostrarModalCierreCaja && $resumenCierre)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-white">Cierre y Arqueo de Caja #{{ $resumenCierre['sesion_id'] }}</h2>
                    <button wire:click="$set('mostrarModalCierreCaja', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <!-- RESUMEN DE SALDOS -->
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5 font-mono">
                        <div class="flex justify-between text-slate-400">
                            <span>Apertura inicial:</span>
                            <span>S/ {{ number_format((float)$resumenCierre['monto_apertura'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-400">
                            <span>(+) Total Ingresos:</span>
                            <span>S/ {{ number_format((float)$resumenCierre['total_ingresos'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-rose-400">
                            <span>(-) Total Egresos:</span>
                            <span>S/ {{ number_format((float)$resumenCierre['total_egresos'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-white font-bold pt-1 border-t border-slate-800">
                            <span>MONTO ESPERADO EN EFECTIVO:</span>
                            <span>S/ {{ number_format((float)$resumenCierre['monto_cierre_esperado'], 2) }}</span>
                        </div>
                    </div>

                    <!-- INPUT MONTO CONTADO REAL -->
                    <div>
                        <label class="block font-semibold text-slate-200 mb-1 text-sm">Monto Contado Real en Efectivo (S/)</label>
                        <input type="number" step="0.01" wire:model.live="montoCierreRealInput"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white font-mono text-xl text-center focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <!-- CÁLCULO DE DIFERENCIA EN VIVO -->
                    @php
                        $esperado = (float)$resumenCierre['monto_cierre_esperado'];
                        $real = (float)$montoCierreRealInput;
                        $dif = round($real - $esperado, 2);
                    @endphp
                    <div class="p-3 rounded-xl border {{ $dif == 0 ? 'bg-emerald-950/40 border-emerald-800 text-emerald-300' : ($dif < 0 ? 'bg-rose-950/60 border-rose-800 text-rose-300' : 'bg-amber-950/40 border-amber-800 text-amber-300') }} flex items-center justify-between font-mono">
                        <span>Diferencia de Cuadre:</span>
                        <span class="font-bold text-sm">
                            {{ $dif >= 0 ? '+ S/ '.number_format($dif, 2) : '- S/ '.number_format(abs($dif), 2) }}
                            {{ $dif == 0 ? '(Exacto)' : ($dif < 0 ? '(Faltante)' : '(Sobrante)') }}
                        </span>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Observaciones de Cierre</label>
                        <input type="text" wire:model="observacionesCierre" placeholder="Ej. Cuadre final turno tarde"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-white">
                    </div>

                    <div class="pt-2 flex space-x-2">
                        <button wire:click="$set('mostrarModalCierreCaja', false)" class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium">
                            Cancelar
                        </button>
                        <button wire:click="cerrarCaja" class="flex-1 py-3 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-xl shadow-lg">
                            Confirmar Cierre de Caja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 4: SELECTOR DE SERIES DISPONIBLES -->
    <!-- ===================================================================== -->
    @if ($mostrarModalSeries)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 flex flex-col max-h-[85vh]">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="text-base font-bold text-white">Seleccionar Número de Serie</h2>
                        <p class="text-xs text-slate-400">Escoja una unidad disponible para agregar al carrito</p>
                    </div>
                    <button wire:click="$set('mostrarModalSeries', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="mb-3">
                    <input type="text" wire:model.live.debounce.250ms="busquedaSerie" placeholder="Filtrar por serie..."
                           class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white">
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                    @forelse ($seriesDisponibles as $s)
                        <div wire:click="agregarSerieAlCarrito({{ $s->id }})"
                             class="p-2.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-emerald-500 rounded-xl flex items-center justify-between cursor-pointer transition">
                            <span class="font-mono text-sm text-purple-300 font-bold">{{ $s->numero_serie }}</span>
                            <span class="text-xs px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-800">Disponible</span>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-500 text-xs">
                            No hay series disponibles para este producto.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 5: NUEVO CLIENTE RÁPIDO -->
    <!-- ===================================================================== -->
    @if ($mostrarModalNuevoCliente)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-base font-bold text-white">Nuevo Cliente</h2>
                    <button wire:click="$set('mostrarModalNuevoCliente', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-1">
                            <label class="block font-semibold text-slate-300 mb-1">Tipo Doc.</label>
                            <select wire:model="nuevoClienteTipo" class="w-full px-2 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CE">C.E.</option>
                                <option value="SIN_DOC">Sin Doc</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block font-semibold text-slate-300 mb-1">Número Documento</label>
                            <input type="text" wire:model="nuevoClienteNumero" placeholder="8 u 11 dígitos"
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Nombre o Razón Social</label>
                        <input type="text" wire:model="nuevoClienteNombre" placeholder="Nombre completo del cliente o empresa"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Dirección (Opcional)</label>
                        <input type="text" wire:model="nuevoClienteDireccion" placeholder="Dirección fiscal o comercial"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-300 mb-1">Teléfono</label>
                            <input type="text" wire:model="nuevoClienteTelefono" placeholder="987654321"
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-300 mb-1">Email</label>
                            <input type="email" wire:model="nuevoClienteEmail" placeholder="cliente@correo.com"
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-white">
                        </div>
                    </div>

                    <div class="pt-2 flex space-x-2">
                        <button wire:click="$set('mostrarModalNuevoCliente', false)" class="flex-1 py-2.5 bg-slate-800 text-slate-300 rounded-xl">Cancelar</button>
                        <button wire:click="crearClienteRapido" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 6: COBRO CON PAGOS MIXTOS & VUELTO -->
    <!-- ===================================================================== -->
    @if ($mostrarModalCobro)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <div class="w-full max-w-xl bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-white">Confirmar Pago de Venta</h2>
                        <p class="text-xs text-slate-400">Total a pagar: <span class="text-emerald-400 font-bold font-mono text-base">S/ {{ number_format($this->total, 2) }}</span></p>
                    </div>
                    <button wire:click="$set('mostrarModalCobro', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto py-4 space-y-4 text-xs">
                    <!-- LISTA DE MÉTODOS DE PAGO AGREGADOS -->
                    <div class="space-y-2.5">
                        @foreach ($pagos as $pIndex => $pago)
                            <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center space-x-2">
                                <div class="w-1/3">
                                    <label class="block text-[10px] text-slate-400 mb-1">Método</label>
                                    <select wire:model.live="pagos.{{ $pIndex }}.metodo"
                                            class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-medium">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="yape">Yape</option>
                                        <option value="plin">Plin</option>
                                        <option value="tarjeta">Tarjeta (POS)</option>
                                        <option value="transferencia">Transferencia</option>
                                    </select>
                                </div>
                                <div class="w-1/3">
                                    <label class="block text-[10px] text-slate-400 mb-1">Monto (S/)</label>
                                    <input type="number" step="0.01" wire:model.live="pagos.{{ $pIndex }}.monto"
                                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-mono font-bold">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[10px] text-slate-400 mb-1">Referencia</label>
                                    <input type="text" wire:model="pagos.{{ $pIndex }}.referencia" placeholder="Op., voucher..."
                                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white">
                                </div>
                                @if (count($pagos) > 1)
                                    <button wire:click="eliminarMetodoPago({{ $pIndex }})" class="mt-4 p-2 text-rose-400 hover:text-rose-300">
                                        &times;
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <button wire:click="agregarMetodoPago"
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg font-medium text-xs flex items-center space-x-1">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        <span>Agregar otro método (Pago Mixto)</span>
                    </button>

                    <!-- SECCIÓN DE EFECTIVO RECIBIDO Y VUELTO -->
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 grid grid-cols-2 gap-3 items-center">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Efectivo Entregado (S/)</label>
                            <input type="number" step="0.01" wire:model.live="efectivoRecibido"
                                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-mono text-base font-bold">
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400 block">Vuelto a entregar:</span>
                            <span class="text-xl font-bold font-mono text-amber-400">
                                S/ {{ number_format($this->vueltoCalculado, 2) }}
                            </span>
                        </div>
                    </div>

                    <!-- ESTADO DE LA SUMA DE PAGOS -->
                    <div class="flex justify-between items-center text-xs font-mono p-2 rounded-lg bg-slate-950 border border-slate-800">
                        <span>Total Pagos: S/ {{ number_format($this->totalPagos, 2) }}</span>
                        @if ($this->montoPendiente > 0)
                            <span class="text-rose-400 font-bold">Faltan S/ {{ number_format($this->montoPendiente, 2) }}</span>
                        @else
                            <span class="text-emerald-400 font-bold">Cubierto ✓</span>
                        @endif
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex space-x-3">
                    <button wire:click="$set('mostrarModalCobro', false)"
                            @disabled($procesandoVenta)
                            class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl">
                        Cancelar
                    </button>
                    <button wire:click="confirmarVenta"
                            @disabled($procesandoVenta || $this->totalPagos < $this->total)
                            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-bold rounded-xl shadow-lg shadow-emerald-950/40 flex items-center justify-center space-x-2">
                        @if ($procesandoVenta)
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Procesando...</span>
                        @else
                            <span>Confirmar Venta</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 7: ÉXITO TRAS REGISTRAR VENTA (TICKET) -->
    <!-- ===================================================================== -->
    @if ($ventaExitosa)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 text-center">
                <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4 ring-1 ring-emerald-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>

                <h2 class="text-xl font-bold text-white">¡Venta Registrada!</h2>
                <p class="text-sm font-mono text-emerald-400 mt-1">{{ $ventaExitosa->codigo }}</p>
                <div class="text-2xl font-bold font-mono text-white my-3">S/ {{ number_format((float)$ventaExitosa->total, 2) }}</div>

                @if ($ventaExitosa->tipo_comprobante->value !== 'nota_venta')
                    @php
                        $comprobante = $ventaExitosa->comprobanteElectronico;
                        $estado = $comprobante?->estado->value ?? 'pendiente';
                    @endphp
                    <div class="my-3 px-3 py-2 rounded-xl text-xs flex items-center justify-between border
                        {{ $estado === 'aceptado' ? 'bg-emerald-950/60 border-emerald-800 text-emerald-300' : 
                           ($estado === 'rechazado' || $estado === 'error' ? 'bg-rose-950/60 border-rose-800 text-rose-300' : 'bg-amber-950/60 border-amber-800 text-amber-300') }}">
                        <span class="font-medium">SUNAT / Nubefact:</span>
                        <span class="uppercase font-bold tracking-wider">{{ $estado }}</span>
                    </div>
                @endif

                <div class="space-y-2 pt-2">
                    <a href="{{ route('pos.ticket', $ventaExitosa->id) }}" target="_blank"
                       class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Imprimir Ticket (80 mm)</span>
                    </a>

                    <button wire:click="$set('ventaExitosa', null)"
                            class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium rounded-xl text-xs transition">
                        Nueva Venta
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 8: MIS VENTAS DEL DÍA (HISTORIAL DEL VENDEDOR) -->
    <!-- ===================================================================== -->
    @if ($mostrarModalHistorial)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-white">Mis Ventas del Día</h2>
                        <p class="text-xs text-slate-400">Total ventas hoy: {{ count($ventasDelDia) }}</p>
                    </div>
                    <button wire:click="$set('mostrarModalHistorial', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="my-3">
                    <input type="text" wire:model.live.debounce.250ms="busquedaHistorial" placeholder="Buscar por código de venta o cliente..."
                           class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white">
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
                    @forelse ($ventasDelDia as $v)
                        <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-slate-200 font-mono">{{ $v->codigo }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded {{ $v->estado->value === 'completada' ? 'bg-emerald-950 text-emerald-400' : 'bg-rose-950 text-rose-400' }}">
                                        {{ $v->estado->etiqueta() }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">
                                    {{ $v->tipo_comprobante->etiqueta() }} {{ $v->serie_comprobante }}-{{ $v->numero_comprobante }} • {{ $v->cliente->nombre_razon_social }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    {{ $v->created_at->format('H:i:s') }}
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <span class="font-bold text-sm font-mono text-white">S/ {{ number_format((float)$v->total, 2) }}</span>
                                <a href="{{ route('pos.ticket', $v->id) }}" target="_blank"
                                   class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-emerald-400 font-medium rounded-lg border border-slate-700 transition flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    <span>Ticket</span>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-slate-500">
                            No has registrado ventas el día de hoy.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODAL DE CONSULTA DE GARANTÍA EN POS -->
    <!-- ========================================================================= -->
    @if ($mostrarModalGarantia)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col">
                <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-800/50">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <h3 class="font-bold text-base text-white">Consultar Garantía por Serie</h3>
                    </div>
                    <button wire:click="$set('mostrarModalGarantia', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="p-5 space-y-4">
                    <form wire:submit.prevent="consultarGarantiaEnPos" class="flex gap-2">
                        <input type="text" wire:model.defer="busquedaGarantiaSerie" 
                               placeholder="Escanear o ingresar número de serie..." 
                               class="flex-1 px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase font-mono">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition">
                            Consultar
                        </button>
                    </form>

                    @if ($resultadoGarantia)
                        <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-4 space-y-3 text-xs">
                            <div class="flex justify-between items-start border-b border-slate-700 pb-2">
                                <div>
                                    <span class="text-slate-400 block">Producto:</span>
                                    <span class="font-bold text-white text-sm">{{ $resultadoGarantia['producto'] }}</span>
                                    <span class="font-mono text-slate-400 block text-[11px]">S/N: {{ $resultadoGarantia['serie'] }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ ($resultadoGarantia['vigente'] ?? false) ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-rose-950 text-rose-400 border border-rose-800' }}">
                                    {{ strtoupper($resultadoGarantia['estado'] ?? 'DESCONOCIDO') }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-slate-300">
                                <div><strong>Cliente:</strong> {{ $resultadoGarantia['cliente'] ?? 'N/A' }}</div>
                                <div><strong>Venta:</strong> {{ $resultadoGarantia['venta_codigo'] ?? 'N/A' }} ({{ $resultadoGarantia['fecha_venta'] ?? 'N/A' }})</div>
                                <div><strong>Vence:</strong> {{ $resultadoGarantia['fecha_fin'] ?? 'N/A' }}</div>
                                <div><strong>Días Restantes:</strong> <span class="font-bold {{ ($resultadoGarantia['dias_restantes'] ?? 0) > 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $resultadoGarantia['dias_restantes'] ?? 0 }} días</span></div>
                            </div>

                            @if (!empty($resultadoGarantia['condiciones']))
                                <div class="bg-slate-900/80 p-2.5 rounded border border-slate-700/60 text-[11px] text-slate-300">
                                    <strong>Condiciones pactadas:</strong> {{ $resultadoGarantia['condiciones'] }}
                                </div>
                            @endif

                            <div class="pt-2 flex gap-2">
                                <button wire:click="iniciarDevolucionDesdeGarantia" 
                                        class="flex-1 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition text-center">
                                    Iniciar Devolución / Cambio
                                </button>
                                <button wire:click="recibirServicioDesdeGarantia" 
                                        class="flex-1 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition text-center">
                                    Recibir en Servicio Técnico
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODAL ASISTENTE DE DEVOLUCIÓN / CAMBIO (PASO A PASO) -->
    <!-- ========================================================================= -->
    @if ($mostrarModalDevolucion && $ventaDevolucion)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-800/50">
                    <div>
                        <h3 class="font-bold text-base text-white flex items-center gap-2">
                            <span>Asistente de Devolución & Cambio</span>
                            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-400">Venta: {{ $ventaDevolucion->codigo }}</span>
                        </h3>
                        <div class="text-[11px] text-slate-400 mt-0.5">Paso {{ $pasoDevolucion }} de 5: 
                            @if($pasoDevolucion === 1) Seleccionar Ítems y Saldo Devolvible
                            @elseif($pasoDevolucion === 2) Motivo y Estado Físico
                            @elseif($pasoDevolucion === 3) Modalidad (Dinero, Cambio o Nota de Crédito)
                            @elseif($pasoDevolucion === 4) Escaneo de Serie Nueva
                            @else Confirmación Final
                            @endif
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalDevolucion', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="p-5 flex-1 overflow-y-auto space-y-4">
                    @if ($pasoDevolucion === 1)
                        <div class="space-y-2">
                            <p class="text-xs text-slate-400">Seleccione los productos o series a devolver de esta venta:</p>
                            @foreach($itemsDevolucion as $k => $item)
                                <label class="flex items-center justify-between p-3 rounded-xl border {{ !empty($item['seleccionado']) ? 'bg-amber-950/20 border-amber-600' : 'bg-slate-800/40 border-slate-700' }} cursor-pointer">
                                    <div class="flex items-center space-x-3">
                                        <input type="checkbox" wire:model="itemsDevolucion.{{ $k }}.seleccionado" class="rounded text-amber-500 focus:ring-0">
                                        <div>
                                            <div class="font-bold text-sm text-white">{{ $item['producto_nombre'] }}</div>
                                            <div class="text-xs text-slate-400 font-mono">
                                                @if($item['serie_numero']) S/N: {{ $item['serie_numero'] }} @else Cant. disponible: {{ $item['cantidad_disponible'] }} @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-slate-400 block">Saldo Devolvible:</span>
                                        <span class="font-bold text-emerald-400 font-mono">S/ {{ number_format((float)$item['monto_disponible'], 2) }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                    @elseif ($pasoDevolucion === 2)
                        <div class="space-y-4 text-xs">
                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Motivo Principal:</label>
                                <select wire:model="motivoDevolucionInput" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                    <option value="defecto_fabrica">Defecto de fábrica (Aplica cobertura de garantía)</option>
                                    <option value="arrepentimiento">Arrepentimiento de compra / Cambio voluntario</option>
                                    <option value="error_venta">Error en venta o digitación</option>
                                    <option value="producto_incorrecto">Producto entregado no corresponde</option>
                                    <option value="otro">Otro motivo</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Detalle o Explicación del Motivo *:</label>
                                <textarea wire:model.defer="detalleMotivoDevolucion" rows="2" placeholder="Detalle la falla o causa..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white"></textarea>
                            </div>

                            <div class="space-y-2 pt-2 border-t border-slate-800">
                                <span class="font-semibold text-slate-300 block">Estado Físico del Equipo Recibido:</span>
                                @foreach($itemsDevolucion as $k => $item)
                                    @if(!empty($item['seleccionado']))
                                        <div class="p-3 bg-slate-800/60 border border-slate-700 rounded-lg grid grid-cols-2 gap-3 items-center">
                                            <div class="font-medium text-slate-200">{{ $item['producto_nombre'] }} ({{ $item['serie_numero'] ?? 'Sin serie' }})</div>
                                            <div>
                                                <select wire:model="itemsDevolucion.{{ $k }}.estado_fisico" class="w-full py-1.5 px-2 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                                                    <option value="nuevo_sellado">Nuevo y Sellado (Pasa a disponible)</option>
                                                    <option value="abierto_ok">Abierto en Buen Estado (Pasa a disponible)</option>
                                                    <option value="con_defecto">Con Defecto de Fábrica (Pasa a garantía)</option>
                                                    <option value="danado">Dañado / Golpeado (Pasa a baja)</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                    @elseif ($pasoDevolucion === 3)
                        <div class="space-y-3">
                            <span class="text-xs text-slate-400 block">Seleccione el tipo de resolución para el cliente:</span>
                            
                            <label class="flex items-center justify-between p-3.5 rounded-xl border {{ $tipoDevolucionInput === 'devolucion_dinero' ? 'bg-rose-950/20 border-rose-600' : 'bg-slate-800/40 border-slate-700' }} cursor-pointer">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" wire:model="tipoDevolucionInput" value="devolucion_dinero" class="text-rose-500 focus:ring-0">
                                    <div>
                                        <div class="font-bold text-sm text-white">Devolución de Dinero</div>
                                        <div class="text-xs text-slate-400">Registra un egreso en la caja abierta actual del vendedor</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center justify-between p-3.5 rounded-xl border {{ $tipoDevolucionInput === 'cambio_producto' ? 'bg-blue-950/20 border-blue-600' : 'bg-slate-800/40 border-slate-700' }} cursor-pointer">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" wire:model="tipoDevolucionInput" value="cambio_producto" class="text-blue-500 focus:ring-0">
                                    <div>
                                        <div class="font-bold text-sm text-white">Cambio por Otro Producto / Serie</div>
                                        <div class="text-xs text-slate-400">Permite seleccionar la nueva serie y liquidar diferencias</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center justify-between p-3.5 rounded-xl border {{ $tipoDevolucionInput === 'nota_credito' ? 'bg-amber-950/20 border-amber-600' : 'bg-slate-800/40 border-slate-700' }} cursor-pointer">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" wire:model="tipoDevolucionInput" value="nota_credito" class="text-amber-500 focus:ring-0">
                                    <div>
                                        <div class="font-bold text-sm text-white">Nota de Crédito Electrónica</div>
                                        <div class="text-xs text-slate-400">Genera comprobante SUNAT (solo para boletas y facturas)</div>
                                    </div>
                                </div>
                            </label>
                        </div>

                    @elseif ($pasoDevolucion === 4)
                        <div class="space-y-4 text-xs">
                            <p class="text-slate-300">Escanee o busque el número de serie nuevo para realizar el cambio:</p>
                            <div class="flex gap-2">
                                <input type="text" wire:model.defer="serieNuevaCambioInput" placeholder="Número de serie nuevo disponible..." class="flex-1 px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white font-mono uppercase">
                                <button type="button" wire:click="buscarSerieNuevaParaCambio" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg">
                                    Validar Serie
                                </button>
                            </div>

                            @if ($serieNuevaSeleccionada)
                                <div class="p-3 bg-slate-800/80 border border-emerald-600 rounded-xl space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-white text-sm">{{ $serieNuevaSeleccionada->producto->nombre }}</span>
                                        <span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 text-[10px] font-bold">DISPONIBLE</span>
                                    </div>
                                    <div class="font-mono text-slate-400">S/N: {{ $serieNuevaSeleccionada->numero_serie }}</div>
                                    <div class="flex justify-between items-center pt-2 border-t border-slate-700 text-sm">
                                        <span>Diferencia de Precio:</span>
                                        <span class="font-bold font-mono {{ $diferenciaCambio > 0 ? 'text-amber-400' : ($diferenciaCambio < 0 ? 'text-emerald-400' : 'text-slate-300') }}">
                                            @if($diferenciaCambio > 0) + S/ {{ number_format($diferenciaCambio, 2) }} (Cliente paga)
                                            @elseif($diferenciaCambio < 0) - S/ {{ number_format(abs($diferenciaCambio), 2) }} (Reembolso)
                                            @else S/ 0.00 (Mismo importe)
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>

                    @elseif ($pasoDevolucion === 5)
                        <div class="p-4 bg-slate-800/60 border border-slate-700 rounded-xl space-y-3 text-xs">
                            <div class="font-bold text-sm text-white">Resumen de la Solicitud:</div>
                            <div><strong>Tipo:</strong> {{ ucfirst(str_replace('_', ' ', $tipoDevolucionInput)) }}</div>
                            <div><strong>Motivo:</strong> {{ ucfirst(str_replace('_', ' ', $motivoDevolucionInput)) }}</div>
                            <div><strong>Detalle:</strong> {{ $detalleMotivoDevolucion }}</div>
                            @if(auth()->user()->hasRole('admin'))
                                <div class="p-2.5 rounded bg-emerald-950/40 border border-emerald-800 text-emerald-300 text-[11px]">
                                    ✓ Como Administrador, la operación se <strong>completará y liquidará inmediatamente</strong> en el sistema.
                                </div>
                            @else
                                <div class="p-2.5 rounded bg-blue-950/40 border border-blue-800 text-blue-300 text-[11px]">
                                    ℹ Como Vendedor, se generará una <strong>solicitud de devolución</strong> pendiente de aprobación de gerencia.
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Footer del Asistente -->
                <div class="px-5 py-3 border-t border-slate-800 flex justify-between bg-slate-800/50">
                    <button type="button" wire:click="retrocederPasoDevolucion" 
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-lg transition {{ $pasoDevolucion === 1 ? 'invisible' : '' }}">
                        &larr; Anterior
                    </button>

                    @if ($pasoDevolucion < 5)
                        <button type="button" wire:click="avanzarPasoDevolucion" 
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-lg transition">
                            Siguiente &rarr;
                        </button>
                    @else
                        <button type="button" wire:click="procesarDevolucion" 
                                class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition shadow-lg shadow-emerald-600/30">
                            Confirmar y Procesar
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- MODAL RECEPCIÓN Y ENTREGA DE SERVICIO TÉCNICO EN POS -->
    <!-- ========================================================================= -->
    @if ($mostrarModalServicio)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <div class="px-5 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-800/50">
                    <div class="flex space-x-2">
                        <button type="button" wire:click="$set('tabServicio', 'recibir')" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $tabServicio === 'recibir' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Recepción Rápida
                        </button>
                        <button type="button" wire:click="$set('tabServicio', 'entregar')" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $tabServicio === 'entregar' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Listas para Entrega
                        </button>
                    </div>
                    <button wire:click="$set('mostrarModalServicio', false)" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="p-5 flex-1 overflow-y-auto space-y-4 text-xs">
                    @if ($tabServicio === 'recibir')
                        <div class="space-y-3">
                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Serie del Equipo (Si se vendió en tienda):</label>
                                <input type="text" wire:model.defer="servicioSerieInput" placeholder="Ej. SN-LNV-001" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white font-mono uppercase">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-300 mb-1">O Descripción Equipo Externo:</label>
                                    <input type="text" wire:model.defer="servicioEquipoDescripcion" placeholder="Ej. Laptop HP Pavilion 15" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-300 mb-1">Serie Externa (Opcional):</label>
                                    <input type="text" wire:model.defer="servicioSerieExterna" placeholder="S/N del fabricante" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white font-mono">
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Falla Reportada por el Cliente *:</label>
                                <textarea wire:model.defer="servicioFalla" rows="2" placeholder="Detalle la falla exacta..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white"></textarea>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Estado Físico y Accesorios Entregados:</label>
                                <input type="text" wire:model.defer="servicioEstadoRecepcion" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-300 mb-1">Fecha Promesa de Entrega:</label>
                                <input type="date" wire:model.defer="servicioFechaPromesa" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>

                            <button type="button" wire:click="registrarRecepcionServicio" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl transition text-sm">
                                Registrar Ingreso a Taller
                            </button>
                        </div>

                    @elseif ($tabServicio === 'entregar')
                        <div class="space-y-3">
                            <div class="mb-2">
                                <label class="block font-semibold text-slate-300 mb-1">Persona que recoge el equipo (Nombre / DNI):</label>
                                <input type="text" wire:model.defer="servicioEntregadoA" placeholder="Dejar en blanco para usar nombre del titular" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>

                            @forelse($ordenesParaEntregar as $ord)
                                <div class="p-3 bg-slate-800/60 border border-slate-700 rounded-xl flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-white text-sm font-mono">{{ $ord->codigo }}</div>
                                        <div class="text-slate-300">{{ $ord->producto?->nombre ?? $ord->equipo_descripcion }}</div>
                                        <div class="text-[11px] text-slate-400">Cliente: {{ $ord->cliente->nombre_razon_social }}</div>
                                        @if($ord->costo_final > 0)
                                            <div class="text-xs font-bold text-amber-400">Total: S/ {{ number_format((float)$ord->costo_final, 2) }} ({{ $ord->venta_id ? 'Pagado' : 'Pendiente' }})</div>
                                        @endif
                                    </div>
                                    <div>
                                        <button type="button" wire:click="entregarEquipoEnPos({{ $ord->id }})" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs">
                                            Entregar
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-slate-500">
                                    No hay órdenes pendientes de entrega en este momento.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>

