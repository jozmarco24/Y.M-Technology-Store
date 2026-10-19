<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Carrito;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpirarPedidosVencidosCommand extends Command
{
    protected $signature = 'pedidos:expirar';

    protected $description = 'Expira las reservas de stock vencidas de pedidos pendientes y limpia carritos antiguos';

    public function handle(PedidoService $pedidoService): int
    {
        $this->info('Verificando reservas de stock vencidas...');

        $expirados = $pedidoService->expirarVencidos();
        $this->info("Se liberaron las reservas de {$expirados} pedido(s) expirado(s).");

        // Limpiar carritos de invitados con más de 30 días de inactividad
        $carritosEliminados = Carrito::whereNull('cliente_web_id')
            ->where('updated_at', '<', Carbon::now()->subDays(30))
            ->delete();

        if ($carritosEliminados > 0) {
            $this->info("Se eliminaron {$carritosEliminados} carrito(s) de invitado vencido(s).");
        }

        return self::SUCCESS;
    }
}
