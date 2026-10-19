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
        $ranking = $this->ranking;
        $comisiones = $this->comisiones;
        $comisionesActivas = $this->estanComisionesActivas();
    @endphp

    {{-- Tabla de Ranking de Vendedores --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
            <x-heroicon-m-trophy class="w-5 h-5 text-amber-500" />
            Ranking y Rendimiento Comercial
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th class="py-2 px-3 text-center w-12">#</th>
                        <th class="py-2 px-3">Vendedor</th>
                        <th class="py-2 px-3 text-center">Pedidos</th>
                        <th class="py-2 px-3 text-right">Total Vendido</th>
                        <th class="py-2 px-3 text-right">Ticket Promedio</th>
                        <th class="py-2 px-3 text-right">Descuentos</th>
                        <th class="py-2 px-3 text-center">Anulaciones</th>
                        @if ($this->puedeVerMargen())
                            <th class="py-2 px-3 text-right">Margen (S/)</th>
                            <th class="py-2 px-3 text-right">Margen (%)</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($ranking as $index => $v)
                        <tr>
                            <td class="py-2 px-3 text-center font-bold {{ $index === 0 ? 'text-amber-500 text-sm' : 'text-gray-400' }}">
                                {{ $index + 1 }}
                            </td>
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $v['vendedor'] }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ $v['total_pedidos'] }}</td>
                            <td class="py-2 px-3 text-right font-bold text-indigo-600">S/ {{ number_format($v['total_vendido'], 2) }}</td>
                            <td class="py-2 px-3 text-right">S/ {{ number_format($v['ticket_promedio'], 2) }}</td>
                            <td class="py-2 px-3 text-right text-amber-600">S/ {{ number_format($v['descuentos_otorgados'], 2) }}</td>
                            <td class="py-2 px-3 text-center font-bold {{ $v['ventas_anuladas'] > 0 ? 'text-rose-600' : 'text-gray-400' }}">
                                {{ $v['ventas_anuladas'] }}
                            </td>
                            @if ($this->puedeVerMargen())
                                <td class="py-2 px-3 text-right font-bold text-emerald-600">S/ {{ number_format($v['margen_total'] ?? 0, 2) }}</td>
                                <td class="py-2 px-3 text-right font-bold">{{ $v['margen_porcentaje'] ?? 0 }}%</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->puedeVerMargen() ? 9 : 7 }}" class="py-4 text-center text-gray-400">
                                Sin ventas de vendedores registradas en el periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Comisiones (si el flag está activado) --}}
    @if ($comisionesActivas && $this->puedeVerMargen() && !empty($comisiones))
        <div class="p-4 bg-white border border-emerald-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-emerald-800">
            <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5 mb-2">
                <x-heroicon-m-banknotes class="w-5 h-5 text-emerald-600" />
                Cálculo Estimado de Comisiones
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                    <thead class="bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200">
                        <tr>
                            <th class="py-1.5 px-2">Vendedor</th>
                            <th class="py-1.5 px-2">Regla Aplicada</th>
                            <th class="py-1.5 px-2 text-right">Base de Cálculo</th>
                            <th class="py-1.5 px-2 text-right">% Comisión</th>
                            <th class="py-1.5 px-2 text-right">Comisión a Pagar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($comisiones as $com)
                            <tr>
                                <td class="py-1.5 px-2 font-semibold">{{ $com['vendedor'] }}</td>
                                <td class="py-1.5 px-2 capitalize">{{ str_replace('_', ' ', $com['tipo_comision']) }}</td>
                                <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($com['base_calculo'], 2) }}</td>
                                <td class="py-1.5 px-2 text-right">{{ $com['porcentaje_aplicado'] }}%</td>
                                <td class="py-1.5 px-2 text-right font-bold text-emerald-600">S/ {{ number_format($com['monto_comision'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
