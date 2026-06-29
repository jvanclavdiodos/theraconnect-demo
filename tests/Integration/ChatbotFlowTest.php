<?php

namespace Tests\Integration;

use Database\Seeders\ChatbotSeeder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChatbotSeeder::class);

        // Force the rule-based path by default so the suite is deterministic and
        // never makes a live API call — regardless of any GEMINI_API_KEY in the
        // local .env. Tests that exercise the AI path opt in explicitly below.
        config(['services.gemini.key' => null]);
    }

    public function test_chatbot_returns_intent_reply(): void
    {
        $patient = $this->createPatient('chat@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => 'What are your clinic hours?',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['reply', 'intent_key', 'is_fallback'],
            ])
            ->assertJsonPath('data.intent_key', 'clinic_hours')
            ->assertJsonPath('data.is_fallback', false);
    }

    public function test_chatbot_fallback_on_unrecognized_input(): void
    {
        $patient = $this->createPatient('fallback@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => 'xyzzy_gibberish_nonsense_abc123',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_fallback', true);
    }

    public function test_chatbot_handles_greeting(): void
    {
        $patient = $this->createPatient('greet@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => 'Hello there!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent_key', 'greeting')
            ->assertJsonPath('data.is_fallback', false);
    }

    public function test_chatbot_empty_message_is_rejected(): void
    {
        $patient = $this->createPatient('empty@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => '',
            ])
            ->assertStatus(422);
    }

    public function test_unauthenticated_chatbot_returns_401(): void
    {
        $this->postJson('/api/v1/chatbot/message', [
            'message' => 'hello',
        ])->assertStatus(401);
    }

    public function test_chatbot_uses_ai_path_when_key_is_configured(): void
    {
        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => json_encode([
                            'reply' => 'We are open Monday to Friday, 8 AM to 5 PM.',
                            'category' => 'clinic_info',
                        ])]],
                    ],
                ]],
            ], 200),
        ]);

        $patient = $this->createPatient('ai@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => 'when are you open?',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.reply', 'We are open Monday to Friday, 8 AM to 5 PM.')
            ->assertJsonPath('data.intent_key', 'clinic_info')
            ->assertJsonPath('data.is_fallback', false);

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), 'gemini-2.5-flash'));
    }

    public function test_chatbot_falls_back_to_jaccard_when_ai_call_fails(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('upstream error', 500),
        ]);

        $patient = $this->createPatient('aifail@test.com');
        $token = $this->getApiToken($patient['user']);

        // API errors out, so the rule-based matcher should still answer.
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', [
                'message' => 'What are your clinic hours?',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.intent_key', 'clinic_hours')
            ->assertJsonPath('data.is_fallback', false);
    }

    // ── Portal (web session) chatbot ─────────────────────────────────────────

    public function test_portal_chatbot_page_renders_for_patient(): void
    {
        $patient = $this->createPatient('portal-chat@test.com');

        $this->actingAs($patient['user'])
            ->get('/portal/chatbot')
            ->assertStatus(200)
            ->assertSee('Joy')
            ->assertSee('alpine:init')
            ->assertSee('x-text="replyText(m)"', false)
            ->assertDontSee('flex-column-reverse', false);
    }

    public function test_portal_chatbot_message_returns_json_exchange(): void
    {
        $patient = $this->createPatient('portal-chat2@test.com');

        $response = $this->actingAs($patient['user'])
            ->postJson('/portal/chatbot', [
                'message' => 'What are your clinic hours?',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'question',
                'answer' => ['reply', 'intent_key', 'is_fallback'],
                'data' => ['reply', 'intent_key', 'is_fallback'],
            ])
            ->assertJsonPath('answer.intent_key', 'clinic_hours')
            ->assertJsonPath('data.intent_key', 'clinic_hours')
            ->assertJsonPath('answer.is_fallback', false);
    }

    public function test_portal_chatbot_rejects_unauthenticated(): void
    {
        $this->postJson('/portal/chatbot', ['message' => 'hello'])
            ->assertStatus(401);
    }

    public function test_portal_chatbot_empty_message_rejected(): void
    {
        $patient = $this->createPatient('portal-chat3@test.com');

        $this->actingAs($patient['user'])
            ->postJson('/portal/chatbot', ['message' => ''])
            ->assertStatus(422);
    }
}
