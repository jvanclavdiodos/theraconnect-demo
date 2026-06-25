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

            $intent = ChatbotIntent::create($intentData);

            foreach ($responses as $responseData) {
                $intent->responses()->create($responseData);
            }
        }
    }
}
