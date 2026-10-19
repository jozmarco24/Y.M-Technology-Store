<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Garantía y Servicio Técnico | SYS-COPM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-blue-500 selection:text-white">
    <!-- Navbar -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight text-white">SYS-COPM</h1>
                    <p class="text-[11px] text-slate-400 font-medium">Portal Público de Garantía & Postventa</p>
                </div>
            </div>
            <a href="/login" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-700 hover:border-slate-500 text-slate-300 transition">
                Acceso Personal
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl w-full mx-auto px-4 py-10 flex-1">
        <div class="text-center max-w-xl mx-auto mb-8">
            <h2 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight mb-2">
                Consulta el estado de tu equipo
            </h2>
            <p class="text-slate-400 text-sm">
                Ingresa el número de serie de tu producto y el documento de identidad asociado a la compra para verificar tu cobertura de garantía o el avance de tu orden de servicio técnico.
            </p>
        </div>

        <!-- Formulario de Consulta -->
        <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-6 md:p-8 backdrop-blur shadow-xl mb-8">
            <form action="{{ route('garantia.publica') }}" method="POST" class="space-y-4">
                @csrf
                <!-- Honeypot anti-bot oculto -->
                <input type="text" name="sitio_web" style="display:none !important;" tabindex="-1" autocomplete="off">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="numero_serie" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            Número de Serie (S/N) <span class="text-blue-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="numero_serie" 
                            id="numero_serie"
                            required
                            placeholder="Ej. SN-LNV-998822" 
                            value="{{ old('numero_serie', $serieInput ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition font-mono uppercase"
                        >
                    </div>

                    <div>
                        <label for="numero_documento" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            DNI o RUC del Comprador <span class="text-blue-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="numero_documento" 
                            id="numero_documento"
                            required
                            placeholder="Ej. 47852369 o 20601234568" 
                            value="{{ old('numero_documento', $docInput ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition font-mono"
                        >
                    </div>
                </div>

                <div class="pt-2 flex justify-center">
                    <button 
                        type="submit" 
                        class="px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-blue-500/25 transition flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Verificar Garantía & Servicio
                    </button>
                </div>
            </form>
        </div>

        <!-- Mensajes de Error -->
        @if(!empty($error))
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl p-4 mb-8 text-center flex items-center justify-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ $error }}</span>
            </div>
        @endif

        <!-- Resultado de la Consulta -->
        @if(!empty($resultado))
            <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-6 md:p-8 backdrop-blur shadow-2xl space-y-6">
                <!-- Estado Principal de Garantía -->
                <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-700/80 gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-blue-400 mb-1">Equipo Registrado</div>
                        <h3 class="text-xl md:text-2xl font-bold text-white">
                            {{ $resultado['producto_nombre'] }}
                            @if(!empty($resultado['producto_modelo']))
                                <span class="text-sm font-normal text-slate-300">({{ $resultado['producto_modelo'] }})</span>
                            @endif
                        </h3>
                        <p class="text-xs font-mono text-slate-400 mt-0.5">S/N: {{ $resultado['numero_serie'] }}</p>
                    </div>
                    <div>
                        @if($resultado['es_vigente'])
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold text-sm">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                GARANTÍA VIGENTE ({{ $resultado['dias_restantes'] }} días restantes)
                            </div>
                        @else
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 font-bold text-sm">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                GARANTÍA {{ strtoupper($resultado['estado']) }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Cobertura y Fechas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700/60">
                        <span class="text-xs text-slate-400 block mb-1">Fecha de Inicio / Compra</span>
                        <span class="text-sm font-semibold text-slate-200">{{ $resultado['fecha_compra'] }}</span>
                    </div>
                    <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700/60">
                        <span class="text-xs text-slate-400 block mb-1">Fecha de Vencimiento</span>
                        <span class="text-sm font-semibold text-slate-200">{{ $resultado['fecha_vencimiento'] }}</span>
                    </div>
                </div>

                <!-- Condiciones de Garantía -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700/60">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block mb-2">Términos de Cobertura</span>
                    <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">{{ $resultado['condiciones'] }}</p>
                </div>

                <!-- Estado de Orden de Servicio Técnico si existe -->
                @if(!empty($resultado['orden_servicio']))
                    <div class="mt-6 pt-6 border-t border-slate-700/80">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Seguimiento de Orden de Servicio Técnico
                            </h4>
                            <span class="text-xs font-mono font-semibold text-blue-400 px-2 py-0.5 rounded bg-blue-500/10">
                                {{ $resultado['orden_servicio']['codigo'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-slate-900/40 p-4 rounded-xl border border-slate-700/40">
                            <div>
                                <span class="text-slate-400 block mb-0.5">Estado en Taller:</span>
                                <span class="font-bold text-amber-400">{{ $resultado['orden_servicio']['estado'] }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Fecha Recepción:</span>
                                <span class="font-semibold text-slate-200">{{ $resultado['orden_servicio']['fecha_ingreso'] }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Fecha Promesa:</span>
                                <span class="font-semibold text-slate-200">{{ $resultado['orden_servicio']['fecha_promesa'] }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 text-center py-6 text-xs text-slate-500">
        SYS-COPM &copy; {{ date('Y') }} — Todos los derechos reservados. Consultas seguras sin exposición de datos personales.
    </footer>
</body>
</html>
