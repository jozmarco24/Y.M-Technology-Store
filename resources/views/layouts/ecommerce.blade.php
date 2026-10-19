<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $metaTitle ?? 'SYS-COPM | Tienda de Tecnología, Laptops y Componentes' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Tienda oficial de laptops, componentes y periféricos de cómputo en Perú. Garantía real y despacho seguro.' }}">
    
    <!-- Open Graph / SEO -->
    <meta property="og:title" content="{{ $metaTitle ?? 'SYS-COPM' }}">
    <meta property="og:description" content="{{ $metaDescription ?? 'Tienda especializada en tecnología' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN para máxima velocidad de renderizado y portabilidad) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col antialiased selection:bg-blue-600 selection:text-white"
      x-data="{ 
          notificaciones: [],
          agregarNotificacion(tipo, mensaje) {
              const id = Date.now();
              this.notificaciones.push({ id, tipo, mensaje });
              setTimeout(() => {
                  this.notificaciones = this.notificaciones.filter(n => n.id !== id);
              }, 4000);
          }
      }"
      @notificar.window="agregarNotificacion($event.detail.tipo, $event.detail.mensaje)">

    <!-- TOAST NOTIFICACIONES -->
    <div class="fixed top-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
        <template x-for="n in notificaciones" :key="n.id">
            <div class="pointer-events-auto p-4 rounded-xl shadow-xl flex items-center gap-3 transition-all transform duration-300 border text-sm font-medium"
                 :class="{
                     'bg-emerald-50 text-emerald-800 border-emerald-200': n.tipo === 'success',
                     'bg-rose-50 text-rose-800 border-rose-200': n.tipo === 'danger' || n.tipo === 'error',
                     'bg-amber-50 text-amber-800 border-amber-200': n.tipo === 'warning',
                     'bg-blue-50 text-blue-800 border-blue-200': n.tipo === 'info'
                 }">
                <span x-text="n.mensaje"></span>
            </div>
        </template>
    </div>

    <!-- BARRA SUPERIOR PROMO -->
    <div class="bg-slate-900 text-slate-300 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center gap-4">
                <span>🚚 Envíos a todo el Perú | Garantía Oficial</span>
                <span class="hidden md:inline text-slate-600">|</span>
                <span class="hidden md:inline">🕒 Lun - Sáb: 9:00 AM - 7:00 PM</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('garantia.publica') }}" class="hover:text-white transition">Consultar Garantía</a>
                <a href="{{ route('libro.reclamaciones') }}" class="hover:text-white transition">Libro de Reclamaciones</a>
            </div>
        </div>
    </div>

    <!-- CABECERA PRINCIPAL -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4">
            
            <!-- Logo -->
            <a href="{{ route('inicio') }}" class="flex items-center gap-2.5 shrink-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-blue-500/20">
                    S
                </div>
                <div>
                    <span class="text-xl font-extrabold tracking-tight text-gray-900">SYS-COPM</span>
                    <span class="block text-[10px] uppercase font-bold tracking-widest text-blue-600 -mt-1">Tecnología</span>
                </div>
            </a>

            <!-- Buscador Global -->
            <div class="flex-1 max-w-xl mx-4 hidden sm:block">
                <form action="{{ route('catalogo') }}" method="GET" class="relative">
                    <input type="text" 
                           name="busqueda" 
                           placeholder="Buscar laptops, procesadores, tarjetas gráficas..." 
                           class="w-full bg-gray-100 hover:bg-gray-50 focus:bg-white text-sm rounded-full pl-11 pr-4 py-2.5 border border-transparent focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition outline-none">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </form>
            </div>

            <!-- Acciones Header (Cuenta & Carrito) -->
            <div class="flex items-center gap-3">
                @if(auth('cliente')->check())
                    <a href="{{ route('mi-cuenta') }}" class="flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-blue-600 transition p-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="hidden md:inline">{{ auth('cliente')->user()->nombre }}</span>
                    </a>
                @else
                    <a href="{{ route('cliente.login') }}" class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition px-3 py-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden md:inline">Iniciar Sesión</span>
                    </a>
                @endif

                <!-- Mini Carrito Livewire -->
                <livewire:ecommerce.mini-carrito />
            </div>
        </div>

        <!-- Menú de Categorías -->
        <nav class="bg-gray-50 border-t border-gray-200 overflow-x-auto text-xs font-semibold text-gray-700 px-4">
            <div class="max-w-7xl mx-auto flex items-center space-x-6 py-2.5">
                <a href="{{ route('catalogo') }}" class="hover:text-blue-600 transition shrink-0 font-bold text-blue-600">
                    Todos los Productos
                </a>
                @foreach(\App\Models\Categoria::where('activo', true)->orderBy('orden')->get() as $cat)
                    <a href="{{ route('catalogo', ['categoria' => $cat->slug]) }}" class="hover:text-blue-600 transition shrink-0">
                        {{ $cat->nombre }}
                    </a>
                @endforeach
            </div>
        </nav>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="flex-1">
        {{ $slot }}
    </main>

    <!-- AVISO DE COOKIES -->
    <div x-data="{ aceptado: localStorage.getItem('cookies_aceptadas') }" 
         x-show="!aceptado" 
         x-cloak
         class="fixed bottom-0 inset-x-0 bg-slate-900/95 backdrop-blur text-white p-4 border-t border-slate-800 z-50 text-xs shadow-2xl">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-slate-300">
                Utilizamos cookies propias y de terceros para brindarte la mejor experiencia de compra, análisis de tráfico y seguridad en transacciones. 
                Consulta nuestra <a href="{{ route('pagina.informativa', ['slug' => 'politica-privacidad']) }}" class="underline hover:text-white">Política de Privacidad</a>.
            </p>
            <button @click="localStorage.setItem('cookies_aceptadas', 'true'); aceptado = true" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg shrink-0 transition shadow-md">
                Aceptar Cookies
            </button>
        </div>
    </div>

    <!-- PIE DE PÁGINA (FOOTER) -->
    <footer class="bg-slate-900 text-slate-300 pt-12 pb-8 border-t border-slate-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
            <!-- Columna 1: Info Tienda -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-extrabold text-lg">
                        S
                    </div>
                    <span class="text-lg font-extrabold text-white">SYS-COPM</span>
                </div>
                <p class="text-xs text-slate-400 mb-4 leading-relaxed">
                    Especialistas en cómputo de alto rendimiento, laptops gamer, estaciones de trabajo y componentes de vanguardia con garantía local.
                </p>
                <div class="text-xs text-slate-400 space-y-1">
                    <p>📍 Av. Wilson 1234, Cercado de Lima</p>
                    <p>📞 (+51) 987 654 321</p>
                    <p>✉️ ventas@sys-copm.com</p>
                </div>
            </div>

            <!-- Columna 2: Enlaces Rápidos -->
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Navegación</h3>
                <ul class="text-xs space-y-2.5">
                    <li><a href="{{ route('catalogo') }}" class="hover:text-white transition">Catálogo Completo</a></li>
                    <li><a href="{{ route('garantia.publica') }}" class="hover:text-white transition">Consulta de Garantía</a></li>
                    <li><a href="{{ route('mi-cuenta') }}" class="hover:text-white transition">Mis Pedidos</a></li>
                    <li><a href="{{ route('cliente.login') }}" class="hover:text-white transition">Iniciar Sesión</a></li>
                </ul>
            </div>

            <!-- Columna 3: Información Legal -->
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Políticas y Legal</h3>
                <ul class="text-xs space-y-2.5">
                    <li><a href="{{ route('pagina.informativa', ['slug' => 'terminos-y-condiciones']) }}" class="hover:text-white transition">Términos y Condiciones</a></li>
                    <li><a href="{{ route('pagina.informativa', ['slug' => 'politica-privacidad']) }}" class="hover:text-white transition">Política de Privacidad</a></li>
                    <li><a href="{{ route('pagina.informativa', ['slug' => 'politica-envios']) }}" class="hover:text-white transition">Envíos y Despachos</a></li>
                    <li><a href="{{ route('pagina.informativa', ['slug' => 'cambios-y-devoluciones']) }}" class="hover:text-white transition">Cambios y Devoluciones</a></li>
                </ul>
            </div>

            <!-- Columna 4: Libro de Reclamaciones y Pago Seguro -->
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Atención al Cliente</h3>
                <a href="{{ route('libro.reclamaciones') }}" class="block bg-slate-800 hover:bg-slate-750 p-4 rounded-xl border border-slate-700 transition group mb-4">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">📖</span>
                        <div>
                            <span class="block text-xs font-bold text-white group-hover:text-blue-400 transition">Libro de Reclamaciones</span>
                            <span class="block text-[11px] text-slate-400">Conforme a ley INDECOPI</span>
                        </div>
                    </div>
                </a>
                <div class="text-[11px] text-slate-400">
                    <p class="font-semibold text-slate-300 mb-1">Pagos 100% Protegidos</p>
                    <p>Aceptamos tarjetas de crédito/débito vía Culqi y Niubiz con encriptación SSL.</p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 border-t border-slate-800 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} SYS-COPM. Todos los derechos reservados. Desarrollado con Laravel 11, Livewire 3 y PostgreSQL.
        </div>
    </footer>

    @livewireScripts
</body>
</html>
