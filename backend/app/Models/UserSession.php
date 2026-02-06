<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'device_fingerprint',
        'device_label',
        'ip_truncated',
        'user_agent',
        'last_active_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
