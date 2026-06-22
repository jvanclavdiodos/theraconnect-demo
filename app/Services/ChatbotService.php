<?php

namespace App\Services;

use App\Models\ChatbotIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotService
{
    public const DEFAULT_FALLBACK = "I'm sorry, I did not quite understand that. Please contact the clinic directly for assistance.";

    private const THRESHOLD = 0.34;

    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * Resolve a patient message to a reply. When a Gemini API key is
     * configured, an LLM (Gemini Flash) answers, grounded on the chatbot
     * knowledge base. If the key is absent or the call fails for any reason,
     * we fall back to the original Jaccard intent matcher — so the chatbot
     * keeps working with zero external dependencies.
     */
    public function resolve(string $message): array
    {
        if (config('services.gemini.key')) {
            try {
                return $this->aiResolve($message);
            } catch (\Throwable $e) {
                Log::warning('Chatbot AI path failed, using Jaccard fallback', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->jaccardResolve($message);
    }

    private function aiResolve(string $message): array
    {
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $url = self::GEMINI_BASE_URL."/{$model}:generateContent";

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
            'content-type' => 'application/json',
        ])
            ->timeout(20)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $this->buildSystemPrompt()]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $message]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'reply' => ['type' => 'string'],
                            'category' => [
                                'type' => 'string',
                                'enum' => [
                                    'clinic_info', 'appointments', 'assignments',
                                    'mental_health', 'crisis', 'smalltalk', 'fallback',
                                ],
                            ],
                        ],
                        'required' => ['reply', 'category'],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $parsed = $text ? json_decode($text, true) : null;

        if (! is_array($parsed) || empty($parsed['reply'])) {
            throw new \RuntimeException('Unexpected chatbot API response shape');
        }

        $category = $parsed['category'] ?? 'fallback';

        return [
            'reply' => $parsed['reply'],
            'intent_key' => $category,
            'is_fallback' => $category === 'fallback',
        ];
    }

    /**
     * Build the system prompt: clinic facts come ONLY from the seeded
     * knowledge base (never invented), plus the guardrails and PH crisis
     * resources. No PHI is included — only generic clinic FAQ content.
     */
    private function buildSystemPrompt(): string
    {
        $kb = ChatbotIntent::where('is_active', true)
            ->where('category', '!=', 'fallback')
            ->with(['responses' => fn ($q) => $q->orderByDesc('priority')])
            ->get()
            ->map(function ($intent) {
                $answer = optional($intent->responses->first())->response_text;

                return $answer ? "- {$intent->display_name}: {$answer}" : null;
            })
            ->filter()
            ->implode("\n");

        return <<<PROMPT
            You are the TheraConnect clinic assistant — a supportive chatbot inside a mental-health clinic's patient app. You speak warmly, concisely, and in plain language (1–3 short paragraphs max).

            CLINIC KNOWLEDGE BASE — the ONLY source of facts about this clinic. Never invent hours, locations, prices, or procedures not listed here:
            {$kb}

            WHAT YOU DO:
            - Answer questions about clinic hours/location, appointments, reminders, assignments, and app usage using ONLY the knowledge base above. Use category "clinic_info", "appointments", or "assignments".
            - Respond supportively to mental-health concerns (stress, anxiety, low mood, sleep, etc.): validate the patient's feelings, offer general, NON-clinical coping suggestions (e.g. paced breathing, grounding exercises, journaling, a short walk), and encourage them to share the concern with their clinician — they can book a session or raise it through their assignments. Use category "mental_health".
            - Greetings, thanks, and goodbyes: respond briefly and kindly. Use category "smalltalk".

            WHAT YOU NEVER DO:
            - Never diagnose, prescribe medication, or give clinical treatment advice.
            - Never claim to be a licensed therapist, doctor, or the patient's clinician. You are an assistant that helps them use the clinic.
            - Never invent clinic facts that are not in the knowledge base. If you don't know, tell them to contact the clinic directly.
            - For anything unrelated to this clinic, the app, or the patient's wellbeing (e.g. coding help, general trivia, homework unrelated to therapy), give a brief, kind redirect to what you CAN help with. Use category "fallback".

            CRISIS HANDLING — HIGHEST PRIORITY:
            If the patient expresses thoughts of suicide, self-harm, wanting to die, or being in immediate danger, set category to "crisis" and respond calmly, warmly, and without judgment. Tell them they are not alone and urge them to reach out RIGHT NOW. Include these resources verbatim:
            • Emergency: call 911.
            • National Center for Mental Health (NCMH) Crisis Hotline: 1553 (toll-free landline), 0917-899-8727, 0919-057-1553, or 1800-1888-1553.
            • Hopeline Philippines (24/7): 0917-558-4673.
            Also urge them to contact the clinic and their clinician immediately. Do NOT attempt therapy or problem-solving in this moment — your only goal is connecting them to help.
            PROMPT;
    }

    private function jaccardResolve(string $message): array
    {
        $inputTokens = $this->tokenize($this->normalize($message));

        if (empty($inputTokens)) {
            return $this->fallbackResponse();
        }

        $intents = ChatbotIntent::where('is_active', true)
            ->where('category', '!=', 'fallback')
            ->with('responses')
            ->get();

        $bestScore = 0;
        $bestIntent = null;

        foreach ($intents as $intent) {
            $phrases = $intent->training_phrases;

            if (empty($phrases)) {
                continue;
            }

            foreach ($phrases as $phrase) {
                $phraseTokens = $this->tokenize($this->normalize($phrase));

                if (empty($phraseTokens)) {
                    continue;
                }

                $score = $this->jaccardSimilarity($inputTokens, $phraseTokens);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIntent = $intent;
                }
            }
        }

        if ($bestIntent && $bestScore >= self::THRESHOLD) {
            $response = $bestIntent->responses->sortByDesc('priority')->first();

            return [
                'reply' => $response->response_text,
                'intent_key' => $bestIntent->intent_key,
                'is_fallback' => false,
            ];
        }

        return $this->fallbackResponse();
    }

    private function fallbackResponse(): array
    {
        $fallback = ChatbotIntent::where('category', 'fallback')
            ->where('is_active', true)
            ->with(['responses' => function ($q) {
                $q->where('is_fallback', true)->orderByDesc('priority');
            }])
            ->first();

        $reply = $fallback && $fallback->responses->isNotEmpty()
            ? $fallback->responses->first()->response_text
            : self::DEFAULT_FALLBACK;

        return [
            'reply' => $reply,
            'intent_key' => 'fallback',
            'is_fallback' => true,
        ];
    }

    private function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function tokenize(string $text): array
    {
        $tokens = explode(' ', $text);

        return array_values(array_filter($tokens, fn ($t) => strlen($t) > 0));
    }

    private function jaccardSimilarity(array $a, array $b): float
    {
        $intersection = array_unique(array_intersect($a, $b));
        $union = array_unique(array_merge($a, $b));

        if (empty($union)) {
            return 0;
        }

        return count($intersection) / count($union);
    }
}
