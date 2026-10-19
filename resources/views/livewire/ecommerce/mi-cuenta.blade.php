<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Panel de Cliente</h1>
            <p class="text-xs text-gray-500">Bienvenido(a), <strong class="text-gray-900">{{ auth('cliente')->user()->nombre }}</strong></p>
        </div>

        <form action="{{ route('cliente.logout') }}" method="POST">
            @csrf
            <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-4 py-2 rounded-xl transition">
                Cerrar Sesión
            </button>
        </form>
    </div>

    <!-- PESTAÑAS -->
    <div class="flex border-b border-gray-200 mb-8 overflow-x-auto gap-4 text-xs font-bold">
        <button wire:click="cambiarPestana('pedidos')" 
                class="py-3 border-b-2 transition whitespace-nowrap {{ $pestana === 'pedidos' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
            📦 Mis Pedidos
        </button>
        <button wire:click="cambiarPestana('garantias')" 
                class="py-3 border-b-2 transition whitespace-nowrap {{ $pestana === 'garantias' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
            🛡️ Mis Garantías Activas
        </button>
        <button wire:click="cambiarPestana('direcciones')" 
                class="py-3 border-b-2 transition whitespace-nowrap {{ $pestana === 'direcciones' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
            📍 Direcciones Guardadas
        </button>
        <button wire:click="cambiarPestana('perfil')" 
                class="py-3 border-b-2 transition whitespace-nowrap {{ $pestana === 'perfil' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
            👤 Datos Personales
        </button>
    </div>

    <!-- CONTENIDO PESTAÑAS -->
    <div>
        <!-- 1. MIS PEDIDOS -->
        @if($pestana === 'pedidos')
            @if($pedidos->isNotEmpty())
                <div class="space-y-4">
                    @foreach($pedidos as $ped)
                        <div class="bg-white p-5 rounded-3xl border border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-extrabold text-gray-900">{{ $ped->codigo }}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"
                                          style="background-color: {{ $ped->estado->color() === 'success' ? '#dcfce7' : ($ped->estado->color() === 'warning' ? '#fef9c3' : '#fee2e2') }}; color: {{ $ped->estado->color() === 'success' ? '#15803d' : ($ped->estado->color() === 'warning' ? '#a16207' : '#b91c1c') }}">
                                        {{ $ped->estado->etiqueta() }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-500 block">Registrado el {{ $ped->created_at->format('d/m/Y H:i') }}</span>
                                <span class="text-xs font-bold text-blue-600 block mt-1">Total: S/ {{ number_format((float) $ped->total, 2) }}</span>
                            </div>

                            <a href="{{ route('pedido.seguimiento', ['codigo' => $ped->codigo]) }}" 
                               class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                                Ver Detalle y Tracking &rarr;
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white p-12 text-center rounded-3xl border border-gray-200 text-gray-400 text-xs">
                    No tienes pedidos registrados todavía.
                </div>
            @endif
        @endif

        <!-- 2. MIS GARANTÍAS -->
        @if($pestana === 'garantias')
            @if($garantias->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($garantias as $gar)
                        <div class="bg-white p-5 rounded-3xl border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-gray-900">{{ $gar->producto?->nombre }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $gar->estaVigente() ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $gar->estaVigente() ? 'Vigente' : 'Vencida' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">Serie: <strong class="font-mono text-gray-800">{{ $gar->serie?->numero }}</strong></p>
                            <p class="text-xs text-gray-500 mt-1">Vence el: <strong class="text-gray-800">{{ $gar->fecha_fin->format('d/m/Y') }}</strong></p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white p-12 text-center rounded-3xl border border-gray-200 text-gray-400 text-xs">
                    No tienes series con garantías registradas en tu historial.
                </div>
            @endif
        @endif

        <!-- 3. DIRECCIONES -->
        @if($pestana === 'direcciones')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Listado -->
                <div class="lg:col-span-2 space-y-4">
                    @foreach($direcciones as $dir)
                        <div class="bg-white p-5 rounded-3xl border border-gray-200 flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <strong class="text-xs font-bold text-gray-900">{{ $dir->alias }}</strong>
                                    @if($dir->es_principal)
                                        <span class="bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded-md">Principal</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-600">{{ $dir->direccion }} - {{ $dir->distrito }}, {{ $dir->provincia }}</p>
                                <p class="text-[11px] text-gray-400 mt-1">Destinatario: {{ $dir->destinatario }} | Tel: {{ $dir->telefono }}</p>
                            </div>
                            <button wire:click="eliminarDireccion({{ $dir->id }})" class="text-gray-400 hover:text-rose-500 text-xs">
                                Eliminar
                            </button>
                        </div>
                    @endforeach
                </div>

                <!-- Formulario Agregar -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 h-fit">
                    <h3 class="text-xs font-extrabold text-gray-900 uppercase tracking-wider mb-4">Nueva Dirección</h3>
                    <form wire:submit.prevent="agregarDireccion" class="space-y-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 block mb-1">Alias (Ej: Casa, Oficina)</label>
                            <input type="text" wire:model="alias" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                            @error('alias') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 block mb-1">Nombre Destinatario</label>
                            <input type="text" wire:model="destinatario" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 block mb-1">Teléfono</label>
                            <input type="text" wire:model="telefonoDir" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 block mb-1">Distrito</label>
                            <input type="text" wire:model="distrito" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 block mb-1">Dirección Exacta</label>
                            <input type="text" wire:model="direccion" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-2.5 outline-none">
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700 pt-2">
                            <input type="checkbox" wire:model="esPrincipal" class="rounded text-blue-600">
                            <span>Marcar como dirección principal</span>
                        </label>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2.5 rounded-xl transition shadow-md mt-2">
                            Guardar Dirección
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <!-- 4. PERFIL -->
        @if($pestana === 'perfil')
            <div class="bg-white p-6 rounded-3xl border border-gray-200 max-w-md">
                <form wire:submit.prevent="guardarPerfil" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-gray-700 block mb-1">Nombre Completo</label>
                        <input type="text" wire:model="nombre" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                        @error('nombre') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-700 block mb-1">Teléfono Móvil</label>
                        <input type="text" wire:model="telefono" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none">
                        @error('telefono') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-700 block mb-1">Correo Electrónico (No modificable)</label>
                        <input type="email" value="{{ auth('cliente')->user()->email }}" disabled class="w-full text-xs bg-gray-100 border border-gray-200 rounded-xl p-3 text-gray-500 cursor-not-allowed">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl transition shadow-md">
                        Guardar Cambios
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
