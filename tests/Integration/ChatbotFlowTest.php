<?php

namespace Tests\Integration;

use Tests\TestCase;

class ChatbotFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChatbotSeeder::class);
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
}
