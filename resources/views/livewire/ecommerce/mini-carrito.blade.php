<div class="relative" x-data="{ abierto: @entangle('abierto') }">
    <!-- Botón con Contador -->
    <button @click="abierto = !abierto" class="relative p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>
        @if($cantidadTotal > 0)
            <span class="absolute top-1 right-1 bg-blue-600 text-white font-bold text-[10px] w-5 h-5 rounded-full flex items-center justify-center shadow-md animate-pulse">
                {{ $cantidadTotal }}
            </span>
        @endif
    </button>

    <!-- Overlay de Fondo -->
    <div x-show="abierto" 
         x-cloak
         @click="abierto = false"
         class="fixed inset-0 bg-black/50 z-50 transition-opacity"></div>

    <!-- Drawer Lateral -->
    <div x-show="abierto" 
         x-cloak
         class="fixed top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl z-50 flex flex-col transform transition-transform duration-300">
        
        <!-- Header Drawer -->
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
            <div class="flex items-center gap-2">
                <span class="font-bold text-gray-900">Tu Carrito</span>
                <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-bold">{{ $cantidadTotal }}</span>
            </div>
            <button @click="abierto = false" class="text-gray-400 hover:text-gray-700 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Lista de Items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            @if($carrito && $carrito->items->isNotEmpty())
                @foreach($carrito->items as $item)
                    <div class="flex gap-3 border-b border-gray-100 pb-3">
                        <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden shrink-0 flex items-center justify-center">
                            @if($item->producto?->imagen)
                                <img src="{{ asset('storage/' . $item->producto->imagen) }}" alt="{{ $item->producto->nombre }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xs text-gray-400">Sin foto</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 truncate">{{ $item->producto?->nombre }}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $item->cantidad }} x S/ {{ number_format((float) $item->precio_unitario, 2) }}</p>
                            <p class="text-xs font-extrabold text-blue-600 mt-1">S/ {{ number_format((float) $item->subtotal(), 2) }}</p>
                        </div>
                        <button wire:click="quitarItem({{ $item->id }})" class="text-gray-400 hover:text-rose-600 self-start p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            @else
                <div class="py-12 text-center text-gray-400">
                    <p class="text-sm">Tu carrito está vacío</p>
                    <a href="{{ route('catalogo') }}" @click="abierto = false" class="text-xs text-blue-600 font-bold mt-2 inline-block hover:underline">
                        Explorar catálogo
                    </a>
                </div>
            @endif
        </div>

        <!-- Footer Drawer -->
        @if($carrito && $carrito->items->isNotEmpty())
            <div class="p-4 border-t border-gray-100 bg-gray-50 space-y-3">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 font-medium">Subtotal</span>
                    <span class="font-extrabold text-gray-900 text-base">S/ {{ number_format($total, 2) }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('carrito') }}" @click="abierto = false" class="text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2.5 rounded-xl text-xs transition">
                        Ver Carrito
                    </a>
                    <a href="{{ route('checkout') }}" @click="abierto = false" class="text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md shadow-blue-500/20">
                        Finalizar Compra
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
