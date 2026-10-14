<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WooCommerceApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ColorManagementTest extends TestCase
{
    protected User $adminUser;

    protected User $editorUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        if (! Schema::hasTable('wp_users')) {
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

        if (! Schema::hasTable('wp_usermeta')) {
            Schema::create('wp_usermeta', function (Blueprint $table) {
                $table->bigIncrements('umeta_id');
                $table->unsignedBigInteger('user_id');
                $table->string('meta_key');
                $table->longText('meta_value')->nullable();
            });
        }

        $this->adminUser = User::create([
            'user_login' => 'admin_test',
            'user_email' => 'admin_test@lizzglamour.com',
            'user_pass' => 'secret123',
            'display_name' => 'Admin Test',
        ]);
        $this->adminUser->setRole(0);

        $this->editorUser = User::create([
            'user_login' => 'editor_test',
            'user_email' => 'editor_test@lizzglamour.com',
            'user_pass' => 'secret123',
            'display_name' => 'Editor Test',
        ]);
        $this->editorUser->setRole(1);

        // Pre-seed cache with ensureAttributesAndTermsExist structure so no attribute bootstrapping hits the network
        Cache::put('wc_attributes_and_terms', [
            'colors' => [
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
                ['id' => 12, 'name' => 'Café', 'slug' => 'cafe'],
            ],
            'sizes' => [
                ['id' => 20, 'name' => '38', 'size' => '38', 'slug' => '38'],
            ],
            'audiences' => [
                ['id' => 30, 'name' => 'Mujer', 'slug' => 'mujer'],
            ],
            'colorAttributeId' => 1,
            'sizeAttributeId' => 2,
            'audienceAttributeId' => 3,
        ]);
    }

    /**
     * 1. index lists colors; search filters (AC1).
     */
    public function test_index_lists_colors_and_search_filters_case_insensitively(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro', 'count' => 3],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco', 'count' => 5],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/colors');
        $response->assertStatus(200);
        $response->assertViewIs('colors.read');
        $response->assertSee('Negro');
        $response->assertSee('Blanco');
        $response->assertDontSee('<th>Slug</th>', false);

        // Search filter matching Blanco
        $filteredResponse = $this->actingAs($this->adminUser)->get('/colors?search=bla');
        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSee('Blanco');
        $filteredResponse->assertDontSee('Negro');

        // Case-insensitive search filter matching Negro
        $filteredCase = $this->actingAs($this->adminUser)->get('/colors?search=NEGRO');
        $filteredCase->assertStatus(200);
        $filteredCase->assertSee('Negro');
        $filteredCase->assertDontSee('Blanco');
    }

    /**
     * Test create and edit views render successfully.
     */
    public function test_create_and_edit_views_render(): void
    {
        $responseCreate = $this->actingAs($this->adminUser)->get('/colors/create');
        $responseCreate->assertStatus(200);
        $responseCreate->assertViewIs('colors.create');

        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms/10*' => Http::response([
                'id' => 10,
                'name' => 'Negro',
                'slug' => 'negro',
            ], 200),
        ]);

        $responseEdit = $this->actingAs($this->adminUser)->get('/colors/10/edit');
        $responseEdit->assertStatus(200);
        $responseEdit->assertViewIs('colors.edit');
        $responseEdit->assertSee('Negro');
        $responseEdit->assertDontSee('Slug');
        $responseEdit->assertDontSee('name="slug"', false);
    }

    /**
     * 2. store sends POST with name + slug; wc_attributes_and_terms cache key is gone and the inventory cache version changed (AC2, AC6).
     */
    public function test_store_creates_color_and_invalidates_caches(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                    ], 200);
                }
                if ($request->method() === 'POST') {
                    return Http::response([
                        'id' => 15,
                        'name' => 'Azul Marino',
                        'slug' => 'azul-marino',
                    ], 201);
                }

                return Http::response([], 404);
            },
        ]);

        Cache::forever(WooCommerceApiService::INVENTORY_CACHE_VERSION_KEY, 'initial-version-uuid');
        $this->assertTrue(Cache::has('wc_attributes_and_terms'));

        $response = $this->actingAs($this->adminUser)->post('/colors', [
            'name' => 'Azul Marino',
        ]);

        $response->assertRedirect('/colors');
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   str_contains($request->url(), 'products/attributes/1/terms') &&
                   $request['name'] === 'Azul Marino' &&
                   $request['slug'] === 'azul-marino';
        });

        // AC6: cache key is gone and inventory cache version changed
        $this->assertFalse(Cache::has('wc_attributes_and_terms'));
        $this->assertNotEquals('initial-version-uuid', Cache::get(WooCommerceApiService::INVENTORY_CACHE_VERSION_KEY));
    }

    /**
     * 3. store duplicate (different case, and slug collision e.g. 'Cafe' vs existing 'Café') -> no POST, session has errors (AC3).
     */
    public function test_store_duplicate_is_rejected_without_post(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 12, 'name' => 'Café', 'slug' => 'cafe'],
            ], 200),
        ]);

        // Duplicate by lowercased name ('negro' vs 'Negro')
        $responseCase = $this->actingAs($this->adminUser)->post('/colors', [
            'name' => 'negro',
        ]);
        $responseCase->assertSessionHasErrors(['name' => 'Ya existe un color con ese nombre.']);

        // Duplicate by slug collision ('Cafe' vs 'Café' with slug 'cafe')
        $responseSlug = $this->actingAs($this->adminUser)->post('/colors', [
            'name' => 'Cafe',
        ]);
        $responseSlug->assertSessionHasErrors(['name' => 'Ya existe un color con ese nombre.']);

        Http::assertNotSent(function ($request) {
            return $request->method() === 'POST' && str_contains($request->url(), 'products/attributes/1/terms');
        });
    }

    /**
     * 4. update sends PUT whose body has name and NO slug key (AC4).
     */
    public function test_update_sends_put_with_name_only_and_no_slug(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1/terms/10*' => Http::response([
                'id' => 10,
                'name' => 'Negro Azabache',
                'slug' => 'negro',
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->put('/colors/10', [
            'name' => 'Negro Azabache',
        ]);

        $response->assertRedirect('/colors');
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), 'products/attributes/1/terms/10')) {
                $data = $request->data();

                return ($data['name'] ?? null) === 'Negro Azabache' && ! array_key_exists('slug', $data);
            }

            return false;
        });
    }

    /**
     * 5. update to another color's name -> no PUT (AC3).
     */
    public function test_update_duplicate_to_another_color_name_is_rejected_without_put(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
                ['id' => 12, 'name' => 'Café', 'slug' => 'cafe'],
            ], 200),
        ]);

        // Attempt to rename color 10 to color 11's name 'blanco'
        $responseName = $this->actingAs($this->adminUser)->put('/colors/10', [
            'name' => 'blanco',
        ]);
        $responseName->assertSessionHasErrors(['name' => 'Ya existe un color con ese nombre.']);

        // Attempt to rename color 10 to slug collision 'Cafe' with color 12
        $responseSlug = $this->actingAs($this->adminUser)->put('/colors/10', [
            'name' => 'Cafe',
        ]);
        $responseSlug->assertSessionHasErrors(['name' => 'Ya existe un color con ese nombre.']);

        Http::assertNotSent(function ($request) {
            return $request->method() === 'PUT';
        });

        // Updating color 10 to its own name should not trigger duplicate rejection
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1/terms/10*' => Http::response([
                'id' => 10,
                'name' => 'Negro',
                'slug' => 'negro',
            ], 200),
        ]);

        $responseSelf = $this->actingAs($this->adminUser)->put('/colors/10', [
            'name' => 'Negro',
        ]);
        $responseSelf->assertSessionHasNoErrors();
        $responseSelf->assertRedirect('/colors');
    }

    /**
     * 6a. destroy when in use -> no DELETE (AC5).
     */
    public function test_destroy_when_in_use_blocks_delete(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1' => Http::response([
                'id' => 1,
                'slug' => 'pa_color',
            ], 200),
            '*/wp-json/wc/v3/products*' => Http::response([
                ['id' => 101, 'name' => 'Zapato Tacón'],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->delete('/colors/10');
        $response->assertRedirect('/colors');
        $response->assertSessionHas('error', 'No se puede eliminar: hay productos que usan este color.');

        Http::assertNotSent(function ($request) {
            return $request->method() === 'DELETE';
        });
    }

    /**
     * 6b. destroy when last color -> no DELETE (AC5).
     */
    public function test_destroy_when_last_color_blocks_delete(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->delete('/colors/10');
        $response->assertRedirect('/colors');
        $response->assertSessionHas('error', 'No se puede eliminar el último color.');

        Http::assertNotSent(function ($request) {
            return $request->method() === 'DELETE';
        });
    }

    /**
     * 6c. destroy unused -> DELETE with force=true (AC5).
     */
    public function test_destroy_unused_color_sends_delete_with_force(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
                ['id' => 11, 'name' => 'Blanco', 'slug' => 'blanco'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1' => Http::response([
                'id' => 1,
                'slug' => 'pa_color',
            ], 200),
            '*/wp-json/wc/v3/products?*' => Http::response([], 200),
            '*/wp-json/wc/v3/products/attributes/1/terms/10*' => Http::response(['id' => 10], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->delete('/colors/10');
        $response->assertRedirect('/colors');
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), 'products/attributes/1/terms/10') &&
                   str_contains($request->url(), 'force=true');
        });
    }

    /**
     * 7. role 1 user -> 403 on colors.index; guest -> redirect to login (AC7, AC9).
     */
    public function test_access_control_and_sidebar_visibility(): void
    {
        // Guest
        $guestResponse = $this->get('/colors');
        $guestResponse->assertRedirect('/login');

        // Role 1 Editor -> 403
        $role1Response = $this->actingAs($this->editorUser)->get('/colors');
        $role1Response->assertStatus(403);

        // Role 0 SuperAdmin -> 200 with Colores link in sidebar (AC9)
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([], 200),
        ]);

        $role0Response = $this->actingAs($this->adminUser)->get('/colors');
        $role0Response->assertStatus(200);
        $role0Response->assertSee(route('colors.index'));
        $role0Response->assertSee('Colores');

        // Role 1 cannot see Colores link in sidebar
        Http::fake([
            '*/wp-json/wc-dsm/v1/admin/inventory*' => Http::response(['data' => []], 200),
        ]);
        $editorInventory = $this->actingAs($this->editorUser)->get('/inventory');
        $editorInventory->assertStatus(200);
        $editorInventory->assertDontSee(route('colors.index'));
    }

    /**
     * 8. WC failure (fake 500) on store -> redirect back with session error, not a 500 (AC8).
     */
    public function test_wc_failure_on_store_flashes_error_and_does_not_return_500(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products/attributes/1/terms*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response(['message' => 'Internal Server Error from WC'], 500);
            },
        ]);

        $response = $this->actingAs($this->adminUser)->post('/colors', [
            'name' => 'Morado',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }
}
