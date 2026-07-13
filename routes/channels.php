<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('users.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('conversations.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation !== null
        && Gate::forUser($user)->allows('participate', $conversation);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel(
    'admin.appointments',
    fn (User $user) => $user->role === 'admin',
    ['guards' => ['web', 'sanctum']]
);
