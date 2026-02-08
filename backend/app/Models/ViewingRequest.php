<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewingRequest extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    public const CANCELLED_BY_SEEKER = 'seeker';

    public const CANCELLED_BY_LANDLORD = 'landlord';

    public const CANCELLED_BY_SYSTEM = 'system';

    protected $fillable = [
        'listing_id',
        'viewing_slot_id',
        'seeker_id',
        'landlord_id',
        'status',
        'message',
        'scheduled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ViewingSlot::class, 'viewing_slot_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
