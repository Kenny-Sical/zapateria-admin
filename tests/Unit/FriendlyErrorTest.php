<?php

namespace Tests\Unit;

use App\Exceptions\WooCommerceApiException;
use App\Support\FriendlyError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class FriendlyErrorTest extends TestCase
{
    public function test_connection_exception_returns_friendly_message_and_reports(): void
    {
        Exceptions::fake();

        $exception = new ConnectionException('Connection timeout');
        $result = FriendlyError::message($exception, 'No se pudo cargar el inventario');

        $this->assertSame(
            'No se pudo cargar el inventario. No se pudo conectar con la tienda. Inténtalo de nuevo en unos minutos.',
            $result
        );
        Exceptions::assertReported(ConnectionException::class);
    }

    public function test_wc_exception_with_duplicate_sku_code_returns_friendly_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Invalid SKU', 400, 'product_invalid_sku');
        $result = FriendlyError::message($exception, 'No se pudo guardar el producto');

        $this->assertSame(
            'No se pudo guardar el producto. Ya existe un producto con ese SKU.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }

    public function test_wc_exception_with_term_exists_code_returns_friendly_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Term already exists', 400, 'term_exists');
        $result = FriendlyError::message($exception, 'No se pudo crear la categoría');

        $this->assertSame(
            'No se pudo crear la categoría. Ya existe un registro con ese nombre.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }

    public function test_wc_exception_with_404_status_returns_friendly_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Resource not found', 404);
        $result = FriendlyError::message($exception, 'No se pudo cargar la categoría');

        $this->assertSame(
            'No se pudo cargar la categoría. El registro ya no existe o fue eliminado.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }

    public function test_wc_exception_with_401_status_returns_friendly_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Unauthorized', 401);
        $result = FriendlyError::message($exception, 'No se pudo actualizar el estado del pedido');

        $this->assertSame(
            'No se pudo actualizar el estado del pedido. La tienda rechazó la solicitud. Contacta al administrador del sistema.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }

    public function test_wc_exception_with_403_status_returns_friendly_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Forbidden', 403);
        $result = FriendlyError::message($exception, 'No se pudo eliminar el color');

        $this->assertSame(
            'No se pudo eliminar el color. La tienda rechazó la solicitud. Contacta al administrador del sistema.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }

    public function test_generic_exception_returns_default_retry_message_and_reports(): void
    {
        Exceptions::fake();

        $exception = new RuntimeException('Unexpected database failure');
        $result = FriendlyError::message($exception, 'No se pudieron cargar los pedidos');

        $this->assertSame(
            'No se pudieron cargar los pedidos. Inténtalo de nuevo en unos minutos.',
            $result
        );
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_wc_exception_with_unknown_status_returns_default_retry_message_and_is_not_reported(): void
    {
        Exceptions::fake();

        $exception = new WooCommerceApiException('Internal server error', 500);
        $result = FriendlyError::message($exception, 'No se pudo cargar el inventario');

        $this->assertSame(
            'No se pudo cargar el inventario. Inténtalo de nuevo en unos minutos.',
            $result
        );
        Exceptions::assertNotReported(WooCommerceApiException::class);
    }
}
