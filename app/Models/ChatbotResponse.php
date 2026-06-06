<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotResponse extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'intent_id',
        'response_text',
        'is_fallback',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_fallback' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(ChatbotIntent::class, 'intent_id');
    }
}
