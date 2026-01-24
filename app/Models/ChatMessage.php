<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'body',
        'audience_type',
        'audience_user_ids',
        'deleted_for_all_at',
        'deleted_for_all_by_user_id',
    ];

    protected $casts = [
        'deleted_for_all_at' => 'datetime',
        'audience_user_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'chat_message_id');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(ChatMessageDeletion::class, 'message_id');
    }
}
