<?php

namespace Tests\Integration;

use App\Models\ChatbotIntent;
use Tests\TestCase;

class WebChatbotContentTest extends TestCase
{
    private function makeIntent(): ChatbotIntent
    {
        $intent = ChatbotIntent::create([
            'intent_key' => 'clinic_hours',
            'display_name' => 'Clinic Hours',
            'category' => 'faq',
            'training_phrases' => ['what are your hours', 'when are you open'],
            'is_active' => true,
        ]);

        $intent->responses()->create([
            'response_text' => 'We are open Monday to Friday, 8 AM to 5 PM.',
            'is_fallback' => false,
            'priority' => 10,
        ]);

        return $intent;
    }

    /**
     * The edit page must load the bound intent. The resource route parameter is
     * named `intent` to match the controller's $intent argument; if that drifts,
     * implicit binding silently injects an empty model and route() for the
     * update form throws UrlGenerationException (missing parameter). This guards
     * that regression.
     */
    public function test_edit_page_loads_the_bound_intent(): void
    {
        $admin = $this->createAdmin();
        $intent = $this->makeIntent();

        $this->actingAs($admin, 'web')
            ->get("/chatbot-content/{$intent->id}/edit")
            ->assertStatus(200)
            ->assertSee('Clinic Hours')
            // Form action must carry the real id — proves binding resolved it.
            ->assertSee("chatbot-content/{$intent->id}", false);
    }

    public function test_create_page_renders(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->get('/chatbot-content/create')
            ->assertStatus(200);
    }

    public function test_store_creates_intent(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->post('/chatbot-content', [
                'intent_key' => 'new_intent',
                'display_name' => 'New Intent',
                'category' => 'faq',
                'training_phrases' => ['hello there'],
                'responses' => [
                    ['response_text' => 'Hi!', 'priority' => 0],
                ],
            ])
            ->assertRedirect(route('chatbot-content.index'));

        $this->assertDatabaseHas('chatbot_intents', ['intent_key' => 'new_intent']);
    }

    public function test_update_persists_changes(): void
    {
        $admin = $this->createAdmin();
        $intent = $this->makeIntent();

        $this->actingAs($admin, 'web')
            ->put("/chatbot-content/{$intent->id}", [
                'intent_key' => 'clinic_hours',
                'display_name' => 'Updated Hours',
                'category' => 'faq',
                'training_phrases' => ['what are your hours'],
                'is_active' => true,
                'responses' => [
                    ['response_text' => 'We are open 9 to 6.', 'priority' => 5],
                ],
            ])
            ->assertRedirect(route('chatbot-content.index'));

        $this->assertDatabaseHas('chatbot_intents', [
            'id' => $intent->id,
            'display_name' => 'Updated Hours',
        ]);
        $this->assertDatabaseHas('chatbot_responses', [
            'intent_id' => $intent->id,
            'response_text' => 'We are open 9 to 6.',
        ]);
    }
}
