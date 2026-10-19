<div>
    <!-- BANNER HERO PRINCIPAL -->
    @if($banners->isNotEmpty())
        <div class="relative bg-slate-900 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20 relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                @php $banner = $banners->first(); @endphp
                <div class="max-w-xl text-center md:text-left">
                    <span class="inline-block bg-blue-500/20 text-blue-400 text-xs uppercase font-extrabold px-3 py-1 rounded-full mb-4 tracking-wider">
                        Tecnología de Punta 2026
                    </span>
                    <h1 class="text-3xl md:text-5xl font-extrabold text-white tracking-tight leading-tight mb-4">
                        {{ $banner->titulo }}
                    </h1>
                    @if($banner->subtitulo)
                        <p class="text-slate-300 text-sm md:text-base mb-8">
                            {{ $banner->subtitulo }}
                        </p>
                    @endif
                    <div class="flex flex-wrap gap-4 justify-center md:justify-start">
                        <a href="{{ $banner->enlace ?: route('catalogo') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition shadow-lg shadow-blue-500/30">
                            {{ $banner->boton_texto ?: 'Comprar Ahora' }}
                        </a>
                        <a href="{{ route('catalogo') }}" class="bg-slate-800 hover:bg-slate-700 text-white font-semibold px-6 py-3 rounded-xl text-sm transition border border-slate-700">
                            Ver Ofertas
                        </a>
                    </div>
                </div>

                <div class="w-full md:w-1/2 flex justify-center">
                    @if($banner->imagen_url)
                        <img src="{{ str_starts_with($banner->imagen_url, 'http') ? $banner->imagen_url : asset('storage/' . $banner->imagen_url) }}" 
                             alt="{{ $banner->titulo }}" 
                             class="max-h-80 object-contain drop-shadow-2xl">
                    @else
                        <div class="w-72 h-72 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-700 flex items-center justify-center text-white text-6xl shadow-2xl">
                            💻
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <!-- Hero Default si no hay banners cargados -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 py-16 px-4 text-center text-white">
            <span class="inline-block bg-blue-500/20 text-blue-400 text-xs font-bold px-3 py-1 rounded-full mb-3">Envíos Rápidos a Nivel Nacional</span>
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-3">Lo Mejor en Laptops y Componentes</h1>
            <p class="text-slate-300 max-w-2xl mx-auto text-sm md:text-base mb-6">Stock sincronizado con nuestra tienda física en tiempo real y garantía oficial.</p>
            <a href="{{ route('catalogo') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition shadow-lg">
                Explorar Catálogo
            </a>
        </div>
    @endif

    <!-- BENEFICIOS -->
    <div class="bg-white border-b border-gray-200 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div class="flex items-center gap-3 justify-center text-left">
                <span class="text-3xl">🛡️</span>
                <div>
                    <h4 class="text-xs font-bold text-gray-900">Garantía Real</h4>
                    <p class="text-[11px] text-gray-500">Hasta 36 meses directa</p>
                </div>
            </div>
            <div class="flex items-center gap-3 justify-center text-left">
                <span class="text-3xl">🚚</span>
                <div>
                    <h4 class="text-xs font-bold text-gray-900">Envíos Seguros</h4>
                    <p class="text-[11px] text-gray-500">Lima y Provincias con tracking</p>
                </div>
            </div>
            <div class="flex items-center gap-3 justify-center text-left">
                <span class="text-3xl">💳</span>
                <div>
                    <h4 class="text-xs font-bold text-gray-900">Pago Protegido</h4>
                    <p class="text-[11px] text-gray-500">Tarjetas, Yape y transferencias</p>
                </div>
            </div>
            <div class="flex items-center gap-3 justify-center text-left">
                <span class="text-3xl">🏬</span>
                <div>
                    <h4 class="text-xs font-bold text-gray-900">Tienda Física</h4>
                    <p class="text-[11px] text-gray-500">Recojo gratis e inmediato</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CATEGORÍAS DESTACADAS -->
    @if($categoriasDestacadas->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-extrabold text-gray-900">Categorías Populares</h2>
                    <p class="text-xs text-gray-500">Encuentra el equipo ideal según tu necesidad</p>
                </div>
                <a href="{{ route('catalogo') }}" class="text-xs font-bold text-blue-600 hover:underline">Ver todas</a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                @foreach($categoriasDestacadas as $cat)
                    <a href="{{ route('catalogo', ['categoria' => $cat->slug]) }}" 
                       class="bg-white p-4 rounded-2xl border border-gray-100 hover:border-blue-300 hover:shadow-lg transition text-center group">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl mx-auto flex items-center justify-center text-2xl mb-2 group-hover:scale-110 transition">
                            💻
                        </div>
                        <span class="text-xs font-bold text-gray-800 group-hover:text-blue-600 transition block truncate">
                            {{ $cat->nombre }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- PRODUCTOS DESTACADOS -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Productos Destacados</h2>
                <p class="text-xs text-gray-500">Equipos seleccionados con alta demanda y rendimiento</p>
            </div>
            <a href="{{ route('catalogo') }}" class="text-xs font-bold text-blue-600 hover:underline">Ver catálogo</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            @foreach($productosDestacados as $prod)
                <div class="bg-white rounded-2xl border border-gray-200/80 overflow-hidden hover:shadow-xl transition flex flex-col justify-between group">
                    <a href="{{ route('producto.detalle', ['slug' => $prod->slug]) }}" class="relative block bg-gray-50 p-6 flex items-center justify-center h-52">
                        @if($prod->imagen)
                            <img src="{{ asset('storage/' . $prod->imagen) }}" alt="{{ $prod->nombre }}" class="max-h-full max-w-full object-contain group-hover:scale-105 transition duration-300">
                        @else
                            <div class="text-gray-300 text-5xl">📦</div>
                        @endif

                        @php $stock = $prod->seriesDisponibles()->count(); @endphp
                        @if($stock <= 0)
                            <span class="absolute top-3 left-3 bg-rose-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase">
                                Agotado
                            </span>
                        @elseif($stock <= 2)
                            <span class="absolute top-3 left-3 bg-amber-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase">
                                Últimas {{ $stock }} unidades
                            </span>
                        @endif
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider block mb-1">
                                {{ $prod->marca?->nombre ?? 'Marca' }}
                            </span>
                            <a href="{{ route('producto.detalle', ['slug' => $prod->slug]) }}" class="text-sm font-bold text-gray-900 hover:text-blue-600 transition line-clamp-2 mb-2">
                                {{ $prod->nombre }}
                            </a>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between mt-auto">
                            <div>
                                <span class="text-[10px] text-gray-400 block font-medium">Precio con IGV</span>
                                <span class="text-base font-extrabold text-blue-600">S/ {{ number_format((float) $prod->precio_venta, 2) }}</span>
                            </div>

                            @if($stock > 0)
                                <button wire:click="agregarAlCarrito({{ $prod->id }})" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white p-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
                                        title="Agregar al Carrito">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </button>
                            @else
                                <span class="text-xs text-gray-400 font-medium">Sin stock</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- MARCAS ASOCIADAS -->
    @if($marcas->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-200">
            <h3 class="text-center text-xs font-bold text-gray-400 uppercase tracking-widest mb-6">Marcas Oficiales con las que trabajamos</h3>
            <div class="flex flex-wrap items-center justify-center gap-8">
                @foreach($marcas as $m)
                    <div class="text-sm font-bold text-gray-500 hover:text-gray-900 transition px-4 py-2 bg-gray-100 rounded-xl">
                        {{ $m->nombre }}
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
