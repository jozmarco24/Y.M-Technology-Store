<div class="max-w-md mx-auto px-4 py-12">
    <div class="bg-white p-8 rounded-3xl border border-gray-200 shadow-sm">
        
        <div class="text-center mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900">
                {{ $modo === 'login' ? 'Iniciar Sesión' : 'Crear Cuenta Nueva' }}
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                {{ $modo === 'login' ? 'Accede para ver tus pedidos y garantías' : 'Regístrate para comprar más rápido y seguir tus envíos' }}
            </p>
        </div>

        @if($modo === 'login')
            <!-- FORMULARIO LOGIN -->
            <form wire:submit.prevent="login" class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Correo Electrónico</label>
                    <input type="email" wire:model="email" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('email') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Contraseña</label>
                    <input type="password" wire:model="password" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('password') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-gray-600">
                        <input type="checkbox" wire:model="recordar" class="rounded text-blue-600">
                        <span>Recordarme</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3.5 rounded-xl transition shadow-lg shadow-blue-500/20">
                    Ingresar a mi Cuenta
                </button>
            </form>
        @else
            <!-- FORMULARIO REGISTRO -->
            <form wire:submit.prevent="registrar" class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Nombre Completo</label>
                    <input type="text" wire:model="nombre" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('nombre') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Correo Electrónico</label>
                    <input type="email" wire:model="email" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('email') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Teléfono Móvil (WhatsApp)</label>
                    <input type="text" wire:model="telefono" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('telefono') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Contraseña (Mínimo 8 caracteres)</label>
                    <input type="password" wire:model="password" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                    @error('password') <span class="text-rose-500 text-[11px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-700 block mb-1">Confirmar Contraseña</label>
                    <input type="password" wire:model="passwordConfirmation" class="w-full text-xs bg-gray-50 border border-gray-200 rounded-xl p-3 outline-none focus:border-blue-500">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3.5 rounded-xl transition shadow-lg shadow-blue-500/20">
                    Crear mi Cuenta
                </button>
            </form>
        @endif

        <div class="mt-6 pt-6 border-t border-gray-100 text-center text-xs">
            <button wire:click="toggleModo" class="text-blue-600 hover:underline font-bold">
                {{ $modo === 'login' ? '¿No tienes cuenta todavía? Regístrate aquí' : '¿Ya tienes una cuenta registrada? Inicia sesión' }}
            </button>
        </div>
    </div>
</div>
