<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-white p-8 sm:p-12 rounded-3xl border border-gray-200 shadow-sm">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-6 border-b border-gray-100 pb-4">
            {{ $pagina->titulo }}
        </h1>

        <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed space-y-4">
            {!! nl2br(e($pagina->contenido)) !!}
        </div>
    </div>
</div>
