<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotIntent extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'intent_key',
        'display_name',
        'category',
        'training_phrases',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'training_phrases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ChatbotResponse::class, 'intent_id');
    }
}
