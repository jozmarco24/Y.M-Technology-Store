<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnviarResumenSemanalEmailJob;
use App\Models\Configuracion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResumenSemanalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportes:resumen-semanal {--destinatarios= : Lista de correos separados por coma} {--desde= : Fecha inicio YYYY-MM-DD} {--hasta= : Fecha fin YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y envía por correo el resumen ejecutivo semanal a los administradores y gerencia';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando proceso de generación y despacho del resumen semanal...');

        $destinatariosConfig = (string) ($this->option('destinatarios') ?: Configuracion::obtener('destinatarios_resumen_semanal', 'gerencia@tienda-syscopm.pe'));
        $emails = array_filter(array_map('trim', explode(',', $destinatariosConfig)));

        if (empty($emails)) {
            $this->warn('No se configuraron destinatarios válidos para el resumen semanal.');

            return self::FAILURE;
        }

        $hasta = $this->option('hasta') ?: Carbon::now()->toDateString();
        $desde = $this->option('desde') ?: Carbon::parse((string) $hasta)->subDays(7)->toDateString();

        $this->line("Periodo: {$desde} al {$hasta}");
        $this->line('Destinatarios: '.implode(', ', $emails));

        EnviarResumenSemanalEmailJob::dispatch($emails, (string) $desde, (string) $hasta);

        $this->info('Job EnviarResumenSemanalEmailJob encolado exitosamente en la cola "correos".');

        return self::SUCCESS;
    }
}
