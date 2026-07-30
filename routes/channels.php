<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('users.{publicId}', function (User $user, string $publicId) {
    return hash_equals($user->public_id, $publicId);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('conversations.{publicId}', function (User $user, string $publicId) {
    $conversation = Conversation::where('public_id', $publicId)->first();

    return $conversation !== null
        && Gate::forUser($user)->allows('participate', $conversation);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel(
    'admin.appointments',
    fn (User $user) => $user->role === 'admin',
    ['guards' => ['web', 'sanctum']]
);
