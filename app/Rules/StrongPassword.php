<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The single source of truth for password strength on the server. Mirrors the
 * client-side checks (web Alpine `passwordField()` + Flutter `validators.dart`):
 * 8–20 chars, at least one uppercase letter, at least one digit, and no spaces.
 *
 * Reports every unmet requirement (not just the first) so the user sees the full
 * picture in one round-trip. Pair with `confirmed` where a confirmation field exists.
 */
class StrongPassword implements ValidationRule
{
    public const MIN = 8;

    public const MAX = 20;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (mb_strlen($value) < self::MIN || mb_strlen($value) > self::MAX) {
            $fail('The password must be between :min and :max characters.')
                ->translate(['min' => self::MIN, 'max' => self::MAX]);
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('The password must include at least one uppercase letter.');
        }

        if (! preg_match('/[0-9]/', $value)) {
            $fail('The password must include at least one number.');
        }

        if (preg_match('/\s/', $value)) {
            $fail('The password must not contain spaces.');
        }
    }
}
