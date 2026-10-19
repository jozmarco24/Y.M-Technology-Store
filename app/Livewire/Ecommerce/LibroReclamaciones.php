<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Reclamacion;
use App\Services\ReclamacionService;
use Exception;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LibroReclamaciones extends Component
{
    public string $tipo = 'reclamo'; // reclamo o queja

    public string $nombre = '';

    public string $tipoDocumento = 'DNI';

    public string $numeroDocumento = '';

    public string $telefono = '';

    public string $email = '';

    public string $domicilio = '';

    public bool $esMenorEdad = false;

    public string $apoderadoNombre = '';

    public string $apoderadoTelefono = '';

    public string $apoderadoEmail = '';

    public string $bienContratado = 'producto'; // producto o servicio

    public string $montoReclamado = '';

    public string $descripcionBien = '';

    public string $detalleReclamacion = '';

    public string $pedidoConsumidor = '';

    public ?Reclamacion $reclamacionCreada = null;

    public ?string $mensajeExito = null;

    public function registrar(ReclamacionService $service): void
    {
        $this->validate([
            'tipo' => 'required|in:reclamo,queja',
            'nombre' => 'required|string|min:3|max:150',
            'tipoDocumento' => 'required|in:DNI,CE,RUC',
            'numeroDocumento' => 'required|string|min:8|max:15',
            'telefono' => 'required|string|min:6|max:20',
            'email' => 'required|email|max:100',
            'domicilio' => 'required|string|min:5|max:255',
            'bienContratado' => 'required|in:producto,servicio',
            'detalleReclamacion' => 'required|string|min:10',
            'pedidoConsumidor' => 'required|string|min:5',
        ]);

        if ($this->esMenorEdad) {
            $this->validate([
                'apoderadoNombre' => 'required|string|min:3',
                'apoderadoTelefono' => 'required|string|min:6',
            ]);
        }

        try {
            $this->reclamacionCreada = $service->registrar([
                'tipo' => $this->tipo,
                'consumidor_nombre' => $this->nombre,
                'consumidor_tipo_documento' => $this->tipoDocumento,
                'consumidor_numero_documento' => $this->numeroDocumento,
                'consumidor_telefono' => $this->telefono,
                'consumidor_email' => $this->email,
                'consumidor_domicilio' => $this->domicilio,
                'es_menor_edad' => $this->esMenorEdad,
                'apoderado_nombre' => $this->esMenorEdad ? $this->apoderadoNombre : null,
                'apoderado_telefono' => $this->esMenorEdad ? $this->apoderadoTelefono : null,
                'apoderado_email' => $this->esMenorEdad ? $this->apoderadoEmail : null,
                'bien_contratado' => $this->bienContratado,
                'monto_reclamado' => $this->montoReclamado ?: null,
                'descripcion_bien' => $this->descripcionBien,
                'detalle_reclamacion' => $this->detalleReclamacion,
                'pedido_consumidor' => $this->pedidoConsumidor,
            ]);

            $this->mensajeExito = "Su hoja de reclamación ha sido registrada satisfactoriamente con el código: {$this->reclamacionCreada->codigo}. Según la normativa de INDECOPI, la empresa dará respuesta formal en un plazo máximo de 15 días hábiles a su correo electrónico ({$this->email}).";
        } catch (Exception $e) {
            $this->addError('general', 'Ocurrió un error al registrar su reclamación: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.ecommerce.libro-reclamaciones')
            ->layout('layouts.ecommerce', [
                'metaTitle' => 'Libro de Reclamaciones Virtual | Conforme a Ley INDECOPI | SYS-COPM',
                'metaDescription' => 'Formulario oficial de Libro de Reclamaciones virtual para reclamos y quejas conforme al Código de Protección y Defensa del Consumidor.',
            ]);
    }
}
