<?php

namespace Database\Seeders;

use App\Models\ChatbotIntent;
use Illuminate\Database\Seeder;

class ChatbotSeeder extends Seeder
{
    public function run(): void
    {
        $intents = [
            [
                'intent_key' => 'clinic_hours',
                'display_name' => 'Clinic Hours',
                'category' => 'faq',
                'training_phrases' => ['what are your hours', 'clinic hours', 'opening hours', 'when are you open', 'office hours', 'working hours', 'what time do you open', 'what time do you close'],
                'responses' => [
                    ['response_text' => 'Our clinic is open Monday through Friday from 8:00 AM to 5:00 PM. We are closed on weekends and public holidays.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'clinic_location',
                'display_name' => 'Clinic Location',
                'category' => 'faq',
                'training_phrases' => ['where are you located', 'clinic location', 'address', 'how to get there', 'directions', 'where is the clinic', 'location'],
                'responses' => [
                    ['response_text' => 'Our clinic is located at 123 Therapy Lane, Wellness District. You can find us near the central park. Free parking is available on site.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'appointment_steps',
                'display_name' => 'How to Book an Appointment',
                'category' => 'faq',
                'training_phrases' => ['how do i book an appointment', 'book appointment', 'schedule a visit', 'make an appointment', 'how to schedule', 'appointment booking', 'how to book'],
                'responses' => [
                    ['response_text' => 'To book an appointment, go to the Schedule tab in the app, select an available time slot, choose in-person or online mode, and submit your request. Your clinician will review and approve it. You will receive a notification once confirmed.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'schedule_reminder',
                'display_name' => 'Appointment Reminders',
                'category' => 'faq',
                'training_phrases' => ['will i get a reminder', 'appointment reminder', 'remind me', 'notification for appointment', 'do you send reminders'],
                'responses' => [
                    ['response_text' => 'Yes! You will receive a push notification the day before your scheduled appointment as a reminder. You can also check your upcoming appointments in the Schedule tab at any time.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'assignment_followup',
                'display_name' => 'Assignment Help',
                'category' => 'faq',
                'training_phrases' => ['how do i submit an assignment', 'assignment help', 'submit homework', 'where are my assignments', 'complete assignment', 'assignment submission'],
                'responses' => [
                    ['response_text' => 'You can find your assignments in the Assignments tab. Tap on an assignment to view details, then use the Submit button to send your response. You can type a text answer or upload a file. Your clinician will review it and mark it as reviewed.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'directions_from_location',
                'display_name' => 'Directions From Current Location',
                'category' => 'faq',
                'training_phrases' => ['how do i get there from here', 'directions from my location', 'navigate to the clinic', 'route to the clinic', 'how can i travel to the clinic', 'directions to the clinic'],
                'responses' => [
                    ['response_text' => "I can't determine your current location or calculate a route. You can enter the clinic address in your preferred maps app for turn-by-turn directions. If you are unsure about transportation or accessibility, please contact the clinic directly.", 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'coping_stress',
                'display_name' => 'General Stress Support',
                'category' => 'faq',
                'training_phrases' => ['i feel stressed', 'help with stress', 'overwhelmed right now', 'too much pressure', 'how can i calm down', 'stressed lately'],
                'responses' => [
                    ['response_text' => 'If it feels manageable, try pausing for a few slow breaths, naming the next small task you can control, or taking a brief break to stretch or walk. These are general self-help ideas, not a substitute for care. Please consider telling your clinician what has been making you feel stressed.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'coping_anxiety',
                'display_name' => 'General Anxiety Support',
                'category' => 'faq',
                'training_phrases' => ['i feel anxious', 'help with anxiety', 'i am worried', 'feeling panicky', 'my thoughts are racing', 'how do i manage anxiety'],
                'responses' => [
                    ['response_text' => 'You could try slowing your breathing, placing both feet on the floor, and gently noticing what you can see and hear around you. If the feeling continues, worsens, or interferes with daily life, please contact your clinician and let them know what you are experiencing.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'grounding_help',
                'display_name' => 'Grounding Exercise',
                'category' => 'faq',
                'training_phrases' => ['grounding exercise', 'help me feel grounded', 'five four three two one', '5 4 3 2 1 technique', 'i feel disconnected', 'bring me back to the present'],
                'responses' => [
                    ['response_text' => 'Try the 5-4-3-2-1 grounding exercise: notice five things you can see, four you can feel, three you can hear, two you can smell, and one you can taste. Go slowly and stop if it feels uncomfortable. This is general support, so please discuss recurring or intense symptoms with your clinician.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'coping_low_mood',
                'display_name' => 'General Low Mood Support',
                'category' => 'faq',
                'training_phrases' => ['i feel down', 'feeling low', 'i feel sad', 'no motivation', 'hard to get started', 'what can i do when i feel low'],
                'responses' => [
                    ['response_text' => 'When it feels possible, choose one very small action such as drinking water, opening a window, taking a short walk, or messaging someone you trust. You do not have to solve everything at once. Please also tell your clinician about persistent or worsening low mood.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'coping_sleep',
                'display_name' => 'General Sleep Support',
                'category' => 'faq',
                'training_phrases' => ['i cannot sleep', 'trouble sleeping', 'help me sleep', 'sleep routine', 'awake at night', 'how can i sleep better'],
                'responses' => [
                    ['response_text' => 'A consistent wind-down time, dimmer lights, less stimulating screen use before bed, and writing down tomorrow’s tasks may help some people. Avoid changing medication based on Joy’s suggestions. If sleep problems persist, please discuss them with your clinician.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'contact_clinician',
                'display_name' => 'Contacting a Clinician',
                'category' => 'faq',
                'training_phrases' => ['should i tell my clinician', 'talk to my clinician', 'contact my therapist', 'i need professional help', 'can i message my clinician', 'reach my clinician'],
                'responses' => [
                    ['response_text' => 'Yes. You can use the Messages tab to contact a clinician assigned to you, or book an appointment from the Schedule tab. Messages are not an emergency service; if you may be in immediate danger, contact emergency support right away.', 'is_fallback' => false, 'priority' => 10],
                ],
            ],
            [
                'intent_key' => 'greeting',
                'display_name' => 'Greeting',
                'category' => 'smalltalk',
                'training_phrases' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'greetings', 'howdy', 'hi there'],
                'responses' => [
                    ['response_text' => "Hi, I'm Joy, your TheraConnect assistant! How can I help you today? You can ask me about appointments, assignments, or clinic information.", 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'thanks',
                'display_name' => 'Thanks',
                'category' => 'smalltalk',
                'training_phrases' => ['thank you', 'thanks', 'thank you so much', 'appreciate it', 'thx', 'ty'],
                'responses' => [
                    ['response_text' => 'You are welcome! Is there anything else I can help with?', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'goodbye',
                'display_name' => 'Goodbye',
                'category' => 'smalltalk',
                'training_phrases' => ['bye', 'goodbye', 'see you', 'see you later', 'talk later', 'bye bye'],
                'responses' => [
                    ['response_text' => 'Goodbye! Take care, and do not hesitate to reach out if you need help.', 'is_fallback' => false, 'priority' => 0],
                ],
            ],
            [
                'intent_key' => 'fallback',
                'display_name' => 'Fallback',
                'category' => 'fallback',
                'training_phrases' => [],
                'responses' => [
                    ['response_text' => "I'm sorry, I did not quite understand that. Please contact the clinic directly by phone for further assistance, or try rephrasing your question.", 'is_fallback' => true, 'priority' => 0],
                ],
            ],
        ];

        foreach ($intents as $intentData) {
            $responses = $intentData['responses'] ?? [];
            unset($intentData['responses']);

            $intent = ChatbotIntent::firstOrCreate(
                ['intent_key' => $intentData['intent_key']],
                $intentData
            );

            if ($intent->wasRecentlyCreated) {
                foreach ($responses as $responseData) {
                    $intent->responses()->create($responseData);
                }
            }
        }
    }
}
