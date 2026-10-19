<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900">Libro de Reclamaciones Virtual</h1>
        <p class="text-xs text-gray-500 mt-1">Conforme a lo establecido en el Código de Protección y Defensa del Consumidor (Ley N° 29571 - INDECOPI)</p>
    </div>

    @if($mensajeExito)
        <div class="bg-emerald-50 border border-emerald-200 p-8 rounded-3xl text-center max-w-xl mx-auto shadow-sm">
            <span class="text-5xl block mb-3">✅</span>
            <h2 class="text-base font-extrabold text-emerald-900 mb-2">Hoja de Reclamación Registrada</h2>
            <p class="text-xs text-emerald-800 leading-relaxed mb-6">
                {{ $mensajeExito }}
            </p>
            <a href="{{ route('inicio') }}" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl transition">
                Volver a la tienda
            </a>
        </div>
    @else
        <div class="bg-white p-6 sm:p-10 rounded-3xl border border-gray-200 shadow-sm">
            @if($errors->has('general'))
                <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-800 text-xs font-semibold">
                    {{ $errors->first('general') }}
                </div>
            @endif

            <form wire:submit.prevent="registrar" class="space-y-6">
                
                <!-- 1. IDENTIFICACIÓN DEL CONSUMIDOR RECLAMANTE -->
                <div>
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">
                        1. Identificación del Consumidor Reclamante
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-gray-700 block mb-1">Nombre Completo o Razón Social</label>
                            <input type="text" wire:model="nombre" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                            @error('nombre') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Tipo de Documento</label>
                            <select wire:model="tipoDocumento" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                <option value="DNI">DNI</option>
                                <option value="CE">Carné de Extranjería</option>
                                <option value="RUC">RUC</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Número de Documento</label>
                            <input type="text" wire:model="numeroDocumento" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                            @error('numeroDocumento') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Teléfono Móvil</label>
                            <input type="text" wire:model="telefono" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                            @error('telefono') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Correo Electrónico (Notificación formal)</label>
                            <input type="email" wire:model="email" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                            @error('email') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-gray-700 block mb-1">Domicilio Completo</label>
                            <input type="text" wire:model="domicilio" placeholder="Av / Calle / Jr, N°, Distrito, Departamento" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                            @error('domicilio') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. IDENTIFICACIÓN DEL BIEN CONTRATADO -->
                <div>
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">
                        2. Identificación del Bien Contratado
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Bien Contratado</label>
                            <select wire:model="bienContratado" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                                <option value="producto">Producto (Laptop, accesorio, hardware)</option>
                                <option value="servicio">Servicio Técnico / Envíos</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Monto Reclamado (Opcional, S/)</label>
                            <input type="number" step="0.01" wire:model="montoReclamado" placeholder="0.00" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-gray-700 block mb-1">Descripción del Producto o Servicio</label>
                            <input type="text" wire:model="descripcionBien" placeholder="Modelo de laptop, código de pedido, etc." class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                        </div>
                    </div>
                </div>

                <!-- 3. DETALLE DE LA RECLAMACIÓN Y PEDIDO DEL CONSUMIDOR -->
                <div>
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">
                        3. Detalle de la Reclamación
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-2">Tipo de Incidencia</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="p-3 rounded-xl border flex items-center gap-2 cursor-pointer text-xs {{ $tipo === 'reclamo' ? 'border-blue-600 bg-blue-50 font-bold' : 'border-gray-200' }}">
                                    <input type="radio" wire:model="tipo" value="reclamo" class="text-blue-600">
                                    <span>Reclamo (Disconformidad con el producto o servicio)</span>
                                </label>
                                <label class="p-3 rounded-xl border flex items-center gap-2 cursor-pointer text-xs {{ $tipo === 'queja' ? 'border-blue-600 bg-blue-50 font-bold' : 'border-gray-200' }}">
                                    <input type="radio" wire:model="tipo" value="queja" class="text-blue-600">
                                    <span>Queja (Malestar por la atención al público)</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Detalle de los hechos reclamados</label>
                            <textarea wire:model="detalleReclamacion" rows="4" placeholder="Explique claramente lo ocurrido..." class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none"></textarea>
                            @error('detalleReclamacion') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Pedido o Solución Solicitada por el Consumidor</label>
                            <textarea wire:model="pedidoConsumidor" rows="2" placeholder="Indique qué solicita como solución concreta..." class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none"></textarea>
                            @error('pedidoConsumidor') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">
                        * La formulación del reclamo no excluye acudir a otras vías de solución de controversias.
                    </p>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl transition shadow-lg shadow-blue-500/20">
                        Enviar Hoja de Reclamación
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
