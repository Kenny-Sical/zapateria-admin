<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductImageStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryImageUploadTest extends TestCase
{
    protected User $user;

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

        $this->user = User::create([
            'user_login' => 'admin_test',
            'user_email' => 'admin_test@lizzglamour.com',
            'user_pass' => 'secret123',
            'display_name' => 'Admin Test',
        ]);
        $this->user->setRole(0);

        Storage::fake('public');

        Http::fake([
            '*/wp-json/wc/v3/products/attributes*' => Http::response([
                ['id' => 1, 'name' => 'Color', 'slug' => 'pa_color'],
                ['id' => 2, 'name' => 'Talla', 'slug' => 'pa_talla'],
                ['id' => 3, 'name' => 'Público', 'slug' => 'pa_publico'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/1/terms*' => Http::response([
                ['id' => 10, 'name' => 'Negro', 'slug' => 'negro'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/2/terms*' => Http::response([
                ['id' => 20, 'name' => '38', 'slug' => '38'],
            ], 200),
            '*/wp-json/wc/v3/products/attributes/3/terms*' => Http::response([
                ['id' => 30, 'name' => 'Mujer', 'slug' => 'mujer'],
                ['id' => 31, 'name' => 'Hombre', 'slug' => 'hombre'],
                ['id' => 32, 'name' => 'Niño', 'slug' => 'nino'],
            ], 200),
            '*/wp-json/wc/v3/products/categories*' => Http::response([
                ['id' => 5, 'name' => 'Botas'],
            ], 200),
            '*/wp-json/wc/v3/products/*/variations/batch*' => Http::response([], 200),
            '*/wp-json/wc/v3/products/*/variations*' => Http::response([], 200),
        ]);
    }

    public function test_storing_product_with_image_sends_url_and_deletes_temp_file(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products*' => Http::response(['id' => 100], 201),
        ]);

        $image = UploadedFile::fake()->image('shoe.jpg');

        $response = $this->actingAs($this->user)->post('/inventory/store', [
            'name' => 'New Shoe',
            'sku' => 'SHOE-001',
            'price' => '150.00',
            'category_id' => 5,
            'audience' => 'mujer',
            'image' => $image,
            'inventory' => [
                10 => [20 => 5],
            ],
        ]);

        $response->assertRedirect(route('inventory.index'));
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/wp-json/wc/v3/products') && $request->method() === 'POST') {
                return isset($request['images']) && str_contains($request['images'][0]['src'], '/storage/product-images/') && str_ends_with($request['images'][0]['src'], '.webp');
            }

            return false;
        });

        // The public disk should be empty because the file was deleted
        $files = Storage::disk('public')->allFiles();
        $this->assertEmpty($files);
    }

    public function test_storing_product_with_api_error_deletes_temp_file_and_returns_error(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/products*' => Http::response('Server Error', 500),
        ]);

        $image = UploadedFile::fake()->image('shoe.jpg');

        $response = $this->actingAs($this->user)->post('/inventory/store', [
            'name' => 'Error Shoe',
            'sku' => 'SHOE-ERR',
            'price' => '100.00',
            'category_id' => 5,
            'audience' => 'mujer',
            'image' => $image,
            'inventory' => [
                10 => [20 => 5],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // The public disk should be empty because the file was deleted even on error
        $files = Storage::disk('public')->allFiles();
        $this->assertEmpty($files);
    }

    public function test_product_image_storage_service(): void
    {
        $service = new ProductImageStorage;
        $image = UploadedFile::fake()->image('test.png');

        $result = $service->store($image, 'TEST-SKU');

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);

        $this->assertStringContainsString('product-images/', $result['path']);
        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertStringContainsString('/storage/', $result['url']);

        Storage::disk('public')->assertExists($result['path']);

        $service->delete($result['path']);

        Storage::disk('public')->assertMissing($result['path']);
    }
}
