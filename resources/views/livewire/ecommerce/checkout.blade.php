<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-2xl font-extrabold text-gray-900 mb-8">Finalizar Compra</h1>

    <!-- STEPS HEADER (Progreso) -->
    <div class="flex items-center justify-between max-w-2xl mx-auto mb-10 text-xs font-bold">
        <div class="flex items-center gap-2 {{ $paso >= 1 ? 'text-blue-600' : 'text-gray-400' }}">
            <span class="w-7 h-7 rounded-full flex items-center justify-center border-2 {{ $paso >= 1 ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-gray-300' }}">1</span>
            <span>Contacto y Facturación</span>
        </div>
        <div class="w-12 h-0.5 bg-gray-200"></div>
        <div class="flex items-center gap-2 {{ $paso >= 2 ? 'text-blue-600' : 'text-gray-400' }}">
            <span class="w-7 h-7 rounded-full flex items-center justify-center border-2 {{ $paso >= 2 ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-gray-300' }}">2</span>
            <span>Entrega</span>
        </div>
        <div class="w-12 h-0.5 bg-gray-200"></div>
        <div class="flex items-center gap-2 {{ $paso >= 3 ? 'text-blue-600' : 'text-gray-400' }}">
            <span class="w-7 h-7 rounded-full flex items-center justify-center border-2 {{ $paso >= 3 ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-gray-300' }}">3</span>
            <span>Pago y Confirmación</span>
        </div>
    </div>

    @if($errorCheckout)
        <div class="max-w-3xl mx-auto mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
            🚨 {{ $errorCheckout }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- FORMULARIO PASO A PASO -->
        <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-gray-200">
            
            <!-- PASO 1: CONTACTO Y COMPROBANTE -->
            @if($paso === 1)
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 mb-4">1. Datos de Contacto y Comprobante</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Correo Electrónico (Para envío de comprobante y tracking)</label>
                            <input type="email" wire:model="email" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                            @error('email') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Teléfono Móvil / WhatsApp</label>
                            <input type="text" wire:model="telefono" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                            @error('telefono') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-6">
                        <label class="text-xs font-bold text-gray-700 block mb-3">Tipo de Comprobante de Pago</label>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <label class="flex items-center gap-3 p-3.5 rounded-xl border {{ $tipoComprobante === 'boleta' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                                <input type="radio" wire:model.live="tipoComprobante" value="boleta" class="text-blue-600">
                                <div>
                                    <span class="block text-xs font-bold text-gray-900">Boleta de Venta</span>
                                    <span class="block text-[11px] text-gray-500">Para consumidor final</span>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3.5 rounded-xl border {{ $tipoComprobante === 'factura' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                                <input type="radio" wire:model.live="tipoComprobante" value="factura" class="text-blue-600">
                                <div>
                                    <span class="block text-xs font-bold text-gray-900">Factura Electrónica</span>
                                    <span class="block text-[11px] text-gray-500">Exige RUC 10 o 20</span>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-gray-700 block mb-1">
                                    {{ $tipoComprobante === 'factura' ? 'RUC de la Empresa (11 dígitos)' : 'DNI / Documento' }}
                                </label>
                                <input type="text" wire:model="documentoNumero" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                                @error('documentoNumero') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            @if($tipoComprobante === 'factura')
                                <div>
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Razón Social</label>
                                    <input type="text" wire:model="razonSocial" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                                    @error('razonSocial') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Dirección Fiscal</label>
                                    <input type="text" wire:model="direccionFiscal" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                                    @error('direccionFiscal') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button wire:click="avanzarPaso" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-md">
                            Continuar a Entrega &rarr;
                        </button>
                    </div>
                </div>
            @endif

            <!-- PASO 2: ENTREGA -->
            @if($paso === 2)
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 mb-4">2. Método y Dirección de Entrega</h2>

                    <div class="space-y-3 mb-6">
                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $metodoEntrega === 'recojo_tienda' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="metodoEntrega" value="recojo_tienda" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Recojo en Tienda Principal (Gratis)</span>
                                <span class="block text-[11px] text-gray-500">Av. Wilson 1234, Lima. Disponible en 1 hora tras confirmarse el pago.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $metodoEntrega === 'delivery_lima' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="metodoEntrega" value="delivery_lima" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Delivery en Lima Metropolitana</span>
                                <span class="block text-[11px] text-gray-500">Entrega en 24 a 48 horas en tu domicilio.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $metodoEntrega === 'envio_provincia' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="metodoEntrega" value="envio_provincia" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Envío a Provincias (Olva Courier / Shalom)</span>
                                <span class="block text-[11px] text-gray-500">Entrega de 2 a 4 días con guía y tracking oficial.</span>
                            </div>
                        </label>
                    </div>

                    @if($metodoEntrega !== 'recojo_tienda')
                        <div class="border-t border-gray-100 pt-6">
                            @if($direccionesGuardadas->isNotEmpty())
                                <div class="mb-4">
                                    <label class="text-xs font-bold text-gray-700 block mb-1.5">Usar una de mis direcciones guardadas</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($direccionesGuardadas as $d)
                                            <button type="button" wire:click="seleccionarDireccion({{ $d->id }})" 
                                                    class="text-left p-3 rounded-xl border text-xs {{ $direccionId === $d->id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                                                <strong class="block text-gray-900">{{ $d->alias }}</strong>
                                                <span class="text-gray-500 block truncate">{{ $d->direccion }}, {{ $d->distrito }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Nombre del Destinatario</label>
                                    <input type="text" wire:model="destinatario" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                    @error('destinatario') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Zona o Departamento</label>
                                    <select wire:model.live="zonaEnvioId" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                        <option value="">Selecciona zona de envío...</option>
                                        @foreach($zonasEnvio as $z)
                                            <option value="{{ $z->id }}">{{ $z->nombre }} (+ S/ {{ number_format((float) $z->costo, 2) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Distrito</label>
                                    <input type="text" wire:model="distrito" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                    @error('distrito') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Dirección Exacta</label>
                                    <input type="text" wire:model="direccion" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                    @error('direccion') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-bold text-gray-700 block mb-1">Referencia</label>
                                    <input type="text" wire:model="referencia" placeholder="Frente al parque, portón blanco..." class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-8 flex justify-between">
                        <button wire:click="retrocederPaso" class="text-xs font-bold text-gray-600 hover:text-gray-900 px-4 py-2">
                            &larr; Volver
                        </button>
                        <button wire:click="avanzarPaso" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-md">
                            Continuar a Pago &rarr;
                        </button>
                    </div>
                </div>
            @endif

            <!-- PASO 3: PAGO Y CONFIRMACIÓN -->
            @if($paso === 3)
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 mb-4">3. Método de Pago</h2>

                    <div class="space-y-3 mb-6">
                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $pasarela === 'simulada' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="pasarela" value="simulada" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Tarjeta de Crédito / Débito (Modo Prueba Inmediata)</span>
                                <span class="block text-[11px] text-gray-500">Validación instantánea para pruebas de desarrollo.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $pasarela === 'yape_manual' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="pasarela" value="yape_manual" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Yape / Plin Directo</span>
                                <span class="block text-[11px] text-gray-500">Yapea al 987-654-321 y envía la captura de confirmación.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-xl border {{ $pasarela === 'transferencia' ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" wire:model.live="pasarela" value="transferencia" class="text-blue-600">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">Transferencia Bancaria BCP / BBVA / Interbank</span>
                                <span class="block text-[11px] text-gray-500">Cuentas corrientes oficiales de la empresa.</span>
                            </div>
                        </label>
                    </div>

                    <!-- Temporizador de Reserva Informativo -->
                    <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl mb-6 text-xs text-amber-900 flex items-center gap-3">
                        <span class="text-2xl">⏱️</span>
                        <div>
                            <strong class="block font-bold">Reserva de Stock Garantizada (30 Minutos)</strong>
                            <p class="text-[11px]">Al confirmar el pedido, las series de tus productos quedarán reservadas exclusivamente para ti.</p>
                        </div>
                    </div>

                    <!-- Términos y Condiciones -->
                    <div class="border-t border-gray-100 pt-4 mb-6">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="aceptaTerminos" class="mt-0.5 rounded text-blue-600">
                            <span class="text-xs text-gray-600">
                                He leído y acepto los <a href="{{ route('pagina.informativa', ['slug' => 'terminos-y-condiciones']) }}" target="_blank" class="text-blue-600 underline">Términos y Condiciones</a> y la <a href="{{ route('pagina.informativa', ['slug' => 'politica-privacidad']) }}" target="_blank" class="text-blue-600 underline">Política de Privacidad</a> de la tienda.
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-between">
                        <button wire:click="retrocederPaso" class="text-xs font-bold text-gray-600 hover:text-gray-900 px-4 py-2">
                            &larr; Volver
                        </button>
                        <button wire:click="procesarPedido" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-8 py-3.5 rounded-xl transition shadow-lg shadow-emerald-600/20">
                            Confirmar y Realizar Pedido
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- RESUMEN LATERAL DE PEDIDO -->
        <div class="bg-white p-6 rounded-3xl border border-gray-200 h-fit space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Resumen de tu Pedido</h3>

            <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
                @if($carrito)
                    @foreach($carrito->items as $it)
                        <div class="py-2.5 flex justify-between items-center text-xs">
                            <div class="truncate pr-2">
                                <span class="font-bold text-gray-900 block truncate">{{ $it->producto?->nombre }}</span>
                                <span class="text-gray-500">{{ $it->cantidad }} x S/ {{ number_format((float) $it->precio_unitario, 2) }}</span>
                            </div>
                            <span class="font-bold text-gray-900 shrink-0">S/ {{ number_format((float) $it->subtotal(), 2) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Totales -->
            <div class="pt-4 border-t border-gray-100 space-y-2 text-xs">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span class="font-bold text-gray-900">S/ {{ number_format((float) $subtotal, 2) }}</span>
                </div>
                @if($descuentoCupon > 0)
                    <div class="flex justify-between text-emerald-600 font-bold">
                        <span>Descuento</span>
                        <span>- S/ {{ number_format((float) $descuentoCupon, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-gray-600">
                    <span>Envío</span>
                    <span class="font-bold text-gray-900">{{ $costoEnvio > 0 ? 'S/ ' . number_format($costoEnvio, 2) : 'GRATIS' }}</span>
                </div>
                <div class="pt-3 border-t border-gray-100 flex justify-between items-center text-sm font-extrabold">
                    <span>Total Final</span>
                    <span class="text-lg text-blue-600">S/ {{ number_format($totalFinal, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
