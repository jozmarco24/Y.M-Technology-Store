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
        @endif
    </div>

    @php
        $top = $this->topRentabilidad;
        $margenProductos = $this->margenPorProducto;
        $bajoMargen = $this->ventasBajoMargen;
    @endphp

    {{-- Top 10 Más y Menos Rentables --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Más Rentables --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-arrow-trending-up class="w-5 h-5 text-emerald-600" />
                Top 10 Productos Más Rentables
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300">
                        <tr>
                            <th class="py-1.5 px-2">Producto</th>
                            <th class="py-1.5 px-2 text-right">Venta Neta</th>
                            <th class="py-1.5 px-2 text-right">Margen (S/)</th>
                            <th class="py-1.5 px-2 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($top['mas_rentables'] as $item)
                            <tr>
                                <td class="py-1.5 px-2 font-medium truncate max-w-[180px]">{{ $item['producto_nombre'] }}</td>
                                <td class="py-1.5 px-2 text-right">S/ {{ number_format($item['venta_neta_sin_igv'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold text-emerald-600">S/ {{ number_format($item['margen_monto'] ?? 0, 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold">{{ $item['margen_porcentaje'] ?? 0 }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin datos registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Menos Rentables --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-arrow-trending-down class="w-5 h-5 text-amber-600" />
                Top 10 Menos Rentables (o menor margen)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300">
                        <tr>
                            <th class="py-1.5 px-2">Producto</th>
                            <th class="py-1.5 px-2 text-right">Venta Neta</th>
                            <th class="py-1.5 px-2 text-right">Margen (S/)</th>
                            <th class="py-1.5 px-2 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($top['menos_rentables'] as $item)
                            <tr>
                                <td class="py-1.5 px-2 font-medium truncate max-w-[180px]">{{ $item['producto_nombre'] }}</td>
                                <td class="py-1.5 px-2 text-right">S/ {{ number_format($item['venta_neta_sin_igv'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold text-amber-600">S/ {{ number_format($item['margen_monto'] ?? 0, 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold">{{ $item['margen_porcentaje'] ?? 0 }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin datos registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Ventas Bajo Costo o Margen Crítico --}}
    @if (!empty($bajoMargen))
        <div class="p-4 bg-white border border-rose-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-rose-900">
            <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-shield-exclamation class="w-5 h-5 text-rose-600" />
                Ventas Bajo Costo o con Margen Bajo (≤ 10%)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200">
                        <tr>
                            <th class="py-1.5 px-2">Venta</th>
                            <th class="py-1.5 px-2">Fecha</th>
                            <th class="py-1.5 px-2">Vendedor</th>
                            <th class="py-1.5 px-2">Producto</th>
                            <th class="py-1.5 px-2 text-center">Cant.</th>
                            <th class="py-1.5 px-2 text-right">P. Venta</th>
                            <th class="py-1.5 px-2 text-right">Costo Unit.</th>
                            <th class="py-1.5 px-2 text-right">Margen (S/)</th>
                            <th class="py-1.5 px-2 text-right">Margen (%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($bajoMargen as $vm)
                            <tr class="{{ $vm['es_bajo_costo'] ? 'bg-rose-50/50 dark:bg-rose-950/20' : '' }}">
                                <td class="py-1.5 px-2 font-bold text-blue-600">{{ $vm['venta_codigo'] }}</td>
                                <td class="py-1.5 px-2">{{ $vm['fecha_venta'] }}</td>
                                <td class="py-1.5 px-2">{{ $vm['vendedor'] }}</td>
                                <td class="py-1.5 px-2 font-medium">{{ $vm['producto'] }}</td>
                                <td class="py-1.5 px-2 text-center">{{ $vm['cantidad'] }}</td>
                                <td class="py-1.5 px-2 text-right">S/ {{ number_format($vm['precio_unitario'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right">S/ {{ number_format($vm['costo_unitario'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold {{ $vm['margen_monto'] < 0 ? 'text-red-600' : 'text-amber-600' }}">
                                    S/ {{ number_format($vm['margen_monto'], 2) }}
                                </td>
                                <td class="py-1.5 px-2 text-right font-bold {{ $vm['margen_porcentaje'] < 0 ? 'text-red-600' : 'text-amber-600' }}">
                                    {{ $vm['margen_porcentaje'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Tabla Completa por Producto --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">Rentabilidad Detallada por Producto</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 px-3">Producto</th>
                        <th class="py-2 px-3">SKU</th>
                        <th class="py-2 px-3">Categoría</th>
                        <th class="py-2 px-3">Marca</th>
                        <th class="py-2 px-3 text-center">Unidades</th>
                        <th class="py-2 px-3 text-right">Venta Neta</th>
                        <th class="py-2 px-3 text-right">Costo Total</th>
                        <th class="py-2 px-3 text-right">Margen (S/)</th>
                        <th class="py-2 px-3 text-right">Margen (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($margenProductos as $p)
                        <tr>
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $p['producto_nombre'] }}</td>
                            <td class="py-2 px-3">{{ $p['sku'] }}</td>
                            <td class="py-2 px-3">{{ $p['categoria'] }}</td>
                            <td class="py-2 px-3">{{ $p['marca'] }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ $p['unidades_vendidas'] }}</td>
                            <td class="py-2 px-3 text-right">S/ {{ number_format($p['venta_neta_sin_igv'], 2) }}</td>
                            <td class="py-2 px-3 text-right">S/ {{ number_format($p['costo_total'] ?? 0, 2) }}</td>
                            <td class="py-2 px-3 text-right font-bold text-emerald-600">S/ {{ number_format($p['margen_monto'] ?? 0, 2) }}</td>
                            <td class="py-2 px-3 text-right font-bold">{{ $p['margen_porcentaje'] ?? 0 }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 text-center text-gray-400">Sin datos registrados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
