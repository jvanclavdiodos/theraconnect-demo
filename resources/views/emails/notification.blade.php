@php
    $appName = config('app.name', 'TheraConnect');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $notification->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; padding: 24px;">
        <p style="margin: 0 0 16px; color: #6b7280;">{{ $appName }}</p>

        <h1 style="font-size: 22px; line-height: 1.3; margin: 0 0 16px;">{{ $notification->title }}</h1>

        <p style="margin: 0 0 24px;">{{ $notification->body }}</p>

        <p style="margin: 0 0 24px;">
            <a href="{{ $ctaUrl }}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px;">
                Open TheraConnect
            </a>
        </p>

        <p style="font-size: 13px; color: #6b7280; margin: 0;">
            This is a transactional notification about your TheraConnect account.
        </p>
    </div>
</body>
</html>
