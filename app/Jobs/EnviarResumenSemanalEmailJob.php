<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Configuracion;
use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\ResumenEjecutivoService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarResumenSemanalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $destinatarios
     */
    public function __construct(
        public array $destinatarios,
        public ?string $desde = null,
        public ?string $hasta = null
    ) {
        $this->onQueue('correos');
    }

    public function handle(
        ResumenEjecutivoService $resumenService,
        ExportacionService $exportacionService
    ): void {
        $fechaFin = $this->hasta ? Carbon::parse($this->hasta) : Carbon::now();
        $fechaInicio = $this->desde ? Carbon::parse($this->desde) : (clone $fechaFin)->subDays(7);

        $tiendaNombre = Configuracion::obtener('tienda_nombre', 'TIENDA DE TECNOLOGÍA');

        foreach ($this->destinatarios as $email) {
            $email = trim($email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Buscar si pertenece a un usuario admin para incluir o no los márgenes
            $usuario = User::where('email', $email)->first();
            if ($usuario === null) {
                // Usuario genérico no admin
                $usuario = new User(['email' => $email, 'name' => 'Destinatario']);
            }

            $datosResumen = $resumenService->generar($usuario, $fechaInicio, $fechaFin);
            $datosResumen['tienda_nombre'] = $tiendaNombre;

            // Renderizar PDF
            $pdf = $exportacionService->generarPdf('reportes.resumen_ejecutivo_pdf', $datosResumen);
            $pdfContent = $pdf->output();

            $asunto = "Resumen Ejecutivo Semanal: {$datosResumen['periodo']['desde']} al {$datosResumen['periodo']['hasta']} - {$tiendaNombre}";

            try {
                Mail::raw("Adjunto encontrará el informe ejecutivo semanal consolidado correspondiente al periodo del {$datosResumen['periodo']['desde']} al {$datosResumen['periodo']['hasta']}.\n\nAtentamente,\nSistema de Gestión SYS-COPM", function ($message) use ($email, $asunto, $pdfContent) {
                    $message->to($email)
                        ->subject($asunto)
                        ->attachData($pdfContent, 'Resumen_Ejecutivo_Semanal.pdf', [
                            'mime' => 'application/pdf',
                        ]);
                });
            } catch (\Throwable $e) {
                Log::error("Error enviando resumen semanal a {$email}: {$e->getMessage()}");
            }
        }
    }
}
