<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FlashMessageRenderingTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

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
            'user_login' => 'admin_flash_test',
            'user_email' => 'admin_flash_test@example.com',
            'user_pass' => 'secret123',
            'display_name' => 'Admin Flash Test',
        ]);
        $this->adminUser->setRole(0);
    }

    public function test_flash_message_with_quotes_renders_json_without_html_entities(): void
    {
        $message = 'Pedido #42 actualizado a "Completado" en la tienda de \'Prueba\'.';
        $encodedMessage = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['success' => $message])
            ->get('/dashboard');

        $response->assertStatus(200);

        $content = $response->getContent();

        // Extract the Swal.fire script block
        $this->assertMatchesRegularExpression('/Swal\.fire\(\{[\s\S]*?icon:\s*\'success\'[\s\S]*?\}\);/', $content);
        preg_match('/Swal\.fire\(\{[\s\S]*?icon:\s*\'success\'[\s\S]*?\}\);/', $content, $matches);
        $swalScript = $matches[0];

        $this->assertStringNotContainsString('&#039;', $swalScript);
        $this->assertStringNotContainsString('&quot;', $swalScript);
        $this->assertStringContainsString('text: '.$encodedMessage, $swalScript);
    }
}
