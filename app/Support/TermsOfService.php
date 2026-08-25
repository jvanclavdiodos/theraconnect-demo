<?php

namespace App\Support;

use App\Models\LegalDocument;
use Illuminate\Support\Str;

final class TermsOfService
{
    public const CURRENT_VERSION = '2026-07-17';

    public const DOCUMENT_KEY = 'user-agreement';

    private function __construct() {}

    public static function document(): ?LegalDocument
    {
        return LegalDocument::query()->where('key', self::DOCUMENT_KEY)->first();
    }

    public static function content(): string
    {
        return self::document()?->content ?? config('legal.terms');
    }

    public static function currentVersion(): string
    {
        return self::document()?->version ?? self::CURRENT_VERSION;
    }

    public static function renderHtml(?string $content = null): string
    {
        return Str::markdown(self::replacePlaceholders($content ?? self::content()), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public static function plainText(): string
    {
        $markdown = preg_replace('/^#{1,6}\s+/m', '', self::replacePlaceholders(self::content()));

        return trim(str_replace(['**', '__'], '', $markdown));
    }

    private static function replacePlaceholders(string $content): string
    {
        $privacyEmail = config('app.privacy_contact_email');

        return strtr($content, [
            '{{privacy_controller}}' => config('app.privacy_controller_name', config('app.name', 'TheraConnect')),
            '{{privacy_contact}}' => $privacyEmail ? "You may email {$privacyEmail}." : '',
        ]);
    }
}
