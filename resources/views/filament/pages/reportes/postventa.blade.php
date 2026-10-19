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
        $fallas = $this->tasaFallas;
        $tiempos = $this->tiemposReparacion;
        $devs = $this->devolucionesAnalisis;
        $garantiasVencer = $this->garantiasPorVencer;
    @endphp

    {{-- Métricas Clave de Servicio Técnico --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Tiempo Promedio Reparación</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $tiempos['tiempo_promedio_dias'] }} días</div>
            <div class="text-xs text-gray-500 mt-1">{{ $tiempos['total_ordenes_entregadas'] }} equipos entregados</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Órdenes Retrasadas</div>
            <div class="text-2xl font-bold {{ $tiempos['total_ordenes_retrasadas'] > 0 ? 'text-red-600' : 'text-gray-900' }} mt-1">
                {{ $tiempos['total_ordenes_retrasadas'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Superan fecha promesa</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Tasa de Devoluciones</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $devs['tasa_devolucion_porcentaje'] }}%</div>
            <div class="text-xs text-gray-500 mt-1">S/ {{ number_format($devs['total_monto_devuelto'], 2) }} reembolsados</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Garantías por Vencer</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ count($garantiasVencer) }}</div>
            <div class="text-xs text-gray-500 mt-1">Próximos 30 días calendario</div>
        </div>
    </div>

    {{-- Tasa de Fallas por Modelo --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
            <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-rose-600" />
            Tasa de Fallas por Modelo y Producto
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th class="py-2 px-3">Producto</th>
                        <th class="py-2 px-3">SKU</th>
                        <th class="py-2 px-3">Marca</th>
                        <th class="py-2 px-3">Categoría</th>
                        <th class="py-2 px-3 text-center">Unidades Vendidas</th>
                        <th class="py-2 px-3 text-center">Órdenes de Servicio</th>
                        <th class="py-2 px-3 text-right">Tasa de Fallas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($fallas as $f)
                        <tr class="{{ $f['tasa_fallas_porcentaje'] > 10 ? 'bg-rose-50/50 dark:bg-rose-950/20' : '' }}">
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $f['producto'] }}</td>
                            <td class="py-2 px-3">{{ $f['sku'] }}</td>
                            <td class="py-2 px-3">{{ $f['marca'] }}</td>
                            <td class="py-2 px-3">{{ $f['categoria'] }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ $f['unidades_vendidas'] }}</td>
                            <td class="py-2 px-3 text-center font-bold text-rose-600">{{ $f['ordenes_servicio'] }}</td>
                            <td class="py-2 px-3 text-right font-bold {{ $f['tasa_fallas_porcentaje'] > 5 ? 'text-rose-600' : 'text-gray-700' }}">
                                {{ $f['tasa_fallas_porcentaje'] }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-gray-400">
                                Sin órdenes de servicio ni reclamos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Devoluciones por Motivo & Órdenes Retrasadas --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Devoluciones por Motivo --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-arrow-uturn-left class="w-5 h-5 text-amber-600" />
                Devoluciones por Motivo
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1.5 px-2">Motivo</th>
                        <th class="py-1.5 px-2 text-center">Cantidad</th>
                        <th class="py-1.5 px-2 text-right">Total Devuelto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($devs['por_motivo'] as $m)
                        <tr>
                            <td class="py-1.5 px-2 font-medium capitalize">{{ str_replace('_', ' ', $m['motivo']) }}</td>
                            <td class="py-1.5 px-2 text-center font-bold">{{ $m['cantidad'] }}</td>
                            <td class="py-1.5 px-2 text-right font-bold text-rose-600">S/ {{ number_format($m['monto_devuelto'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-center text-gray-400">Sin devoluciones en el periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Órdenes Retrasadas --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-red-700 dark:text-red-400 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-clock class="w-5 h-5 text-red-600" />
                Órdenes de Servicio Retrasadas
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-red-50 dark:bg-red-950/40 text-red-900 dark:text-red-200">
                    <tr>
                        <th class="py-1.5 px-2">Código</th>
                        <th class="py-1.5 px-2">Cliente</th>
                        <th class="py-1.5 px-2">Promesa</th>
                        <th class="py-1.5 px-2 text-right">Retraso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($tiempos['ordenes_retrasadas'] as $ord)
                        <tr>
                            <td class="py-1.5 px-2 font-bold text-blue-600">{{ $ord['codigo'] }}</td>
                            <td class="py-1.5 px-2 font-medium truncate max-w-[120px]">{{ $ord['cliente'] }}</td>
                            <td class="py-1.5 px-2 text-gray-500">{{ $ord['fecha_promesa'] }}</td>
                            <td class="py-1.5 px-2 text-right font-bold text-red-600">{{ $ord['dias_retraso'] }} días</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin órdenes retrasadas actualmente</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
