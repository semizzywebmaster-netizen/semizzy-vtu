<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Semizzy\Addons\AIChatbot\Services\AIProviderService;
use Tests\TestCase;

class AIChatbotAddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_registered_with_expected_identifier_and_routes(): void
    {
        $manifest = require base_path('addons/ai.chatbot/manifest.php');

        $this->assertSame('ai.chatbot', $manifest['identifier']);
        $this->assertSame('AI & Automation', $manifest['category']);
        $this->assertContains('addons/ai.chatbot/routes/web.php', $manifest['web_route_files']);
        $this->assertContains('2026_10_09_100000_create_ai_chatbot_tables.php', $manifest['migrations']);
        foreach ($manifest['permissions'] as $permission) {
            $this->assertIsString($permission);
            $this->assertNotSame('', $permission);
        }
    }

    public function test_openai_provider_connection_test_uses_encrypted_credentials_and_parses_response(): void
    {
        $migration = require base_path('addons/ai.chatbot/database/migrations/2026_10_09_100000_create_ai_chatbot_tables.php');
        $migration->up();

        $apiKey = 'unit-test-secret-key';
        $id = DB::table('ai_chatbot_providers')->insertGetId([
            'name' => 'Test OpenAI', 'driver' => 'openai', 'api_key_encrypted' => Crypt::encryptString($apiKey),
            'model' => 'gpt-4o-mini', 'enabled' => true, 'priority' => 1, 'timeout_seconds' => 5,
            'max_output_tokens' => 64, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertNotSame($apiKey, DB::table('ai_chatbot_providers')->where('id', $id)->value('api_key_encrypted'));

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
                'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 1],
            ], 200),
        ]);

        $result = app(AIProviderService::class)->test((array) DB::table('ai_chatbot_providers')->find($id));

        $this->assertTrue($result['ok']);
        $this->assertSame('Provider returned a valid response.', $result['message']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer '.$apiKey));
    }

    public function test_chat_service_does_not_call_any_provider_when_none_is_enabled(): void
    {
        $migration = require base_path('addons/ai.chatbot/database/migrations/2026_10_09_100000_create_ai_chatbot_tables.php');
        $migration->up();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The AI assistant is not configured yet.');

        app(AIProviderService::class)->answer([['role' => 'user', 'content' => 'Hello']], 'Safe system prompt');
    }
}
