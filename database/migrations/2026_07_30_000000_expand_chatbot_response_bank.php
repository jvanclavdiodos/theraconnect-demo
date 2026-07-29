<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INTENTS = [
        [
            'intent_key' => 'directions_from_location',
            'display_name' => 'Directions From Current Location',
            'category' => 'faq',
            'training_phrases' => ['how do i get there from here', 'directions from my location', 'navigate to the clinic', 'route to the clinic', 'how can i travel to the clinic', 'directions to the clinic'],
            'response' => "I can't determine your current location or calculate a route. You can enter the clinic address in your preferred maps app for turn-by-turn directions. If you are unsure about transportation or accessibility, please contact the clinic directly.",
        ],
        [
            'intent_key' => 'coping_stress',
            'display_name' => 'General Stress Support',
            'category' => 'faq',
            'training_phrases' => ['i feel stressed', 'help with stress', 'overwhelmed right now', 'too much pressure', 'how can i calm down', 'stressed lately'],
            'response' => 'If it feels manageable, try pausing for a few slow breaths, naming the next small task you can control, or taking a brief break to stretch or walk. These are general self-help ideas, not a substitute for care. Please consider telling your clinician what has been making you feel stressed.',
        ],
        [
            'intent_key' => 'coping_anxiety',
            'display_name' => 'General Anxiety Support',
            'category' => 'faq',
            'training_phrases' => ['i feel anxious', 'help with anxiety', 'i am worried', 'feeling panicky', 'my thoughts are racing', 'how do i manage anxiety'],
            'response' => 'You could try slowing your breathing, placing both feet on the floor, and gently noticing what you can see and hear around you. If the feeling continues, worsens, or interferes with daily life, please contact your clinician and let them know what you are experiencing.',
        ],
        [
            'intent_key' => 'grounding_help',
            'display_name' => 'Grounding Exercise',
            'category' => 'faq',
            'training_phrases' => ['grounding exercise', 'help me feel grounded', 'five four three two one', '5 4 3 2 1 technique', 'i feel disconnected', 'bring me back to the present'],
            'response' => 'Try the 5-4-3-2-1 grounding exercise: notice five things you can see, four you can feel, three you can hear, two you can smell, and one you can taste. Go slowly and stop if it feels uncomfortable. This is general support, so please discuss recurring or intense symptoms with your clinician.',
        ],
        [
            'intent_key' => 'coping_low_mood',
            'display_name' => 'General Low Mood Support',
            'category' => 'faq',
            'training_phrases' => ['i feel down', 'feeling low', 'i feel sad', 'no motivation', 'hard to get started', 'what can i do when i feel low'],
            'response' => 'When it feels possible, choose one very small action such as drinking water, opening a window, taking a short walk, or messaging someone you trust. You do not have to solve everything at once. Please also tell your clinician about persistent or worsening low mood.',
        ],
        [
            'intent_key' => 'coping_sleep',
            'display_name' => 'General Sleep Support',
            'category' => 'faq',
            'training_phrases' => ['i cannot sleep', 'trouble sleeping', 'help me sleep', 'sleep routine', 'awake at night', 'how can i sleep better'],
            'response' => 'A consistent wind-down time, dimmer lights, less stimulating screen use before bed, and writing down tomorrow’s tasks may help some people. Avoid changing medication based on Joy’s suggestions. If sleep problems persist, please discuss them with your clinician.',
        ],
        [
            'intent_key' => 'contact_clinician',
            'display_name' => 'Contacting a Clinician',
            'category' => 'faq',
            'training_phrases' => ['should i tell my clinician', 'talk to my clinician', 'contact my therapist', 'i need professional help', 'can i message my clinician', 'reach my clinician'],
            'response' => 'Yes. You can use the Messages tab to contact a clinician assigned to you, or book an appointment from the Schedule tab. Messages are not an emergency service; if you may be in immediate danger, contact emergency support right away.',
        ],
    ];

    public function up(): void
    {
        foreach (self::INTENTS as $definition) {
            if (DB::table('chatbot_intents')->where('intent_key', $definition['intent_key'])->exists()) {
                continue;
            }

            $intentId = DB::table('chatbot_intents')->insertGetId([
                'intent_key' => $definition['intent_key'],
                'display_name' => $definition['display_name'],
                'category' => $definition['category'],
                'training_phrases' => json_encode($definition['training_phrases'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('chatbot_responses')->insert([
                'intent_id' => $intentId,
                'response_text' => $definition['response'],
                'is_fallback' => false,
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('chatbot_intents')
            ->whereIn('intent_key', array_column(self::INTENTS, 'intent_key'))
            ->delete();
    }
};
