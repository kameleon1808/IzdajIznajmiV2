<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Conversation extends Model
{
    protected $fillable = [
        'tenant_id',
        'landlord_id',
        'listing_id',
        'tenant_last_read_at',
        'landlord_last_read_at',
    ];

    protected $casts = [
        'tenant_last_read_at' => 'datetime',
        'landlord_last_read_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isParticipant(User $user): bool
    {
        return in_array($user->id, [$this->tenant_id, $this->landlord_id], true);
    }

    public function markReadFor(User $user): void
    {
        $column = $user->id === $this->tenant_id ? 'tenant_last_read_at' : 'landlord_last_read_at';
        $originalTimestamps = $this->timestamps;
        $this->timestamps = false;
        $this->forceFill([$column => Carbon::now()])->saveQuietly();
        $this->timestamps = $originalTimestamps;
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = $user->id === $this->tenant_id ? $this->tenant_last_read_at : $this->landlord_last_read_at;

        $query = $this->messages()->where('sender_id', '!=', $user->id);

        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }
}
