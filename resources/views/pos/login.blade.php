<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Punto de Venta (POS)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-400 mb-4 ring-1 ring-emerald-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">SYS-COPM POS</h1>
            <p class="text-sm text-slate-400 mt-1">Terminal de Ventas y Facturación Rápida</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('pos.login.submit') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Correo Electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-4 py-3 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                    placeholder="vendedor@tienda.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Contraseña</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-4 py-3 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                    placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center text-slate-400 hover:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900">
                    <span class="ml-2">Recordarme en este equipo</span>
                </label>
            </div>

            <button type="submit"
                class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-lg shadow-emerald-900/30 transition-all flex items-center justify-center space-x-2">
                <span>Ingresar al Punto de Venta</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
            <a href="/admin" class="text-xs text-slate-500 hover:text-emerald-400 transition-colors">
                ¿Acceso a Panel Administrativo Filament? Ir a /admin
            </a>
        </div>
    </div>
</body>
</html>
