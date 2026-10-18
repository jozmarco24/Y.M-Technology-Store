<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $pendientes = \App\Models\ComprobanteElectronico::where('estado', \App\Enums\EstadoComprobante::PENDIENTE)->count();
            $errores = \App\Models\ComprobanteElectronico::where('estado', \App\Enums\EstadoComprobante::ERROR)->count();
            $rechazados = \App\Models\ComprobanteElectronico::where('estado', \App\Enums\EstadoComprobante::RECHAZADO)->count();
        @endphp

        <!-- CONTADORES RESUMEN -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">Pendientes de Envío</span>
                    <div class="text-2xl font-bold font-mono text-amber-500">{{ $pendientes }}</div>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl text-amber-500">
                    <x-heroicon-o-clock class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">Con Error (Reintentables)</span>
                    <div class="text-2xl font-bold font-mono text-orange-500">{{ $errores }}</div>
                </div>
                <div class="p-3 bg-orange-50 dark:bg-orange-950/40 rounded-xl text-orange-500">
                    <x-heroicon-o-arrow-path class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">Rechazados por SUNAT</span>
                    <div class="text-2xl font-bold font-mono text-rose-500">{{ $rechazados }}</div>
                </div>
                <div class="p-3 bg-rose-50 dark:bg-rose-950/40 rounded-xl text-rose-500">
                    <x-heroicon-o-x-circle class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
