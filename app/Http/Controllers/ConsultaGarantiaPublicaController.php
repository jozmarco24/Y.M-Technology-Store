<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Garantia;
use App\Models\OrdenServicio;
use App\Models\Serie;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsultaGarantiaPublicaController extends Controller
{
    /**
     * Muestra el formulario público o el resultado de la consulta.
     */
    public function index(Request $request): View
    {
        $serieInput = trim((string) $request->input('numero_serie', ''));
        $docInput = trim((string) $request->input('numero_documento', ''));
        $honeypot = $request->input('sitio_web'); // Honeypot anti-bot sencillo

        $resultado = null;
        $error = null;

        // Si se envió el formulario (ambos campos obligatorios)
        if ($request->isMethod('post') || ($request->has('numero_serie') && $request->has('numero_documento'))) {
            // Protección honeypot contra bots
            if (! empty($honeypot)) {
                Log::warning('Honeypot detectó bot en consulta de garantía', ['ip' => $request->ip()]);
                $error = 'No se encontró un registro de garantía que coincida con los datos ingresados.';

                return view('garantia_publica', ['resultado' => null, 'error' => $error]);
            }

            if (empty($serieInput) || empty($docInput)) {
                $error = 'El número de serie y el documento de identidad (DNI o RUC) son obligatorios.';

                return view('garantia_publica', ['resultado' => null, 'error' => $error]);
            }

            // Normalización
            $serieMayus = strtoupper($serieInput);
            $docLimpio = preg_replace('/[^0-9A-Za-z]/', '', $docInput);

            // Búsqueda de cliente por documento
            $cliente = Cliente::where('numero_documento', $docLimpio)->first();
            $serie = Serie::where('numero_serie', $serieMayus)->with('producto')->first();

            if (! $cliente || ! $serie) {
                // Mensaje genérico de seguridad para no exponer existencia de datos
                $error = 'No se encontró un registro de garantía que coincida con los datos ingresados.';
                Log::info('Consulta pública de garantía sin coincidencia', ['serie' => $serieMayus, 'doc' => $docLimpio, 'ip' => $request->ip()]);

                return view('garantia_publica', ['resultado' => null, 'error' => $error]);
            }

            // Buscar garantía de esa serie para ese cliente
            $garantia = Garantia::where('serie_id', $serie->id)
                ->where('cliente_id', $cliente->id)
                ->latest('id')
                ->first();

            if (! $garantia) {
                // Alternativamente, verificar si la garantía se emitió al cliente en su detalle de venta
                $garantia = Garantia::where('serie_id', $serie->id)
                    ->whereHas('ventaDetalle.venta', fn ($q) => $q->where('cliente_id', $cliente->id))
                    ->latest('id')
                    ->first();
            }

            if (! $garantia) {
                $error = 'No se encontró un registro de garantía que coincida con los datos ingresados.';

                return view('garantia_publica', ['resultado' => null, 'error' => $error]);
            }

            // Buscar si tiene alguna orden de servicio activa o reciente
            $ordenServicio = OrdenServicio::where('serie_id', $serie->id)
                ->where('cliente_id', $cliente->id)
                ->latest('id')
                ->first();

            // Preparar respuesta SIN datos personales ni costos
            $resultado = [
                'encontrado' => true,
                'producto_nombre' => $serie->producto?->nombre ?? 'Equipo',
                'producto_modelo' => $serie->producto?->modelo,
                'numero_serie' => $serie->numero_serie,
                'fecha_compra' => $garantia->fecha_inicio->format('d/m/Y'),
                'fecha_vencimiento' => $garantia->fecha_fin->format('d/m/Y'),
                'dias_restantes' => $garantia->diasRestantes(),
                'estado' => $garantia->estado->etiqueta(),
                'es_vigente' => $garantia->esVigente(),
                'condiciones' => $garantia->condiciones_snapshot ?? 'Garantía por defectos de fábrica en componentes de hardware.',
                'orden_servicio' => $ordenServicio ? [
                    'codigo' => $ordenServicio->codigo,
                    'estado' => $ordenServicio->estado->etiqueta(),
                    'fecha_ingreso' => $ordenServicio->recibido_en->format('d/m/Y'),
                    'fecha_promesa' => $ordenServicio->fecha_promesa ? $ordenServicio->fecha_promesa->format('d/m/Y') : 'Por coordinar',
                ] : null,
            ];
        }

        return view('garantia_publica', [
            'resultado' => $resultado,
            'error' => $error,
            'serieInput' => $serieInput,
            'docInput' => $docInput,
        ]);
    }
}
