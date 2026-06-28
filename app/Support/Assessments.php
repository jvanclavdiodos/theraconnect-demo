<?php

namespace App\Support;

/**
 * Static catalog of the standardized symptom scales the app supports.
 *
 * The items, response options and scoring of PHQ-9 and GAD-7 are fixed,
 * public-domain instruments — so they live in code rather than in question/
 * option tables. Each instance assigned to a patient is an `assessments` row;
 * this class supplies the prompts to render and the scoring/severity logic.
 *
 * PHQ-9 / GAD-7 share the same 0–3 frequency scale ("Over the last 2 weeks,
 * how often have you been bothered by…").
 */
class Assessments
{
    public const PHQ9 = 'phq9';

    public const GAD7 = 'gad7';

    /** Shared 0–3 answer options (index = stored value). */
    public const OPTIONS = [
        'Not at all',
        'Several days',
        'More than half the days',
        'Nearly every day',
    ];

    public const INSTRUMENTS = [
        self::PHQ9 => [
            'key' => self::PHQ9,
            'title' => 'PHQ-9',
            'name' => 'Patient Health Questionnaire (depression)',
            'prompt' => 'Over the last 2 weeks, how often have you been bothered by any of the following problems?',
            'max' => 27,
            'items' => [
                'Little interest or pleasure in doing things',
                'Feeling down, depressed, or hopeless',
                'Trouble falling or staying asleep, or sleeping too much',
                'Feeling tired or having little energy',
                'Poor appetite or overeating',
                'Feeling bad about yourself — or that you are a failure or have let yourself or your family down',
                'Trouble concentrating on things, such as reading the newspaper or watching television',
                'Moving or speaking so slowly that other people could have noticed; or being so fidgety or restless that you have been moving around a lot more than usual',
                'Thoughts that you would be better off dead, or of hurting yourself in some way',
            ],
        ],
        self::GAD7 => [
            'key' => self::GAD7,
            'title' => 'GAD-7',
            'name' => 'Generalized Anxiety Disorder scale',
            'prompt' => 'Over the last 2 weeks, how often have you been bothered by the following problems?',
            'max' => 21,
            'items' => [
                'Feeling nervous, anxious, or on edge',
                'Not being able to stop or control worrying',
                'Worrying too much about different things',
                'Trouble relaxing',
                'Being so restless that it is hard to sit still',
                'Becoming easily annoyed or irritable',
                'Feeling afraid, as if something awful might happen',
            ],
        ],
    ];

    public static function exists(string $instrument): bool
    {
        return isset(self::INSTRUMENTS[$instrument]);
    }

    public static function definition(string $instrument): ?array
    {
        return self::INSTRUMENTS[$instrument] ?? null;
    }

    public static function title(string $instrument): string
    {
        return self::INSTRUMENTS[$instrument]['title'] ?? $instrument;
    }

    public static function itemCount(string $instrument): int
    {
        return count(self::INSTRUMENTS[$instrument]['items'] ?? []);
    }

    /** Sum the per-item responses (already validated 0–3). */
    public static function score(array $responses): int
    {
        return array_sum(array_map('intval', $responses));
    }

    /** Clinical severity band for a total score. */
    public static function severity(string $instrument, int $score): string
    {
        if ($instrument === self::PHQ9) {
            return match (true) {
                $score >= 20 => 'Severe',
                $score >= 15 => 'Moderately severe',
                $score >= 10 => 'Moderate',
                $score >= 5 => 'Mild',
                default => 'Minimal',
            };
        }

        // GAD-7
        return match (true) {
            $score >= 15 => 'Severe',
            $score >= 10 => 'Moderate',
            $score >= 5 => 'Mild',
            default => 'Minimal',
        };
    }
}
