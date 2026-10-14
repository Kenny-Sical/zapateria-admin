<?php

namespace App\Support;

use App\Exceptions\WooCommerceApiException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class FriendlyError
{
    public static function message(Throwable $e, string $action): string
    {
        if (! $e instanceof WooCommerceApiException) {
            report($e);
        }

        $reason = match (true) {
            $e instanceof ConnectionException => 'No se pudo conectar con la tienda. Inténtalo de nuevo en unos minutos.',
            $e instanceof WooCommerceApiException && $e->errorCode === 'product_invalid_sku' => 'Ya existe un producto con ese SKU.',
            $e instanceof WooCommerceApiException && $e->errorCode === 'term_exists' => 'Ya existe un registro con ese nombre.',
            $e instanceof WooCommerceApiException && $e->getCode() === 404 => 'El registro ya no existe o fue eliminado.',
            $e instanceof WooCommerceApiException && ($e->getCode() === 401 || $e->getCode() === 403) => 'La tienda rechazó la solicitud. Contacta al administrador del sistema.',
            default => 'Inténtalo de nuevo en unos minutos.',
        };

        return "{$action}. {$reason}";
    }
}
