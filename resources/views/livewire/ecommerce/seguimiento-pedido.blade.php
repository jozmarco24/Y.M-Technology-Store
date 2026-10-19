<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Estado de tu Pedido</h1>
            <p class="text-xs text-gray-500">Código de orden: <strong class="text-gray-900">{{ $codigo }}</strong></p>
        </div>
        <a href="{{ route('inicio') }}" class="text-xs font-bold text-blue-600 hover:underline">
            &larr; Volver a la tienda
        </a>
    </div>

    @if(!$autenticado && !$pedido)
        <!-- Formulario de Validación de Correo para Invitados -->
        <div class="bg-white p-8 rounded-3xl border border-gray-200 text-center max-w-md mx-auto shadow-sm">
            <span class="text-5xl block mb-3">🔒</span>
            <h2 class="text-base font-extrabold text-gray-900 mb-2">Acceso a Seguimiento de Invitado</h2>
            <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                Por seguridad y protección de tus datos personales, ingresa el correo electrónico con el que realizaste este pedido.
            </p>

            <form wire:submit.prevent="verificarAccesoEmail" class="space-y-4">
                <div>
                    <input type="email" 
                           wire:model="email" 
                           placeholder="tu-correo@example.com" 
                           class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('email') <span class="text-rose-500 text-[11px] block text-left mt-1">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3 rounded-xl transition shadow-md">
                    Consultar Pedido
                </button>
            </form>
        </div>
    @elseif($pedido)
        <!-- Mensajes de Alerta / Reintento -->
        @if($mensajeReintento)
            <div class="mb-6 p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold">
                ℹ️ {{ $mensajeReintento }}
            </div>
        @endif

        @if($pedido->estado === \App\Enums\EstadoPedido::EXPIRADO)
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-3">
                <span class="text-2xl">⏳</span>
                <div>
                    <strong class="block">Este pedido ha expirado</strong>
                    <span>La reserva de stock por 30 minutos venció antes de recibir la confirmación del pago. Las series fueron devueltas al inventario.</span>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- DETALLE Y LÍNEA DE TIEMPO -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Tarjeta de Estado y Resumen -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs text-gray-500">Registrado el {{ $pedido->created_at->format('d/m/Y H:i') }}</span>
                        <span class="text-xs font-bold px-3 py-1 rounded-full uppercase"
                              style="background-color: {{ $pedido->estado->color() === 'success' ? '#dcfce7' : ($pedido->estado->color() === 'warning' ? '#fef9c3' : '#fee2e2') }}; color: {{ $pedido->estado->color() === 'success' ? '#15803d' : ($pedido->estado->color() === 'warning' ? '#a16207' : '#b91c1c') }}">
                            {{ $pedido->estado->etiqueta() }}
                        </span>
                    </div>

                    @if($pedido->tracking_codigo)
                        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-100 mb-6 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-blue-600 uppercase block">Envío por Courier</span>
                                <strong class="text-sm text-gray-900 block">{{ $pedido->courier }}</strong>
                                <span class="text-xs text-gray-600">Guía / Tracking: <strong class="font-mono text-blue-700">{{ $pedido->tracking_codigo }}</strong></span>
                            </div>
                        </div>
                    @endif

                    <!-- Línea de Tiempo (Eventos) -->
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4">Historial de Estados</h3>
                    <div class="border-l-2 border-blue-100 ml-3 space-y-4 pb-2">
                        @foreach($pedido->eventos as $ev)
                            <div class="relative pl-6">
                                <span class="w-3 h-3 rounded-full bg-blue-600 absolute -left-[7px] top-1"></span>
                                <span class="text-[11px] font-bold text-gray-800 block">{{ $ev->created_at->format('d/m/Y H:i') }}</span>
                                <p class="text-xs text-gray-600 mt-0.5">{{ $ev->comentario }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if($pedido->estado === \App\Enums\EstadoPedido::PENDIENTE_PAGO && !$pedido->estaExpirado())
                        <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-amber-700 font-semibold">Reserva activa hasta: {{ $pedido->reserva_expira_en?->format('H:i') }}</span>
                            <button wire:click="reintentarPago" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition">
                                Reintentar Pago
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Productos Comprados -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4">Artículos del Pedido</h3>
                    <div class="divide-y divide-gray-100">
                        @foreach($pedido->items as $it)
                            <div class="py-3 flex justify-between items-center text-xs">
                                <div>
                                    <strong class="text-gray-900 block">{{ $it->descripcion }}</strong>
                                    <span class="text-gray-500">{{ $it->cantidad }} unid. x S/ {{ number_format((float) $it->precio_unitario, 2) }}</span>
                                </div>
                                <span class="font-bold text-gray-900">S/ {{ number_format((float) $it->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- RESUMEN FINANCIERO Y COMPROBANTE -->
            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm h-fit space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Datos de Facturación</h3>

                <div class="text-xs space-y-1.5 text-gray-600">
                    <p>Comprobante: <strong class="text-gray-900 uppercase">{{ $pedido->tipo_comprobante }}</strong></p>
                    <p>Documento: <strong class="text-gray-900">{{ $pedido->documento_tipo }} {{ $pedido->documento_numero }}</strong></p>
                    @if($pedido->razon_social)
                        <p>Razón Social: <strong class="text-gray-900">{{ $pedido->razon_social }}</strong></p>
                    @endif
                    <p>Email: <strong class="text-gray-900">{{ $pedido->email_contacto }}</strong></p>
                </div>

                <div class="pt-4 border-t border-gray-100 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span class="font-bold text-gray-900">S/ {{ number_format((float) $pedido->subtotal, 2) }}</span>
                    </div>
                    @if($pedido->descuento > 0)
                        <div class="flex justify-between text-emerald-600 font-bold">
                            <span>Descuento</span>
                            <span>- S/ {{ number_format((float) $pedido->descuento, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-gray-600">
                        <span>Costo de Envío</span>
                        <span class="font-bold text-gray-900">S/ {{ number_format((float) $pedido->costo_envio, 2) }}</span>
                    </div>
                    <div class="pt-3 border-t border-gray-100 flex justify-between items-center text-sm font-extrabold">
                        <span>Total Pagado</span>
                        <span class="text-blue-600">S/ {{ number_format((float) $pedido->total, 2) }}</span>
                    </div>
                </div>

                @if($pedido->venta && $pedido->venta->comprobanteElectronico && $pedido->venta->comprobanteElectronico->enlace_pdf)
                    <div class="pt-4 border-t border-gray-100">
                        <a href="{{ $pedido->venta->comprobanteElectronico->enlace_pdf }}" target="_blank" 
                           class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-black text-white font-bold text-xs py-3 rounded-xl transition shadow-md">
                            <span>📄</span> Descargar Comprobante PDF
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
