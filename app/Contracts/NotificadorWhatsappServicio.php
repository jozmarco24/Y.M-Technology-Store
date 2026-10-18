<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\OrdenServicio;

interface NotificadorWhatsappServicio
{
    /**
     * Enviar notificación de orden de servicio al cliente vía WhatsApp.
     */
    public function notificar(OrdenServicio $orden, string $mensaje): bool;
}
