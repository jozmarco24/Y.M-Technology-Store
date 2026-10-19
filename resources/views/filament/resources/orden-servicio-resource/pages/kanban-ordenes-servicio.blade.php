<x-filament-panels::page>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Arrastra las tarjetas o utiliza los selectores rápidos para actualizar el estado del equipo en tiempo real.
        </p>
        <a href="{{ route('filament.admin.resources.orden-servicios.index') }}" class="text-sm font-medium text-primary-600 hover:underline">
            &larr; Volver a la lista detallada
        </a>
    </div>

    @php
        $ordenesAgrupadas = $this->getOrdenesPorEstado();
        $columnas = $this->columnas;
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 overflow-x-auto pb-6">
        @foreach($columnas as $estadoKey => $col)
            @php
                $items = $ordenesAgrupadas[$estadoKey] ?? collect();
            @endphp
            <div class="flex flex-col bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3 min-w-[240px]">
                <!-- Cabecera de Columna -->
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-bold text-sm text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-{{ $col['color'] }}-500"></span>
                        {{ $col['titulo'] }}
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        {{ $items->count() }}
                    </span>
                </div>

                <!-- Tarjetas -->
                <div class="flex flex-col gap-3 min-h-[300px]">
                    @forelse($items as $orden)
                        <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition">
                            <div class="flex justify-between items-start mb-1">
                                <a href="{{ route('filament.admin.resources.orden-servicios.view', $orden) }}" class="font-bold text-xs text-primary-600 hover:underline">
                                    {{ $orden->codigo }}
                                </a>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $orden->esGarantia() ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                                    {{ $orden->tipo->etiqueta() }}
                                </span>
                            </div>

                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-100 line-clamp-1 mb-1">
                                {{ $orden->producto?->nombre ?? $orden->equipo_descripcion }}
                            </p>

                            <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2 mb-2">
                                <span class="font-medium text-gray-600 dark:text-gray-300">Falla:</span> {{ $orden->falla_reportada }}
                            </p>

                            <div class="text-[10px] text-gray-400 dark:text-gray-500 mb-2">
                                Cliente: {{ Str::limit($orden->cliente?->nombre_razon_social, 18) }}
                            </div>

                            @if($orden->costo_final > 0)
                                <div class="text-xs font-bold text-gray-700 dark:text-gray-200 mb-2">
                                    S/ {{ number_format((float) $orden->costo_final, 2) }}
                                </div>
                            @endif

                            <!-- Cambio rápido de estado -->
                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-1">
                                <select 
                                    wire:change="moverEstado({{ $orden->id }}, $event.target.value)"
                                    class="text-[10px] py-1 px-1.5 rounded border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-300 w-full focus:ring-primary-500 focus:border-primary-500"
                                >
                                    <option value="" disabled selected>Mover a...</option>
                                    @foreach($columnas as $k => $c)
                                        @if($k !== $estadoKey)
                                            <option value="{{ $k }}">{{ $c['titulo'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @empty
                        <div class="flex items-center justify-center h-24 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-lg text-xs text-gray-400">
                            Sin órdenes
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
