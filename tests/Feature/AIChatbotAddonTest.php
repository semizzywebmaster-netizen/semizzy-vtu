<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Semizzy\Addons\AIChatbot\Services\AIProviderService;
use Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Semizzy\Addons\AIChatbot\Http\Controllers\AIChatbotController;
use Semizzy\Addons\AIChatbot\Http\Controllers\AIChatbotAdminController;
use App\Services\Audit\AuditLogger;

class AIChatbotAddonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('ai_chatbot_conversations');
        Schema::create('ai_chatbot_conversations', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('visitor_hash', 64)->nullable(); $table->string('title')->default('New conversation');
            $table->string('status')->default('open'); $table->unsignedBigInteger('support_ticket_id')->nullable();
            $table->timestamp('last_message_at')->nullable(); $table->timestamps();
        });
        Schema::dropIfExists('ai_chatbot_providers');
        Schema::create('ai_chatbot_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('driver');
            $table->text('api_key_encrypted');
            $table->string('model');
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedSmallInteger('timeout_seconds')->default(20);
            $table->unsignedInteger('max_output_tokens')->default(600);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ai_chatbot_providers');
        Schema::dropIfExists('ai_chatbot_conversations');
        parent::tearDown();
    }

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The AI assistant is not configured yet.');

        app(AIProviderService::class)->answer([['role' => 'user', 'content' => 'Hello']], 'Safe system prompt');
    }

    public function test_answer_falls_back_to_the_next_enabled_provider_after_a_provider_error(): void
    {
        foreach ([
            ['name' => 'Primary', 'model' => 'primary-model', 'priority' => 1],
            ['name' => 'Fallback', 'model' => 'fallback-model', 'priority' => 2],
        ] as $provider) {
            DB::table('ai_chatbot_providers')->insert([
                'name' => $provider['name'], 'driver' => 'openai',
                'api_key_encrypted' => Crypt::encryptString('test-key-'.$provider['name']),
                'model' => $provider['model'], 'enabled' => true, 'priority' => $provider['priority'],
                'timeout_seconds' => 5, 'max_output_tokens' => 64, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'temporary provider failure']], 503)
                ->push([
                    'choices' => [['message' => ['content' => 'Fallback answer']]],
                    'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3],
                ], 200),
        ]);

        $answer = app(AIProviderService::class)->answer(
            [['role' => 'user', 'content' => 'Help me']],
            'Only answer safely.'
        );

        $this->assertSame('Fallback answer', $answer['content']);
        $this->assertSame('fallback-model', $answer['model']);
        Http::assertSentCount(2);
    }

    public function test_anthropic_adapter_sends_secret_in_header_and_parses_text_response(): void
    {
        $apiKey = 'anthropic-unit-secret';
        DB::table('ai_chatbot_providers')->insert([
            'name' => 'Test Anthropic', 'driver' => 'anthropic',
            'api_key_encrypted' => Crypt::encryptString($apiKey), 'model' => 'claude-test-model',
            'enabled' => true, 'priority' => 1, 'timeout_seconds' => 5, 'max_output_tokens' => 64,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Anthropic response']],
                'usage' => ['input_tokens' => 8, 'output_tokens' => 2],
            ], 200),
        ]);

        $answer = app(AIProviderService::class)->answer(
            [['role' => 'user', 'content' => 'Hello']], 'Safe system prompt'
        );

        $this->assertSame('Anthropic response', $answer['content']);
        $this->assertSame('anthropic', $answer['provider']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', $apiKey));
    }

    public function test_gemini_adapter_uses_header_authentication_and_parses_response(): void
    {
        $apiKey = 'gemini-unit-secret';
        DB::table('ai_chatbot_providers')->insert([
            'name' => 'Test Gemini', 'driver' => 'gemini',
            'api_key_encrypted' => Crypt::encryptString($apiKey), 'model' => 'gemini-test-model',
            'enabled' => true, 'priority' => 1, 'timeout_seconds' => 5, 'max_output_tokens' => 64,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Gemini response']]]]],
                'usageMetadata' => ['promptTokenCount' => 9, 'candidatesTokenCount' => 3],
            ], 200),
        ]);

        $answer = app(AIProviderService::class)->answer(
            [['role' => 'user', 'content' => 'Hello']], 'Safe system prompt'
        );

        $this->assertSame('Gemini response', $answer['content']);
        $this->assertSame('gemini', $answer['provider']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-test-model:generateContent')
            && $request->hasHeader('x-goog-api-key', $apiKey));
    }

    public function test_provider_authentication_failure_returns_a_safe_error_without_the_secret(): void
    {
        $apiKey = 'never-display-this-secret';
        DB::table('ai_chatbot_providers')->insert([
            'name' => 'Rejected OpenAI', 'driver' => 'openai',
            'api_key_encrypted' => Crypt::encryptString($apiKey), 'model' => 'gpt-4o-mini',
            'enabled' => true, 'priority' => 1, 'timeout_seconds' => 5, 'max_output_tokens' => 64,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'invalid api key']], 401)]);

        try {
            app(AIProviderService::class)->answer([['role' => 'user', 'content' => 'Hello']], 'Safe system prompt');
            $this->fail('Expected the rejected provider to fail safely.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rejected its credentials', $e->getMessage());
            $this->assertStringNotContainsString($apiKey, $e->getMessage());
            $this->assertStringNotContainsString('invalid api key', $e->getMessage());
        }
    }

    public function test_visitor_can_only_access_a_conversation_bound_to_their_visitor_cookie(): void
    {
        $ownerToken = str_repeat('a', 64);
        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_chatbot_conversations')->insert([
            'uuid' => $uuid, 'user_id' => null, 'visitor_hash' => hash('sha256', $ownerToken),
            'title' => 'Visitor conversation', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(AIChatbotController::class);
        $method = new \ReflectionMethod($controller, 'owned');
        $owned = $method->invoke($controller, Request::create('/', 'GET', [], ['ai_chatbot_visitor' => $ownerToken]), $uuid);
        $this->assertSame($uuid, $owned->uuid);

        try {
            $method->invoke($controller, Request::create('/', 'GET', [], ['ai_chatbot_visitor' => str_repeat('b', 64)]), $uuid);
            $this->fail('A different visitor must not read another visitor conversation.');
        } catch (\ReflectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e->getPrevious() ?? $e);
        }
    }

    public function test_missing_visitor_cookie_cannot_access_anonymous_conversation(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_chatbot_conversations')->insert([
            'uuid' => $uuid, 'user_id' => null, 'visitor_hash' => hash('sha256', str_repeat('c', 64)),
            'title' => 'Visitor conversation', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(AIChatbotController::class);
        $method = new \ReflectionMethod($controller, 'owned');
        try {
            $method->invoke($controller, Request::create('/', 'GET'), $uuid);
            $this->fail('A visitor without the ownership cookie must be rejected.');
        } catch (\ReflectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e->getPrevious() ?? $e);
        }
    }

    public function test_authenticated_users_cannot_access_another_users_conversation(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_chatbot_conversations')->insert([
            'uuid' => $uuid, 'user_id' => 41, 'visitor_hash' => null,
            'title' => 'Private user conversation', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(AIChatbotController::class);
        $method = new \ReflectionMethod($controller, 'owned');

        $ownerRequest = Request::create('/', 'GET');
        $ownerRequest->setUserResolver(fn () => (object) ['id' => 41]);
        $this->assertSame($uuid, $method->invoke($controller, $ownerRequest, $uuid)->uuid);

        $otherUserRequest = Request::create('/', 'GET');
        $otherUserRequest->setUserResolver(fn () => (object) ['id' => 42]);
        try {
            $method->invoke($controller, $otherUserRequest, $uuid);
            $this->fail('An authenticated user must not read another user conversation.');
        } catch (\ReflectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e->getPrevious() ?? $e);
        }
    }

    public function test_editing_provider_without_a_new_key_preserves_encrypted_credential(): void
    {
        $apiKey = 'keep-this-encrypted-key';
        $id = DB::table('ai_chatbot_providers')->insertGetId([
            'name' => 'Existing provider', 'driver' => 'openai',
            'api_key_encrypted' => Crypt::encryptString($apiKey), 'model' => 'old-model',
            'enabled' => true, 'priority' => 5, 'timeout_seconds' => 15, 'max_output_tokens' => 256,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = DB::table('ai_chatbot_providers')->where('id', $id)->value('api_key_encrypted');
        $request = Request::create('/', 'PUT', [
            'name' => 'Renamed provider', 'model' => 'new-model', 'priority' => 2,
            'timeout_seconds' => 25, 'max_output_tokens' => 900, 'enabled' => true,
        ]);
        $request->setUserResolver(fn () => (object) ['id' => 1]);

        app(AIChatbotAdminController::class)->updateProvider($request, $id, app(AuditLogger::class));

        $provider = DB::table('ai_chatbot_providers')->where('id', $id)->first();
        $this->assertSame('Renamed provider', $provider->name);
        $this->assertSame('new-model', $provider->model);
        $this->assertSame(2, (int) $provider->priority);
        $this->assertSame(25, (int) $provider->timeout_seconds);
        $this->assertSame(900, (int) $provider->max_output_tokens);
        $this->assertSame($before, $provider->api_key_encrypted);
        $this->assertSame($apiKey, Crypt::decryptString($provider->api_key_encrypted));
    }
}
