<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudAuditLog extends Model
{
    protected $fillable = [
        'action',
        'node_id',
        'actor_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(CloudNode::class, 'node_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
