<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WooCommerceApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryIndexTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

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

        // Mock ensureAttributesAndTermsExist requests
        Http::fake([
            '*/wp-json/wc/v3/products/attributes*' => Http::response([
                ['id' => 1, 'name' => 'Color', 'slug' => 'pa_color'],
                ['id' => 2, 'name' => 'Talla', 'slug' => 'pa_talla'],
                ['id' => 3, 'name' => 'Público', 'slug' => 'pa_publico'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/2/terms*' => Http::response([
                ['id' => 20, 'name' => '38', 'slug' => '38'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/3/terms*' => Http::response([
                ['id' => 30, 'name' => 'Mujer', 'slug' => 'mujer'],
            ], 200),
            '*/wp-json/wc/v3/products/categories*' => Http::response([
                ['id' => 5, 'name' => 'Botas'],
            ], 200),
        ]);
    }

    public function test_index_makes_exactly_one_request_to_admin_inventory_and_filters_are_forwarded(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response([
                'data' => [
                    [
                        'id' => 100,
                        'name' => 'Bota Larga',
                        'sku' => 'BOT-001',
                        'price' => '250.00',
                        'status' => 'publish',
                        'audience' => 'mujer',
                        'categories' => [['id' => 5, 'name' => 'Botas']],
                        'images' => [],
                        'variations' => [
                            [
                                'id' => 101,
                                'regular_price' => '250.00',
                                'stock_quantity' => 10,
                                'attributes' => [
                                    ['name' => 'Color', 'option' => 'Negro'],
                                    ['name' => 'Talla', 'option' => '38'],
                                ]
                            ]
                        ]
                    ]
                ],
                'total' => 1,
                'total_pages' => 1,
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->get('/inventory?categories[]=5&colors[]=10&colors[]=11&sizes[]=20&audience=mujer&search=boot&per_page=500');

        $response->assertStatus(200);
        $response->assertViewHas('products');

        $products = $response->viewData('products');
        $this->assertEquals(1, $products->total());

        $first = $products->first();
        $this->assertEquals(10, $first->total_stock);
        $this->assertEquals('Mujer', $first->audience_label);
        $this->assertArrayHasKey('Negro', $first->matrix_data);
        $this->assertEquals(10, $first->matrix_data['Negro']['38']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'wc-dsm/v1/admin/inventory')) {
                return $request['category'] == '5' &&
                       $request['colors'] == '10,11' &&
                       $request['sizes'] == '20' &&
                       $request['audience'] == 'mujer' &&
                       $request['search'] == 'boot' &&
                       $request['per_page'] == 100; // Clamped to 100
            }
            return false;
        });

        $this->assertCount(1, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/variations');
        });
    }

    public function test_api_failure_renders_view_with_empty_paginator(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response('Error', 500),
        ]);

        $response = $this->actingAs($this->user)->get('/inventory');
        $response->assertStatus(200);

        $products = $response->viewData('products');
        $this->assertEquals(0, $products->total());
    }

    public function test_service_unit_level_hits_correct_endpoints(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
            '*/wp-json/wc/v3/orders*' => Http::response([], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']),
        ]);

        $apiService = new WooCommerceApiService();
        $apiService->getAdminInventory();
        $apiService->getOrdersPaginated();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'wc/v3/orders');
        });
    }

    public function test_inventory_cache_returns_cached_data_for_identical_calls(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
        ]);

        $apiService = app(WooCommerceApiService::class);
        $result1 = $apiService->getAdminInventory(['page' => 1]);
        $result2 = $apiService->getAdminInventory(['page' => '1']);

        $this->assertEquals($result1, $result2);

        $this->assertCount(1, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }

    public function test_inventory_cache_misses_for_different_params(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
        ]);

        $apiService = app(WooCommerceApiService::class);
        $apiService->getAdminInventory(['page' => 1]);
        $apiService->getAdminInventory(['page' => 2]);

        $this->assertCount(2, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }

    public function test_inventory_cache_is_invalidated_after_mutations(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
            '*/wp-json/wc/v3/orders/*' => Http::response([], 200),
            '*/wp-json/wc/v3/products/*' => Http::response(['id' => 1], 200),
        ]);

        $apiService = app(WooCommerceApiService::class);

        $apiService->getAdminInventory();
        $apiService->updateOrderStatus(1, 'completed');
        $apiService->getAdminInventory();
        $apiService->updateProduct(1, ['name' => 'New']);
        $apiService->getAdminInventory();

        $this->assertCount(3, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }

    public function test_inventory_cache_does_not_cache_exceptions(): void
    {
        Http::fakeSequence()
            ->push('Error', 500)
            ->push(['data' => [], 'total' => 0, 'total_pages' => 0], 200);

        $apiService = app(WooCommerceApiService::class);

        $thrown = false;
        try {
            $apiService->getAdminInventory();
        } catch (\Exception $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'A 500 response must throw.');

        $apiService->getAdminInventory();

        $this->assertCount(2, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }

    public function test_inventory_cache_is_disabled_when_ttl_is_zero(): void
    {
        config(['woocommerce.inventory_cache_ttl' => 0]);

        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
        ]);

        $apiService = app(WooCommerceApiService::class);
        $apiService->getAdminInventory();
        $apiService->getAdminInventory();

        $this->assertCount(2, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }

    public function test_inventory_cache_works_at_controller_level(): void
    {
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => [], 'total' => 0, 'total_pages' => 0], 200),
        ]);

        $this->actingAs($this->user)->get('/inventory?page=1');
        $this->actingAs($this->user)->get('/inventory?page=1');

        $this->assertCount(1, Http::recorded(function ($request) {
            return str_contains($request->url(), 'wc-dsm/v1/admin/inventory');
        }));
    }
}
