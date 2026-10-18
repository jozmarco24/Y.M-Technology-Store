<div class="space-y-4 text-xs font-mono">
    @if ($comprobante)
        <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <span class="text-gray-500 block">Comprobante:</span>
            <span class="font-bold text-gray-900 dark:text-white">{{ $comprobante->serie }}-{{ $comprobante->numero }} ({{ strtoupper($comprobante->tipo) }})</span>
            <span class="block mt-1">Estado: <strong>{{ strtoupper($comprobante->estado->value) }}</strong></span>
            @if ($comprobante->codigo_sunat)
                <span class="block">Código SUNAT: {{ $comprobante->codigo_sunat }}</span>
            @endif
            @if ($comprobante->mensaje_sunat)
                <span class="block text-gray-700 dark:text-gray-300">Mensaje SUNAT: {{ $comprobante->mensaje_sunat }}</span>
            @endif
        </div>

        <div>
            <span class="font-bold text-gray-700 dark:text-gray-300 block mb-1 font-sans">Respuesta JSON Recibida:</span>
            <pre class="p-3 bg-gray-950 text-emerald-400 rounded-lg overflow-x-auto max-h-80">{{ json_encode($comprobante->respuesta_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div>
            <span class="font-bold text-gray-700 dark:text-gray-300 block mb-1 font-sans">Payload JSON Enviado:</span>
            <pre class="p-3 bg-gray-950 text-blue-400 rounded-lg overflow-x-auto max-h-80">{{ json_encode($comprobante->payload_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @else
        <p class="text-gray-500">No hay información de comprobante disponible.</p>
    @endif
</div>
