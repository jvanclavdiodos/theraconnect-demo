<?php

namespace Tests\Integration;

use App\Models\ChatbotIntent;
use Database\Seeders\ChatbotSeeder;
use Tests\TestCase;

class ChatbotResponseBankTest extends TestCase
{
    public function test_expanded_response_bank_is_available_after_migration(): void
    {
        foreach ([
            'directions_from_location',
            'coping_stress',
            'coping_anxiety',
            'grounding_help',
            'coping_low_mood',
            'coping_sleep',
            'contact_clinician',
        ] as $intentKey) {
            $this->assertDatabaseHas('chatbot_intents', [
                'intent_key' => $intentKey,
                'is_active' => true,
            ]);

            $intent = ChatbotIntent::where('intent_key', $intentKey)
                ->with('responses')
                ->firstOrFail();

            $this->assertNotEmpty($intent->training_phrases);
            $this->assertTrue($intent->responses->isNotEmpty());
        }
    }

    public function test_seeding_again_does_not_overwrite_admin_edited_content(): void
    {
        $intent = ChatbotIntent::where('intent_key', 'directions_from_location')
            ->with('responses')
            ->firstOrFail();
        $intent->update(['display_name' => 'Clinic-customized directions']);
        $intent->responses->first()->update([
            'response_text' => 'Clinic-customized navigation guidance.',
        ]);

        $this->seed(ChatbotSeeder::class);

        $intent->refresh()->load('responses');
        $this->assertSame('Clinic-customized directions', $intent->display_name);
        $this->assertSame(
            'Clinic-customized navigation guidance.',
            $intent->responses->first()->response_text
        );
    }
}
