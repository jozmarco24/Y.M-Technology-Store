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
        $cierres = $this->diferenciasCierre;
        $movimientos = $this->movimientosPorConcepto;
        $anulaciones = $this->anulacionesPorUsuario;
    @endphp

    {{-- Resumen de Movimientos Manuales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Total Ingresos Manuales</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">S/ {{ number_format($movimientos['total_ingresos'], 2) }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Total Egresos Manuales</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">S/ {{ number_format($movimientos['total_egresos'], 2) }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Balance Neto de Caja</div>
            <div class="text-2xl font-bold {{ $movimientos['balance_neto'] >= 0 ? 'text-blue-600' : 'text-amber-600' }} mt-1">
                S/ {{ number_format($movimientos['balance_neto'], 2) }}
            </div>
        </div>
    </div>

    {{-- Diferencias de Cierre de Caja --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
            <x-heroicon-m-scale class="w-5 h-5 text-indigo-600" />
            Auditoría de Diferencias en Cierres de Sesión
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th class="py-2 px-3">Vendedor</th>
                        <th class="py-2 px-3">Apertura</th>
                        <th class="py-2 px-3">Cierre</th>
                        <th class="py-2 px-3 text-right">M. Apertura</th>
                        <th class="py-2 px-3 text-right">M. Esperado</th>
                        <th class="py-2 px-3 text-right">M. Real</th>
                        <th class="py-2 px-3 text-right">Diferencia</th>
                        <th class="py-2 px-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($cierres as $c)
                        <tr class="{{ $c['tipo_diferencia'] === 'faltante' ? 'bg-rose-50/40 dark:bg-rose-950/20' : '' }}">
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $c['vendedor'] }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $c['abierta_en'] }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $c['cerrada_en'] }}</td>
                            <td class="py-2 px-3 text-right">S/ {{ number_format($c['monto_apertura'], 2) }}</td>
                            <td class="py-2 px-3 text-right font-medium">S/ {{ number_format($c['monto_esperado'], 2) }}</td>
                            <td class="py-2 px-3 text-right font-medium">S/ {{ number_format($c['monto_real'], 2) }}</td>
                            <td class="py-2 px-3 text-right font-bold {{ $c['diferencia'] < 0 ? 'text-red-600' : ($c['diferencia'] > 0 ? 'text-emerald-600' : 'text-gray-700') }}">
                                S/ {{ number_format($c['diferencia'], 2) }}
                            </td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-bold {{ $c['tipo_diferencia'] === 'cuadrada' ? 'bg-emerald-100 text-emerald-800' : ($c['tipo_diferencia'] === 'faltante' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ ucfirst($c['tipo_diferencia']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 text-center text-gray-400">
                                Sin cierres de caja en el periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Anulaciones de Venta por Usuario --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
            <x-heroicon-m-x-circle class="w-5 h-5 text-rose-600" />
            Anulaciones de Venta por Usuario
        </h3>
        <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="py-1.5 px-2">Usuario</th>
                    <th class="py-1.5 px-2 text-center">Total Anulaciones</th>
                    <th class="py-1.5 px-2 text-right">Monto Total Anulado (S/)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($anulaciones as $an)
                    <tr>
                        <td class="py-1.5 px-2 font-semibold text-gray-900 dark:text-white">{{ $an['usuario'] }}</td>
                        <td class="py-1.5 px-2 text-center font-bold text-rose-600">{{ $an['total_anulaciones'] }}</td>
                        <td class="py-1.5 px-2 text-right font-bold text-rose-600">S/ {{ number_format($an['total_monto_anulado'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-3 text-center text-gray-400">Sin ventas anuladas en el periodo</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
