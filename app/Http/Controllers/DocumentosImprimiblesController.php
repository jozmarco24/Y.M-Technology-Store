<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\OrdenServicio;
use Illuminate\Contracts\View\View;

class DocumentosImprimiblesController extends Controller
{
    /**
     * Imprimir constancia de devolución o cambio en formato A4.
     */
    public function devolucionA4(Devolucion $devolucion): View
    {
        $devolucion->load(['venta.cliente', 'detalles.producto', 'detalles.serie', 'solicitante', 'aprobador']);
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return view('documentos.devolucion_a4', [
            'devolucion' => $devolucion,
            'configs' => $configuraciones,
        ]);
    }

    /**
     * Imprimir ticket de devolución / cambio en formato térmico 80mm.
     */
    public function devolucionTicket(Devolucion $devolucion): View
    {
        $devolucion->load(['venta.cliente', 'detalles.producto', 'detalles.serie', 'solicitante']);
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return view('documentos.devolucion_ticket', [
            'devolucion' => $devolucion,
            'configs' => $configuraciones,
        ]);
    }

    /**
     * Imprimir orden de ingreso de servicio técnico en formato A4 con QR y condiciones.
     */
    public function servicioIngresoA4(OrdenServicio $orden): View
    {
        $orden->load(['cliente', 'producto', 'serie', 'receptor', 'tecnico', 'fotos']);
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return view('documentos.servicio_ingreso_a4', [
            'orden' => $orden,
            'configs' => $configuraciones,
        ]);
    }

    /**
     * Imprimir cotización de servicio técnico en formato A4.
     */
    public function servicioCotizacionA4(OrdenServicio $orden): View
    {
        $orden->load(['cliente', 'producto', 'serie', 'repuestos.producto', 'tecnico']);
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return view('documentos.servicio_cotizacion_a4', [
            'orden' => $orden,
            'configs' => $configuraciones,
        ]);
    }

    /**
     * Imprimir acta de entrega de servicio técnico con garantía de reparación en formato A4.
     */
    public function servicioActaEntregaA4(OrdenServicio $orden): View
    {
        $orden->load(['cliente', 'producto', 'serie', 'repuestos', 'tecnico']);
        $configuraciones = Configuracion::pluck('valor', 'clave')->toArray();

        return view('documentos.servicio_acta_entrega_a4', [
            'orden' => $orden,
            'configs' => $configuraciones,
        ]);
    }
}
