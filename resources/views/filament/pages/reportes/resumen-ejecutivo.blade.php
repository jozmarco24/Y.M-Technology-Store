<x-filament-panels::page>
    <form wire:submit.prevent="aplicarFiltros">
        {{ $this->form }}
    </form>

    <div class="flex justify-end gap-2 my-2">
        @if ($this->puedeExportar())
            <x-filament::button wire:click="exportarCsv" color="gray" icon="heroicon-m-arrow-down-tray">
                Exportar CSV
            </x-filament::button>
            <x-filament::button wire:click="exportarExcel" color="success" icon="heroicon-m-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button wire:click="exportarPdf" color="danger" icon="heroicon-m-document-text">
                Exportar PDF
            </x-filament::button>
        @endif
    </div>

    @php
        $resumen = $this->resumen;
    @endphp

    {{-- Alertas Operativas --}}
    @if (!empty($resumen['alertas']))
        <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-lg dark:bg-red-950/40 dark:border-red-600">
            <h3 class="text-sm font-bold text-red-800 dark:text-red-300 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-red-500" />
                Alertas y Riesgos Operativos
            </h3>
            <ul class="space-y-1 text-sm text-red-700 dark:text-red-400">
                @foreach ($resumen['alertas'] as $alerta)
                    <li class="flex items-start gap-2">
                        <span class="text-red-500 font-bold">&bull;</span>
                        <span>{{ $alerta }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tarjetas Principales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Venta Neta --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Ventas Netas</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                S/ {{ number_format($resumen['ventas']['venta_neta'], 2) }}
            </div>
            <div class="flex items-center gap-1 mt-2 text-xs {{ $resumen['ventas']['variacion_ventas_porcentaje'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                @if ($resumen['ventas']['variacion_ventas_porcentaje'] >= 0)
                    <x-heroicon-m-arrow-trending-up class="w-4 h-4" />
                    <span>+{{ $resumen['ventas']['variacion_ventas_porcentaje'] }}% vs anterior</span>
                @else
                    <x-heroicon-m-arrow-trending-down class="w-4 h-4" />
                    <span>{{ $resumen['ventas']['variacion_ventas_porcentaje'] }}% vs anterior</span>
                @endif
            </div>
        </div>

        {{-- Margen Bruto (si tiene permiso) --}}
        @if (isset($resumen['rentabilidad']))
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Margen Bruto</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                    S/ {{ number_format($resumen['rentabilidad']['margen_bruto'], 2) }}
                </div>
                <div class="flex items-center gap-1 mt-2 text-xs {{ $resumen['rentabilidad']['variacion_margen_porcentaje'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    @if ($resumen['rentabilidad']['variacion_margen_porcentaje'] >= 0)
                        <x-heroicon-m-arrow-trending-up class="w-4 h-4" />
                        <span>+{{ $resumen['rentabilidad']['variacion_margen_porcentaje'] }}% vs anterior</span>
                    @else
                        <x-heroicon-m-arrow-trending-down class="w-4 h-4" />
                        <span>{{ $resumen['rentabilidad']['variacion_margen_porcentaje'] }}% vs anterior</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Ticket Promedio --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Ticket Promedio</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                S/ {{ number_format($resumen['ventas']['ticket_promedio'], 2) }}
            </div>
            <div class="text-xs text-gray-500 mt-2">
                {{ $resumen['ventas']['total_pedidos'] }} pedidos completados
            </div>
        </div>

        {{-- Postventa / Devoluciones --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Devoluciones</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                S/ {{ number_format($resumen['postventa']['total_devuelto'], 2) }}
            </div>
            <div class="text-xs text-gray-500 mt-2">
                Tasa de devolución: {{ $resumen['postventa']['tasa_devolucion_porcentaje'] }}%
            </div>
        </div>
    </div>

    {{-- Top Productos --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">Top 5 Productos Vendidos</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border-b">
                    <tr>
                        <th class="py-2 px-3">Producto</th>
                        <th class="py-2 px-3">SKU</th>
                        <th class="py-2 px-3">Categoría</th>
                        <th class="py-2 px-3 text-center">Unidades</th>
                        <th class="py-2 px-3 text-right">Venta Neta</th>
                        @if ($this->puedeVerMargen())
                            <th class="py-2 px-3 text-right">Margen</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($resumen['top_productos'] as $prod)
                        <tr>
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $prod['producto_nombre'] }}</td>
                            <td class="py-2 px-3">{{ $prod['sku'] }}</td>
                            <td class="py-2 px-3">{{ $prod['categoria'] }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ $prod['unidades_vendidas'] }}</td>
                            <td class="py-2 px-3 text-right font-medium">S/ {{ number_format($prod['venta_neta_sin_igv'], 2) }}</td>
                            @if ($this->puedeVerMargen())
                                <td class="py-2 px-3 text-right text-emerald-600 dark:text-emerald-400 font-bold">
                                    S/ {{ number_format($prod['margen_monto'] ?? 0, 2) }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->puedeVerMargen() ? 6 : 5 }}" class="py-4 text-center text-gray-400">
                                Sin ventas registradas en el periodo seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
