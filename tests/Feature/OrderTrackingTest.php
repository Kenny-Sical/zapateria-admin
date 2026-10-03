<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WooCommerceApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('wp_users')) {
            Schema::create('wp_users', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->string('user_login')->default('admin');
                $table->string('user_email')->default('admin@example.com');
                $table->string('user_pass')->default('');
                $table->string('display_name')->default('Admin');
                $table->string('user_nicename')->default('admin');
                $table->string('user_url')->default('');
                $table->dateTime('user_registered')->nullable();
                $table->string('user_activation_key')->default('');
                $table->integer('user_status')->default(0);
            });
        }

        if (!Schema::hasTable('wp_usermeta')) {
            Schema::create('wp_usermeta', function (Blueprint $table) {
                $table->bigIncrements('umeta_id');
                $table->unsignedBigInteger('user_id');
                $table->string('meta_key');
                $table->longText('meta_value')->nullable();
            });
        }

        $this->user = User::create([
            'user_login'   => 'admin_test',
            'user_email'   => 'admin_test@lizzglamour.com',
            'user_pass'    => 'secret123',
            'display_name' => 'Admin Test',
        ]);
        $this->user->setRole(0);
    }

    /**
     * Test getOrdersPaginated returns mapped orders and headers from WooCommerce REST API.
     */
    public function test_get_orders_paginated_returns_mapped_orders_and_headers(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/orders*' => Http::response([
                [
                    'id'                   => 101,
                    'number'               => '101',
                    'status'               => 'processing',
                    'currency'             => 'GTQ',
                    'currency_symbol'      => 'Q',
                    'date_created'         => '2026-10-01T12:00:00',
                    'total'                => '250.00',
                    'total_tax'            => '0.00',
                    'shipping_total'       => '25.00',
                    'discount_total'       => '0.00',
                    'payment_method_title' => 'Pago contra entrega',
                    'billing'              => [
                        'first_name' => 'María',
                        'last_name'  => 'López',
                        'email'      => 'maria@example.com',
                        'phone'      => '12345678',
                    ],
                    'shipping'             => [],
                    'line_items'           => [
                        [
                            'id'         => 1,
                            'name'       => 'Zapatos Tacón Elegante',
                            'product_id' => 10,
                            'quantity'   => 1,
                            'price'      => 225.00,
                            'total'      => 225.00,
                            'sku'        => 'TAC-01',
                            'meta_data'  => [
                                ['key' => 'pa_color', 'value' => 'Negro', 'display_key' => 'Color', 'display_value' => 'Negro'],
                                ['key' => 'pa_talla', 'value' => '38', 'display_key' => 'Talla', 'display_value' => '38'],
                            ],
                        ],
                    ],
                ],
            ], 200, [
                'X-WP-Total'      => '1',
                'X-WP-TotalPages' => '1',
            ]),
        ]);

        $apiService = new WooCommerceApiService();
        $result = $apiService->getOrdersPaginated(['page' => 1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('totalPages', $result);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['totalPages']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals(101, $result['data'][0]['id']);
        $this->assertEquals('processing', $result['data'][0]['status']);
    }

    /**
     * Test OrderController index returns 200 with orders and metrics.
     */
    public function test_order_controller_index_returns_200_with_orders_and_metrics(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/orders*' => Http::response([
                [
                    'id'                   => 201,
                    'number'               => '201',
                    'status'               => 'completed',
                    'currency'             => 'GTQ',
                    'currency_symbol'      => 'Q',
                    'date_created'         => '2026-10-01T10:00:00',
                    'total'                => '350.00',
                    'total_tax'            => '0.00',
                    'shipping_total'       => '25.00',
                    'discount_total'       => '0.00',
                    'payment_method_title' => 'Tarjeta de Crédito',
                    'billing'              => [
                        'first_name' => 'Carlos',
                        'last_name'  => 'Gómez',
                        'email'      => 'carlos@example.com',
                        'phone'      => '87654321',
                    ],
                    'shipping'             => [],
                    'line_items'           => [
                        [
                            'id'         => 10,
                            'name'       => 'Botas de Cuero Premium',
                            'product_id' => 50,
                            'quantity'   => 1,
                            'price'      => 325.00,
                            'total'      => 325.00,
                            'sku'        => 'BOT-01',
                            'meta_data'  => [],
                        ],
                    ],
                ],
                [
                    'id'                   => 202,
                    'number'               => '202',
                    'status'               => 'processing',
                    'currency'             => 'GTQ',
                    'currency_symbol'      => 'Q',
                    'date_created'         => '2026-10-01T11:00:00',
                    'total'                => '150.00',
                    'total_tax'            => '0.00',
                    'shipping_total'       => '0.00',
                    'discount_total'       => '0.00',
                    'payment_method_title' => 'Transferencia',
                    'billing'              => [
                        'first_name' => 'Ana',
                        'last_name'  => 'Martínez',
                        'email'      => 'ana@example.com',
                        'phone'      => '55556677',
                    ],
                    'shipping'             => [],
                    'line_items'           => [],
                ],
            ], 200, [
                'X-WP-Total'      => '2',
                'X-WP-TotalPages' => '1',
            ]),
        ]);

        $response = $this->actingAs($this->user)->get('/orders');

        $response->assertStatus(200);
        $response->assertViewIs('orders.read');
        $response->assertViewHas('orders');
        $response->assertViewHas('metrics');
        $response->assertViewHas('currentStatus', 'all');
        $response->assertViewHas('search', '');

        $metrics = $response->viewData('metrics');
        $this->assertEquals(2, $metrics['total_orders']);
        $this->assertEquals(500.00, $metrics['total_sales']);
        $this->assertEquals(1, $metrics['completed_count']);
        $this->assertEquals(1, $metrics['pending_processing_count']);

        $response->assertSee('#201');
        $response->assertSee('#202');
        $response->assertSee('Carlos Gómez');
        $response->assertSee('Ana Martínez');
        $response->assertSee('Q 350.00');
    }

    /**
     * Test updateStatus updates order in WooCommerce API and redirects back with success.
     */
    public function test_update_status_updates_order_in_woocommerce_api(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/orders/201*' => Http::response([
                'id'     => 201,
                'status' => 'completed',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->from('/orders')
            ->put('/orders/201/status', [
                'status' => 'completed',
            ]);

        $response->assertRedirect('/orders');
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'orders/201')
                && $request->method() === 'PUT'
                && ($request['status'] ?? null) === 'completed';
        });
    }

    /**
     * Test updateStatus rejects invalid statuses.
     */
    public function test_update_status_validates_allowed_status_values(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/orders')
            ->put('/orders/201/status', [
                'status' => 'invalid_status_xyz',
            ]);

        $response->assertRedirect('/orders');
        $response->assertSessionHasErrors('status');
    }

    /**
     * Test unauthenticated users cannot access orders index.
     */
    public function test_unauthenticated_user_cannot_access_orders(): void
    {
        $response = $this->get('/orders');
        $response->assertRedirect('/login');
    }
}
