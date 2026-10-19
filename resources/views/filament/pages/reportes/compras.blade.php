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
        $compras = $this->comprasPorProveedor;
        $evolucion = $this->evolucionCosto;
        $comparativo = $this->comparativoProveedores;
        $productos = \App\Models\Producto::where('activo', true)->get();
    @endphp

    {{-- Compras por Proveedor --}}
    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
            <x-heroicon-m-building-storefront class="w-5 h-5 text-indigo-600" />
            Compras Recibidas por Proveedor
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 px-3">Proveedor</th>
                        <th class="py-2 px-3">RUC / Documento</th>
                        <th class="py-2 px-3 text-center">Cantidad Compras</th>
                        @if ($this->puedeVerMargen())
                            <th class="py-2 px-3 text-right">Subtotal</th>
                            <th class="py-2 px-3 text-right">IGV (18%)</th>
                            <th class="py-2 px-3 text-right">Total Facturado</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($compras as $c)
                        <tr>
                            <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white">{{ $c['razon_social'] }}</td>
                            <td class="py-2 px-3">{{ $c['numero_documento'] }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ $c['cantidad_compras'] }}</td>
                            @if ($this->puedeVerMargen())
                                <td class="py-2 px-3 text-right font-medium">S/ {{ number_format($c['subtotal_compras'] ?? 0, 2) }}</td>
                                <td class="py-2 px-3 text-right">S/ {{ number_format($c['igv_compras'] ?? 0, 2) }}</td>
                                <td class="py-2 px-3 text-right font-bold text-indigo-600">S/ {{ number_format($c['total_compras'] ?? 0, 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->puedeVerMargen() ? 6 : 3 }}" class="py-4 text-center text-gray-400">
                                Sin compras recibidas registradas en el periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Evolución del Costo por Producto --}}
    @if ($this->puedeVerMargen())
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                    <x-heroicon-m-chart-bar class="w-5 h-5 text-emerald-600" />
                    Evolución Histórica del Costo de Compra
                </h3>
                <div class="w-full sm:w-64">
                    <select wire:model.live="productoSeleccionadoId" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach ($productos as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Historial de compras --}}
                <div>
                    <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Histórico por Compra Recibida</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-1.5 px-2">Compra</th>
                                    <th class="py-1.5 px-2">Fecha</th>
                                    <th class="py-1.5 px-2">Proveedor</th>
                                    <th class="py-1.5 px-2 text-right">Costo Unit.</th>
                                    <th class="py-1.5 px-2 text-right">Variación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($evolucion as $ev)
                                    <tr>
                                        <td class="py-1.5 px-2 font-bold text-blue-600">{{ $ev['compra_codigo'] }}</td>
                                        <td class="py-1.5 px-2">{{ $ev['fecha_compra'] }}</td>
                                        <td class="py-1.5 px-2 truncate max-w-[120px]">{{ $ev['proveedor'] }}</td>
                                        <td class="py-1.5 px-2 text-right font-bold">S/ {{ number_format($ev['costo_unitario'], 2) }}</td>
                                        <td class="py-1.5 px-2 text-right font-medium {{ $ev['variacion_porcentaje'] > 0 ? 'text-red-600' : ($ev['variacion_porcentaje'] < 0 ? 'text-green-600' : 'text-gray-500') }}">
                                            {{ $ev['variacion_porcentaje'] > 0 ? '+' : '' }}{{ $ev['variacion_porcentaje'] }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-3 text-center text-gray-400">Sin historial para este producto</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Comparativo entre proveedores --}}
                <div>
                    <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Comparativo de Costo entre Proveedores</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-1.5 px-2">Proveedor</th>
                                    <th class="py-1.5 px-2 text-center">Unidades</th>
                                    <th class="py-1.5 px-2 text-right">Mínimo</th>
                                    <th class="py-1.5 px-2 text-right">Máximo</th>
                                    <th class="py-1.5 px-2 text-right">Promedio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($comparativo as $cp)
                                    <tr>
                                        <td class="py-1.5 px-2 font-medium truncate max-w-[120px]">{{ $cp['razon_social'] }}</td>
                                        <td class="py-1.5 px-2 text-center font-bold">{{ $cp['total_unidades'] }}</td>
                                        <td class="py-1.5 px-2 text-right text-emerald-600 font-medium">S/ {{ number_format($cp['costo_minimo'], 2) }}</td>
                                        <td class="py-1.5 px-2 text-right text-rose-600 font-medium">S/ {{ number_format($cp['costo_maximo'], 2) }}</td>
                                        <td class="py-1.5 px-2 text-right font-bold">S/ {{ number_format($cp['costo_promedio'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-3 text-center text-gray-400">Sin comparativa de proveedores</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
