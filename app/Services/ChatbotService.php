<?php

namespace App\Services;

use App\Models\ChatbotIntent;
use Illuminate\Support\Str;

class ChatbotService
{
    public const DEFAULT_FALLBACK = "I'm sorry, I did not quite understand that. Please contact the clinic directly for assistance.";

    private const THRESHOLD = 0.34;

    public function resolve(string $message): array
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
