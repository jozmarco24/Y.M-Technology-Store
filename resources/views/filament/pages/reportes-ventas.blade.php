<x-filament-panels::page>
    <x-filament-panels::form wire:submit="aplicarFiltro">
        {{ $this->form }}

        <div class="flex items-center justify-between mt-2">
            <x-filament::button type="submit" color="primary">
                Filtrar Resultados
            </x-filament::button>

            <x-filament::button wire:click="exportarCsv" color="success" icon="heroicon-o-arrow-down-tray">
                Exportar a Excel / CSV
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @php
        $data = $this->getViewData();
    @endphp

    <!-- SECCIÓN MARGEN (SOLO ADMIN) -->
    @if ($data['esAdmin'] && $data['margen'])
        <div class="mt-4">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">
                Rentabilidad y Margen del Período (Exclusivo Administrador)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Total Ventas Facturadas</span>
                    <div class="text-xl font-bold text-primary-600 mt-1 font-mono">
                        S/ {{ number_format((float)$data['margen']['total_ventas'], 2) }}
                    </div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Costo Total de Productos</span>
                    <div class="text-xl font-bold text-gray-700 dark:text-gray-300 mt-1 font-mono">
                        S/ {{ number_format((float)$data['margen']['total_costo'], 2) }}
                    </div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Margen Bruto (Utilidad)</span>
                    <div class="text-xl font-bold text-emerald-600 mt-1 font-mono">
                        S/ {{ number_format((float)$data['margen']['margen_bruto'], 2) }}
                    </div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Porcentaje de Margen</span>
                    <div class="text-xl font-bold text-emerald-600 mt-1 font-mono">
                        {{ $data['margen']['porcentaje_margen'] }} %
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
        <!-- VENTAS POR VENDEDOR -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3 flex items-center space-x-2">
                <span>Ventas por Vendedor</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="pb-2">Vendedor</th>
                            <th class="pb-2 text-center">Transacciones</th>
                            <th class="pb-2 text-right">Total Vendido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-mono">
                        @forelse ($data['ventasPorVendedor'] as $row)
                            <tr>
                                <td class="py-2.5 font-sans font-medium text-gray-800 dark:text-gray-200">{{ $row['vendedor'] }}</td>
                                <td class="py-2.5 text-center">{{ $row['cantidad_ventas'] }}</td>
                                <td class="py-2.5 text-right font-bold text-primary-600">S/ {{ number_format((float)$row['total_vendido'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-400">Sin ventas en este rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VENTAS POR MÉTODO DE PAGO -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3 flex items-center space-x-2">
                <span>Ventas por Método de Pago</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="pb-2">Método</th>
                            <th class="pb-2 text-center">Operaciones</th>
                            <th class="pb-2 text-right">Total Recaudado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-mono">
                        @forelse ($data['ventasPorMetodo'] as $row)
                            <tr>
                                <td class="py-2.5 font-sans font-medium text-gray-800 dark:text-gray-200">{{ $row['etiqueta'] }}</td>
                                <td class="py-2.5 text-center">{{ $row['cantidad'] }}</td>
                                <td class="py-2.5 text-right font-bold text-emerald-600">S/ {{ number_format((float)$row['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-400">Sin pagos en este rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PRODUCTOS MÁS VENDIDOS -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm mt-4">
        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">
            Top 10 Productos Más Vendidos
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                        <th class="pb-2">#</th>
                        <th class="pb-2">Producto</th>
                        <th class="pb-2 text-center">Unidades Vendidas</th>
                        <th class="pb-2 text-right">Recaudación Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-mono">
                    @forelse ($data['topProductos'] as $idx => $prod)
                        <tr>
                            <td class="py-2.5 text-gray-400">{{ $idx + 1 }}</td>
                            <td class="py-2.5 font-sans font-medium text-gray-800 dark:text-gray-200">{{ $prod['producto'] }}</td>
                            <td class="py-2.5 text-center font-bold">{{ $prod['cantidad_vendida'] }}</td>
                            <td class="py-2.5 text-right font-bold text-primary-600">S/ {{ number_format((float)$prod['total_recaudado'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">Sin datos de productos vendidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
