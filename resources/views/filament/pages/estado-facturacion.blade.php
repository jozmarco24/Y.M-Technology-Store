<x-filament-panels::page>
    <div class="space-y-6">
        <!-- TARJETA DE ESTADO ACTUAL -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Configuración del Entorno de Facturación</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Modo de Operación</span>
                    <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                        {{ config('facturacion.modo') === 'produccion' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' :
                           (config('facturacion.modo') === 'demo' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300') }}">
                        {{ strtoupper(config('facturacion.modo', 'simulado')) }}
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        @if (config('facturacion.modo') === 'simulado')
                            Emisión local simulada sin salida a internet ni llamadas a Nubefact. Ideal para pruebas y desarrollo.
                        @elseif (config('facturacion.modo') === 'demo')
                            Entorno de pruebas oficial de Nubefact / SUNAT (sin valor tributario real).
                        @else
                            Entorno productivo real con validez tributaria ante SUNAT.
                        @endif
                    </p>
                </div>

                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Ruta API Nubefact</span>
                    <span class="mt-1 text-sm font-mono font-medium text-gray-800 dark:text-gray-200 break-all block">
                        {{ config('facturacion.ruta') ?: '(No configurada - simulado)' }}
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Variable de entorno <code>NUBEFACT_RUTA</code>.
                    </p>
                </div>

                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Token de Autenticación</span>
                    <span class="mt-1 text-sm font-mono font-medium text-gray-800 dark:text-gray-200 block">
                        @if (config('facturacion.token'))
                            ••••••••••••••••{{ substr((string) config('facturacion.token'), -4) }}
                        @else
                            <span class="text-gray-400 italic">No configurado</span>
                        @endif
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Protegido en <code>.env</code> (nunca expuesto en código ni Git).
                    </p>
                </div>
            </div>

            <div class="mt-6 flex items-center space-x-4">
                <x-filament::button wire:click="probarConexion" icon="heroicon-o-bolt">
                    Probar Conexión con Nubefact
                </x-filament::button>
            </div>

            @if ($resultadoPrueba)
                <div class="mt-4 p-4 rounded-xl text-sm border {{ $pruebaExitosa ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200' }}">
                    <div class="font-bold mb-1">
                        {{ $pruebaExitosa ? 'Resultado: Conexión Exitosa' : 'Resultado: Error de Conexión' }}
                    </div>
                    <p class="font-mono text-xs">{{ $resultadoPrueba }}</p>
                </div>
            @endif
        </div>

        <!-- RESUMEN DE COLA Y POLÍTICA DE REINTENTOS -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">Arquitectura Asíncrona y Tolerancia a Fallos</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">
                La venta no depende de internet ni de SUNAT. El comprobante se registra localmente con su serie y correlativo garantizados, y se envía mediante la cola de trabajo <code>facturacion</code> con reintentos programados.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 block">Cola configurada:</span>
                    <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ config('facturacion.cola', 'facturacion') }}</span>
                </div>
                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 block">Máximo reintentos:</span>
                    <span class="font-bold font-mono text-gray-900 dark:text-white">{{ config('facturacion.max_intentos', 5) }} intentos</span>
                </div>
                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 block">Backoff exponencial:</span>
                    <span class="font-bold font-mono text-gray-900 dark:text-white">1m, 5m, 15m, 1h, 3h</span>
                </div>
                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 block">Scheduler automático:</span>
                    <span class="font-bold font-mono text-gray-900 dark:text-white">Cada 10 minutos</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
