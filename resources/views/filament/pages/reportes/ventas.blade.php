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
        $met = $this->metricas;
        $canalInfo = $this->canalPosVsWeb;
        $horas = $this->horasPico;
        $metodos = $this->ventasPorMetodoPago;
        $clientes = $this->clientesAnalisis;
    @endphp

    {{-- Métricas Clave --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Total Pedidos</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $met['total_pedidos'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $met['total_unidades'] }} unidades despachadas</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Total Facturado</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">S/ {{ number_format($met['total_facturado'], 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Venta Neta: S/ {{ number_format($met['venta_neta'], 2) }}</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Ticket Promedio</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">S/ {{ number_format($met['ticket_promedio'], 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $met['unidades_por_venta'] }} un./pedido</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Devoluciones</div>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">S/ {{ number_format($met['total_devoluciones'], 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Descuentos: S/ {{ number_format($met['total_descuentos'], 2) }}</div>
        </div>
    </div>

    {{-- Canal POS vs Web & Métodos de Pago --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Canal POS vs Web --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-arrows-right-left class="w-5 h-5 text-blue-600" />
                Desempeño por Canal (POS vs Web)
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1.5 px-2">Canal</th>
                        <th class="py-1.5 px-2 text-center">Pedidos</th>
                        <th class="py-1.5 px-2 text-right">Monto Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td class="py-1.5 px-2 font-medium">Tienda Física (POS)</td>
                        <td class="py-1.5 px-2 text-center font-bold">{{ $canalInfo['pos']['pedidos'] }}</td>
                        <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($canalInfo['pos']['monto'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-1.5 px-2 font-medium">Tienda Virtual (Web)</td>
                        <td class="py-1.5 px-2 text-center font-bold">{{ $canalInfo['web']['pedidos'] }}</td>
                        <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($canalInfo['web']['monto'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 text-xs">
                <div class="font-bold text-gray-700 dark:text-gray-300 mb-1">Embudo de Conversión Web</div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded">
                        <div class="text-gray-500 text-[10px]">Iniciados</div>
                        <div class="font-bold">{{ $canalInfo['web']['pedidos_totales_iniciados'] }}</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded">
                        <div class="text-gray-500 text-[10px]">Pagados</div>
                        <div class="font-bold text-emerald-600">{{ $canalInfo['web']['pedidos_pagados'] }}</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded">
                        <div class="text-gray-500 text-[10px]">Conversión</div>
                        <div class="font-bold text-blue-600">{{ $canalInfo['web']['tasa_conversion_porcentaje'] }}%</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Métodos de Pago --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-credit-card class="w-5 h-5 text-indigo-600" />
                Ventas por Método de Pago
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1.5 px-2">Método</th>
                        <th class="py-1.5 px-2 text-center">Transacciones</th>
                        <th class="py-1.5 px-2 text-right">Total (S/)</th>
                        <th class="py-1.5 px-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($metodos as $m)
                        <tr>
                            <td class="py-1.5 px-2 font-medium capitalize">{{ $m['metodo'] }}</td>
                            <td class="py-1.5 px-2 text-center font-bold">{{ $m['cantidad_transacciones'] }}</td>
                            <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($m['total_monto'], 2) }}</td>
                            <td class="py-1.5 px-2 text-right font-bold text-indigo-600">{{ $m['participacion_porcentaje'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin pagos registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Horas Pico & Top Clientes --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Horas Pico --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-clock class="w-5 h-5 text-purple-600" />
                Distribución por Día de la Semana
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1.5 px-2">Día</th>
                        <th class="py-1.5 px-2 text-center">Ventas</th>
                        <th class="py-1.5 px-2 text-right">Monto Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($horas['por_dia_semana'] as $dia)
                        <tr>
                            <td class="py-1.5 px-2 font-medium">{{ $dia['dia_nombre'] }}</td>
                            <td class="py-1.5 px-2 text-center font-bold">{{ $dia['total_ventas'] }}</td>
                            <td class="py-1.5 px-2 text-right font-medium">S/ {{ number_format($dia['monto_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Top Clientes --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                <x-heroicon-m-user-group class="w-5 h-5 text-emerald-600" />
                Top Clientes del Periodo
            </h3>
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-1.5 px-2">Cliente</th>
                        <th class="py-1.5 px-2">Doc.</th>
                        <th class="py-1.5 px-2 text-center">Compras</th>
                        <th class="py-1.5 px-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($clientes['top_clientes'] as $cli)
                        <tr>
                            <td class="py-1.5 px-2 font-medium truncate max-w-[150px]">{{ $cli['nombre'] }}</td>
                            <td class="py-1.5 px-2">{{ $cli['documento'] }}</td>
                            <td class="py-1.5 px-2 text-center font-bold">{{ $cli['total_compras'] }}</td>
                            <td class="py-1.5 px-2 text-right font-bold text-emerald-600">S/ {{ number_format($cli['total_gastado'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-center text-gray-400">Sin datos de clientes</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="flex justify-between items-center text-xs text-gray-500 mt-3 pt-2 border-t">
                <span>Clientes nuevos: <strong>{{ $clientes['clientes_nuevos'] }}</strong></span>
                <span>Clientes recurrentes: <strong>{{ $clientes['clientes_recurrentes'] }}</strong></span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
