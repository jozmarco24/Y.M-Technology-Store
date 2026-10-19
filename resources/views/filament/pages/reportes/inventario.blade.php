<x-filament-panels::page>
    <div class="flex justify-end gap-2 mb-2">
        <x-filament::button wire:click="crearCompraBorrador" color="warning" icon="heroicon-m-plus-circle">
            Crear compra borrador con reposición
        </x-filament::button>
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
        $valor = $this->valorizacion;
        $deadStock = $this->stockMuerto;
        $reposicion = $this->reposicionSugerida;
        $abc = $this->clasificacionAbc;
        $seriesAntiguas = $this->seriesAntiguas;
    @endphp

    {{-- Valorización General del Inventario --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-circle-stack class="w-5 h-5 text-indigo-600" />
                Valorización por Categoría
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-1.5 px-2">Categoría</th>
                            <th class="py-1.5 px-2 text-center">Unidades</th>
                            @if ($this->puedeVerMargen())
                                <th class="py-1.5 px-2 text-right">Valor Total (S/)</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($valor['por_categoria'] as $c)
                            <tr>
                                <td class="py-1.5 px-2 font-medium">{{ $c['categoria'] }}</td>
                                <td class="py-1.5 px-2 text-center font-bold">{{ $c['unidades'] }}</td>
                                @if ($this->puedeVerMargen())
                                    <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($c['valor_total'] ?? 0, 2) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-tag class="w-5 h-5 text-indigo-600" />
                Valorización por Marca
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-1.5 px-2">Marca</th>
                            <th class="py-1.5 px-2 text-center">Unidades</th>
                            @if ($this->puedeVerMargen())
                                <th class="py-1.5 px-2 text-right">Valor Total (S/)</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($valor['por_marca'] as $m)
                            <tr>
                                <td class="py-1.5 px-2 font-medium">{{ $m['marca'] }}</td>
                                <td class="py-1.5 px-2 text-center font-bold">{{ $m['unidades'] }}</td>
                                @if ($this->puedeVerMargen())
                                    <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($m['valor_total'] ?? 0, 2) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Reposición Sugerida --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-1.5">
            <x-heroicon-m-arrow-path class="w-5 h-5 text-amber-600" />
            Sugerencia de Reposición (Cobertura deseada: 30 días)
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 uppercase">
                    <tr>
                        <th class="py-2 px-3">Producto</th>
                        <th class="py-2 px-3">SKU</th>
                        <th class="py-2 px-3">Categoría</th>
                        <th class="py-2 px-3 text-center">Stock Actual</th>
                        <th class="py-2 px-3 text-center">Stock Mínimo</th>
                        <th class="py-2 px-3 text-center">Prom. Diario</th>
                        <th class="py-2 px-3 text-center">Cobertura (Días)</th>
                        <th class="py-2 px-3 text-center font-bold text-amber-800">Cantidad Sugerida</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($reposicion as $item)
                        <tr>
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $item['producto'] }}</td>
                            <td class="py-2 px-3">{{ $item['sku'] }}</td>
                            <td class="py-2 px-3">{{ $item['categoria'] }}</td>
                            <td class="py-2 px-3 text-center">{{ $item['stock_actual'] }}</td>
                            <td class="py-2 px-3 text-center">{{ $item['stock_minimo'] }}</td>
                            <td class="py-2 px-3 text-center">{{ $item['promedio_diario_ventas'] }}</td>
                            <td class="py-2 px-3 text-center font-bold {{ $item['dias_cobertura_actual'] < 10 ? 'text-red-600' : 'text-gray-700' }}">
                                {{ $item['dias_cobertura_actual'] }} d
                            </td>
                            <td class="py-2 px-3 text-center font-bold text-amber-600 text-sm">
                                +{{ $item['cantidad_sugerida'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 text-center text-gray-400">
                                Todo el inventario se encuentra dentro de los niveles óptimos de cobertura.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stock Muerto & Clasificación ABC --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Stock Muerto --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-rose-700 dark:text-rose-400 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-clock class="w-5 h-5 text-rose-600" />
                Stock Muerto (Sin ventas en > 90 días)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200">
                        <tr>
                            <th class="py-1.5 px-2">Producto</th>
                            <th class="py-1.5 px-2 text-center">Stock</th>
                            <th class="py-1.5 px-2 text-center">Última Venta</th>
                            @if ($this->puedeVerMargen())
                                <th class="py-1.5 px-2 text-right">Capital Inmovilizado</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($deadStock as $ds)
                            <tr>
                                <td class="py-1.5 px-2 font-medium truncate max-w-[150px]">{{ $ds['producto'] }}</td>
                                <td class="py-1.5 px-2 text-center font-bold">{{ $ds['stock_disponible'] }}</td>
                                <td class="py-1.5 px-2 text-center">{{ $ds['ultima_venta_en'] }}</td>
                                @if ($this->puedeVerMargen())
                                    <td class="py-1.5 px-2 text-right font-bold text-rose-600">
                                        S/ {{ number_format($ds['capital_inmovilizado'] ?? 0, 2) }}
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin productos en stock muerto</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Clasificación ABC --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-blue-700 dark:text-blue-400 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-chart-pie class="w-5 h-5 text-blue-600" />
                Clasificación ABC (Principio de Pareto)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200">
                        <tr>
                            <th class="py-1.5 px-2">Producto</th>
                            <th class="py-1.5 px-2 text-center">Clase</th>
                            <th class="py-1.5 px-2 text-right">Venta Neta</th>
                            <th class="py-1.5 px-2 text-right">Acumulado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($abc as $item)
                            <tr>
                                <td class="py-1.5 px-2 font-medium truncate max-w-[150px]">{{ $item['producto'] }}</td>
                                <td class="py-1.5 px-2 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $item['clasificacion'] === 'A' ? 'bg-emerald-100 text-emerald-800' : ($item['clasificacion'] === 'B' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ $item['clasificacion'] }}
                                    </span>
                                </td>
                                <td class="py-1.5 px-2 text-right">S/ {{ number_format($item['total_venta_neta'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right font-medium">{{ $item['porcentaje_acumulado'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin ventas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
