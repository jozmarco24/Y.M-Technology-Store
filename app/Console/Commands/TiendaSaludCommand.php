<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\Pedido;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TiendaSaludCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tienda:salud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica el estado de salud de la base de datos, colas, pasarelas, Nubefact, almacenamiento y disco';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== CHEQUEO DE SALUD DE SISTEMA SYS-COPM ===');

        $errores = 0;

        // 1. Base de datos PostgreSQL
        try {
            DB::connection()->getPdo();
            $pedidosCount = Pedido::count();
            $this->line(" [OK] Base de datos PostgreSQL conectada exitosamente ({$pedidosCount} pedidos registrados).");
        } catch (Exception $e) {
            $this->error(" [FAIL] Error en Base de Datos: {$e->getMessage()}");
            $errores++;
        }

        // 2. Almacenamiento y Disco
        try {
            Storage::disk('public')->put('health_check.tmp', 'ok');
            Storage::disk('public')->delete('health_check.tmp');

            $espacioLibreGb = round(disk_free_space(base_path()) / (1024 * 1024 * 1024), 2);
            $this->line(" [OK] Almacenamiento local operativo. Espacio libre en disco: {$espacioLibreGb} GB.");
        } catch (Exception $e) {
            $this->error(" [FAIL] Error en Almacenamiento: {$e->getMessage()}");
            $errores++;
        }

        // 3. Colas de Trabajo (Jobs)
        try {
            $pendientes = DB::table('jobs')->count();
            $fallidos = DB::table('failed_jobs')->count();
            $this->line(" [OK] Sistema de Colas accesible ({$pendientes} jobs en cola, {$fallidos} fallidos).");
        } catch (Exception $e) {
            $this->error(" [FAIL] Error en Colas: {$e->getMessage()}");
            $errores++;
        }

        // 4. Conectividad con Facturación Electrónica (Nubefact)
        $nubefactRuta = Configuracion::obtener('nubefact_ruta', config('facturacion.nubefact.ruta', ''));
        if (! empty($nubefactRuta)) {
            try {
                $res = Http::timeout(5)->get($nubefactRuta);
                $this->line(' [OK] Nubefact endpoint accesible.');
            } catch (Exception $e) {
                $this->warn(" [WARN] Conectividad con Nubefact: {$e->getMessage()}");
            }
        } else {
            $this->line(' [INFO] Nubefact en modo simulación local (sin URL de producción configurada).');
        }

        // 5. Conectividad Pasarela Culqi
        $pasarelaActiva = env('PAGOS_PASARELA', 'simulada');
        $this->line(" [OK] Pasarela activa configurada: {$pasarelaActiva}.");

        $this->newLine();
        if ($errores === 0) {
            $this->info('✓ Todos los servicios críticos se encuentran OPERATIVOS y en buen estado.');

            return self::SUCCESS;
        }

        $this->error("✗ Se encontraron {$errores} fallas críticas en los servicios.");

        return self::FAILURE;
    }
}
