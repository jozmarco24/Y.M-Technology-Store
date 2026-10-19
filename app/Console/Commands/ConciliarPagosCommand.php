<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoPagoOnline;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Services\PagoOnlineService;
use Exception;
use Illuminate\Console\Command;

class ConciliarPagosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pagos:conciliar {--dias=2 : Días hacia atrás para conciliar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara pagos registrados en pasarela contra pedidos y detecta inconsistencias o cobros huérfanos';

    /**
     * Execute the console command.
     */
    public function handle(PagoOnlineService $pagoOnlineService): int
    {
        $dias = (int) $this->option('dias');
        $desde = now()->subDays($dias);

        $this->info("Iniciando conciliación de pagos desde {$desde->format('d/m/Y H:i')}...");

        // Buscar pagos que quedaron pendientes con referencia externa
        $pagosDudosos = PagoOnline::where('created_at', '>=', $desde)
            ->whereNotNull('referencia_externa')
            ->where('estado', EstadoPagoOnline::PENDIENTE)
            ->with('pedido')
            ->get();

        $conciliados = 0;
        $diferencias = 0;

        foreach ($pagosDudosos as $pago) {
            /** @var Pedido|null $pedido */
            $pedido = $pago->pedido;

            if (! $pedido) {
                $this->warn(" [ALERTA] Pago online #{$pago->id} sin pedido asociado. Ref: {$pago->referencia_externa}");
                $diferencias++;

                continue;
            }

            try {
                // Consultar a la pasarela real o simulada
                $resConsulta = $pagoOnlineService->consultar($pago);

                if ($resConsulta['pagado']) {
                    $this->info(" [CONCILIADO] Pago #{$pago->id} detectado como cobrado en pasarela. Confirmando pedido {$pedido->codigo}...");
                    $pagoOnlineService->confirmar($pago, $resConsulta['respuesta'] ?? []);
                    $conciliados++;
                }
            } catch (Exception $e) {
                $this->error(" [ERROR] Al consultar pago #{$pago->id}: {$e->getMessage()}");
                $diferencias++;
            }
        }

        $this->newLine();
        $this->info("Conciliación completada. Pagos conciliados: {$conciliados}. Diferencias/alertas: {$diferencias}.");

        return self::SUCCESS;
    }
}
