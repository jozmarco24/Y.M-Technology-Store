<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- BARRA SUPERIOR CATÁLOGO -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 bg-white p-4 rounded-2xl border border-gray-200">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">Catálogo de Productos</h1>
            <p class="text-xs text-gray-500">Mostrando {{ $productos->total() }} productos disponibles</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Buscador interno con debounce -->
            <div class="relative">
                <input type="text" 
                       wire:model.live.debounce.300ms="busqueda" 
                       placeholder="Buscar en catálogo..." 
                       class="text-xs bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- Ordenamiento -->
            <select wire:model.live="orden" class="text-xs bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 outline-none focus:border-blue-500">
                <option value="novedad">Más recientes</option>
                <option value="precio_asc">Precio: Menor a Mayor</option>
                <option value="precio_desc">Precio: Mayor a Menor</option>
                <option value="nombre_asc">Nombre: A - Z</option>
            </select>
        </div>
    </div>

    <!-- COMPARADOR FLOTANTE (Hasta 3 laptops/productos) -->
    @if(count($comparadorIds) > 0)
        <div class="mb-8 bg-blue-50 border border-blue-200 p-4 rounded-2xl">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-blue-900">Comparador de Productos ({{ count($comparadorIds) }}/3)</span>
                <button wire:click="limpiarComparador" class="text-[11px] text-blue-700 hover:underline">Limpiar comparador</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($productosComparar as $comp)
                    <div class="bg-white p-3 rounded-xl border border-blue-100 flex items-center justify-between">
                        <div class="truncate pr-2">
                            <span class="block text-xs font-bold text-gray-900 truncate">{{ $comp->nombre }}</span>
                            <span class="text-xs font-bold text-blue-600">S/ {{ number_format((float) $comp->precio_venta, 2) }}</span>
                        </div>
                        <button wire:click="toggleComparador({{ $comp->id }})" class="text-gray-400 hover:text-rose-500">✕</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- SIDEBAR DE FILTROS -->
        <aside class="space-y-6">
            <!-- Filtro de Categoría -->
            <div class="bg-white p-5 rounded-2xl border border-gray-200">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Categorías</h3>
                <ul class="text-xs space-y-2">
                    <li>
                        <button wire:click="$set('categoria', null)" 
                                class="w-full text-left font-medium {{ $categoria === null ? 'text-blue-600 font-bold' : 'text-gray-600 hover:text-gray-900' }}">
                            Todas las categorías
                        </button>
                    </li>
                    @foreach($categorias as $cat)
                        <li>
                            <button wire:click="$set('categoria', '{{ $cat->slug }}')" 
                                    class="w-full text-left font-medium {{ $categoria === $cat->slug ? 'text-blue-600 font-bold' : 'text-gray-600 hover:text-gray-900' }}">
                                {{ $cat->nombre }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Filtro de Marca -->
            <div class="bg-white p-5 rounded-2xl border border-gray-200">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Marcas</h3>
                <ul class="text-xs space-y-2 max-h-48 overflow-y-auto">
                    <li>
                        <button wire:click="$set('marca', null)" 
                                class="w-full text-left font-medium {{ $marca === null ? 'text-blue-600 font-bold' : 'text-gray-600 hover:text-gray-900' }}">
                            Todas las marcas
                        </button>
                    </li>
                    @foreach($marcas as $m)
                        <li>
                            <button wire:click="$set('marca', '{{ $m->slug }}')" 
                                    class="w-full text-left font-medium {{ $marca === $m->slug ? 'text-blue-600 font-bold' : 'text-gray-600 hover:text-gray-900' }}">
                                {{ $m->nombre }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Filtro de Rango de Precio -->
            <div class="bg-white p-5 rounded-2xl border border-gray-200">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Rango de Precio</h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <label class="text-[10px] text-gray-500 block mb-1">Mínimo (S/)</label>
                        <input type="number" wire:model.live.debounce.400ms="precioMin" placeholder="0" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-500 block mb-1">Máximo (S/)</label>
                        <input type="number" wire:model.live.debounce.400ms="precioMax" placeholder="5000" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-2 outline-none">
                    </div>
                </div>
            </div>
        </aside>

        <!-- GRILLA DE PRODUCTOS -->
        <main class="lg:col-span-3">
            @if($productos->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-8">
                    @foreach($productos as $prod)
                        <div class="bg-white rounded-2xl border border-gray-200/80 overflow-hidden hover:shadow-xl transition flex flex-col justify-between group">
                            
                            <!-- Imagen y Estado -->
                            <a href="{{ route('producto.detalle', ['slug' => $prod->slug]) }}" class="relative block bg-gray-50 p-6 flex items-center justify-center h-48">
                                @if($prod->imagen)
                                    <img src="{{ asset('storage/' . $prod->imagen) }}" alt="{{ $prod->nombre }}" class="max-h-full max-w-full object-contain group-hover:scale-105 transition duration-300">
                                @else
                                    <div class="text-gray-300 text-4xl">📦</div>
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

                            <!-- Datos del Producto -->
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
                                        <span class="text-[10px] text-gray-400 block font-medium">Con IGV</span>
                                        <span class="text-base font-extrabold text-blue-600">S/ {{ number_format((float) $prod->precio_venta, 2) }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <button wire:click="toggleComparador({{ $prod->id }})" 
                                                class="p-2 rounded-xl border text-xs font-semibold {{ in_array($prod->id, $comparadorIds, true) ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-400 hover:border-gray-400' }}"
                                                title="Comparar">
                                            ⚖️
                                        </button>

                                        @if($stock > 0)
                                            <button wire:click="agregarAlCarrito({{ $prod->id }})" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white p-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
                                                    title="Agregar al Carrito">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Paginación -->
                <div class="mt-8">
                    {{ $productos->links() }}
                </div>
            @else
                <!-- Estado Sin Resultados -->
                <div class="bg-white rounded-2xl p-12 text-center border border-gray-200">
                    <span class="text-5xl block mb-4">🔍</span>
                    <h3 class="text-base font-bold text-gray-900 mb-1">No se encontraron productos</h3>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto mb-6">
                        No hay artículos que coincidan con los filtros seleccionados. Intenta cambiar los términos de búsqueda o limpiar los filtros.
                    </p>
                    <button wire:click="$set('busqueda', ''); $set('categoria', null); $set('marca', null); $set('precioMin', null); $set('precioMax', null);" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition">
                        Limpiar todos los filtros
                    </button>
                </div>
            @endif
        </main>
    </div>
</div>
