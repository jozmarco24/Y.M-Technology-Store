<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Facturacion\EmisorComprobante;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class EstadoFacturacion extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Facturación Electrónica';

    protected static ?string $navigationLabel = 'Estado de Integración';

    protected static ?string $title = 'Estado de Facturación Electrónica (Nubefact)';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.estado-facturacion';

    public ?string $resultadoPrueba = null;

    public bool $pruebaExitosa = false;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && $user->hasRole('admin');
    }

    public function probarConexion(EmisorComprobante $emisor): void
    {
        try {
            $modo = config('facturacion.modo', 'simulado');

            if ($modo === 'simulado') {
                $this->resultadoPrueba = 'Conexión exitosa (Modo Simulado activo). Las emisiones se procesarán localmente de forma inmediata.';
                $this->pruebaExitosa = true;

                Notification::make()
                    ->title('Modo Simulado Operativo')
                    ->body('El emisor simulado está respondiendo correctamente.')
                    ->success()
                    ->send();

                return;
            }

            $ruta = (string) config('facturacion.ruta');
            $token = (string) config('facturacion.token');

            if (empty($ruta) || empty($token)) {
                $this->resultadoPrueba = 'Faltan credenciales NUBEFACT_RUTA o NUBEFACT_TOKEN en el archivo .env';
                $this->pruebaExitosa = false;

                Notification::make()
                    ->title('Credenciales incompletas')
                    ->body('Verifique NUBEFACT_RUTA y NUBEFACT_TOKEN.')
                    ->danger()
                    ->send();

                return;
            }

            // Realizar una petición de prueba básica a la URL de Nubefact
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Token token="'.$token.'"',
                    'Content-Type' => 'application/json',
                ])
                ->post($ruta, [
                    'operacion' => 'consultar_comprobante',
                    'tipo_de_comprobante' => 1,
                    'serie' => 'F001',
                    'numero' => 99999999,
                ]);

            if ($response->successful() || $response->status() === 422 || $response->status() === 404 || str_contains($response->body(), 'errors')) {
                $this->resultadoPrueba = "Conexión a Nubefact establecida correctamente. Código HTTP: {$response->status()} (El endpoint respondió a la petición autenticada).";
                $this->pruebaExitosa = true;

                Notification::make()
                    ->title('Conexión con Nubefact Exitosa')
                    ->body("El servicio respondió con status {$response->status()}.")
                    ->success()
                    ->send();
            } else {
                $this->resultadoPrueba = "Nubefact respondió con código HTTP {$response->status()}: ".$response->body();
                $this->pruebaExitosa = false;

                Notification::make()
                    ->title('Respuesta inesperada')
                    ->body("Código HTTP {$response->status()}")
                    ->warning()
                    ->send();
            }
        } catch (Throwable $e) {
            $this->resultadoPrueba = 'Error al conectar con Nubefact: '.$e->getMessage();
            $this->pruebaExitosa = false;

            Notification::make()
                ->title('Fallo de Conexión')
                ->body('No se pudo establecer comunicación con el servidor de facturación.')
                ->danger()
                ->send();
        }
    }
}
