<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-gray-500 mb-6 gap-2 items-center">
        <a href="{{ route('inicio') }}" class="hover:text-blue-600">Inicio</a>
        <span>/</span>
        <a href="{{ route('catalogo') }}" class="hover:text-blue-600">Catálogo</a>
        <span>/</span>
        <span class="text-gray-900 font-semibold truncate">{{ $producto->nombre }}</span>
    </nav>

    <!-- FICHA PRINCIPAL DEL PRODUCTO -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white p-6 sm:p-10 rounded-3xl border border-gray-200/80 mb-12 shadow-sm">
        
        <!-- Galería de Imágenes -->
        <div>
            <div class="bg-gray-50 rounded-2xl p-6 flex items-center justify-center h-80 sm:h-96 border border-gray-100 mb-4">
                @if($imagenSeleccionada)
                    <img src="{{ asset('storage/' . $imagenSeleccionada) }}" alt="{{ $producto->nombre }}" class="max-h-full max-w-full object-contain">
                @else
                    <div class="text-gray-300 text-6xl">📦</div>
                @endif
            </div>

            <!-- Miniaturas -->
            @if($producto->imagenes->isNotEmpty() || $producto->imagen)
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @if($producto->imagen)
                        <button wire:click="seleccionarImagen('{{ $producto->imagen }}')" 
                                class="w-16 h-16 rounded-xl border-2 {{ $imagenSeleccionada === $producto->imagen ? 'border-blue-600' : 'border-gray-200' }} p-1 bg-white shrink-0 overflow-hidden">
                            <img src="{{ asset('storage/' . $producto->imagen) }}" alt="Miniatura principal" class="w-full h-full object-contain">
                        </button>
                    @endif
                    @foreach($producto->imagenes as $img)
                        <button wire:click="seleccionarImagen('{{ $img->ruta }}')" 
                                class="w-16 h-16 rounded-xl border-2 {{ $imagenSeleccionada === $img->ruta ? 'border-blue-600' : 'border-gray-200' }} p-1 bg-white shrink-0 overflow-hidden">
                            <img src="{{ asset('storage/' . $img->ruta) }}" alt="Miniatura adicional" class="w-full h-full object-contain">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Información y Compra -->
        <div class="flex flex-col justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-blue-600 block mb-1">
                    {{ $producto->marca?->nombre ?? 'Oficial' }}
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-snug mb-3">
                    {{ $producto->nombre }}
                </h1>

                <!-- Stock e Indicadores -->
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    @php $stock = (int) ($producto->stock_disponible ?? $producto->seriesDisponibles()->count()); @endphp
                    @if($stock > 0)
                        <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            En Stock ({{ $stock }} disponibles)
                        </span>
                    @else
                        <span class="bg-rose-50 text-rose-700 text-xs font-bold px-3 py-1 rounded-full border border-rose-200">
                            Agotado temporalmente
                        </span>
                    @endif

                    <span class="text-xs text-gray-500">SKU: <strong class="text-gray-800">{{ $producto->sku }}</strong></span>
                </div>

                <!-- Precio -->
                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-6">
                    <span class="text-xs text-gray-400 block font-medium">Precio final con IGV incluido</span>
                    <div class="text-3xl sm:text-4xl font-extrabold text-blue-600">
                        S/ {{ number_format((float) $producto->precio_venta, 2) }}
                    </div>
                </div>

                <!-- Política de Garantía Destacada -->
                @if($politicaGarantia)
                    <div class="bg-blue-50/60 p-4 rounded-2xl border border-blue-100 flex items-start gap-3 mb-6">
                        <span class="text-2xl">🛡️</span>
                        <div class="text-xs">
                            <strong class="text-blue-950 font-bold block mb-0.5">Garantía Oficial de la Tienda ({{ $politicaGarantia->meses_garantia }} Meses)</strong>
                            <p class="text-blue-800 leading-relaxed">{{ $politicaGarantia->terminos }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Acciones de Agregar al Carrito -->
            @if($stock > 0)
                <div class="pt-6 border-t border-gray-100 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                            <button wire:click="decrementarCantidad" class="px-3.5 py-2.5 text-gray-600 hover:bg-gray-200 transition font-bold">-</button>
                            <span class="px-4 py-2.5 text-xs font-bold text-gray-900">{{ $cantidad }}</span>
                            <button wire:click="incrementarCantidad" class="px-3.5 py-2.5 text-gray-600 hover:bg-gray-200 transition font-bold">+</button>
                        </div>

                        <button wire:click="agregarAlCarrito" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-xl text-sm transition shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            Agregar al Carrito
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- ESPECIFICACIONES TÉCNICAS Y DESCRIPCIÓN -->
    <div class="bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-10 mb-12">
        <h2 class="text-lg font-extrabold text-gray-900 mb-6">Especificaciones Técnicas</h2>
        
        @if(!empty($producto->especificaciones) && is_array($producto->especificaciones))
            <div class="overflow-x-auto mb-8">
                <table class="w-full text-xs text-left border-collapse">
                    <tbody>
                        @foreach($producto->especificaciones as $clave => $valor)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 font-bold text-gray-700 w-1/3 bg-gray-50/50">{{ $clave }}</td>
                                <td class="py-3 px-4 text-gray-900">{{ is_array($valor) ? json_encode($valor) : $valor }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($producto->descripcion)
            <div class="prose prose-sm max-w-none text-gray-600 leading-relaxed">
                <h3 class="text-sm font-bold text-gray-900 mb-2">Descripción del Producto</h3>
                <p>{{ $producto->descripcion }}</p>
            </div>
        @endif
    </div>

    <!-- PRODUCTOS RELACIONADOS -->
    @if($relacionados->isNotEmpty())
        <section class="mt-12">
            <h2 class="text-xl font-extrabold text-gray-900 mb-6">Productos Relacionados</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($relacionados as $rel)
                    <div class="bg-white rounded-2xl border border-gray-200/80 p-4 hover:shadow-lg transition flex flex-col justify-between">
                        <a href="{{ route('producto.detalle', ['slug' => $rel->slug]) }}" class="block h-36 bg-gray-50 rounded-xl p-4 flex items-center justify-center mb-3">
                            @if($rel->imagen)
                                <img src="{{ asset('storage/' . $rel->imagen) }}" alt="{{ $rel->nombre }}" class="max-h-full max-w-full object-contain">
                            @else
                                <div class="text-gray-300 text-3xl">📦</div>
                            @endif
                        </a>
                        <div>
                            <span class="text-[10px] text-gray-400 font-bold uppercase">{{ $rel->marca?->nombre }}</span>
                            <a href="{{ route('producto.detalle', ['slug' => $rel->slug]) }}" class="text-xs font-bold text-gray-900 hover:text-blue-600 transition line-clamp-2 mb-2 block">
                                {{ $rel->nombre }}
                            </a>
                            <span class="text-sm font-extrabold text-blue-600">S/ {{ number_format((float) $rel->precio_venta, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- DATOS ESTRUCTURADOS JSON-LD PARA SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "{{ $producto->nombre }}",
        "image": "{{ $producto->imagen ? asset('storage/' . $producto->imagen) : '' }}",
        "description": "{{ $producto->meta_descripcion ?: Str::limit(strip_tags((string) $producto->descripcion), 150) }}",
        "sku": "{{ $producto->sku }}",
        "brand": {
            "@type": "Brand",
            "name": "{{ $producto->marca?->nombre ?? 'SYS-COPM' }}"
        },
        "offers": {
            "@type": "Offer",
            "url": "{{ url()->current() }}",
            "priceCurrency": "PEN",
            "price": "{{ $producto->precio_venta }}",
            "availability": "{{ $producto->stock_disponible > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}"
        }
    }
    </script>
</div>
