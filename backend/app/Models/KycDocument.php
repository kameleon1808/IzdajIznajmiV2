<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    public const TYPE_ID_FRONT = 'id_front';

    public const TYPE_ID_BACK = 'id_back';

    public const TYPE_SELFIE = 'selfie';

    public const TYPE_PROOF = 'proof_of_address';

    protected $fillable = [
        'submission_id',
        'user_id',
        'doc_type',
        'original_name',
        'mime_type',
        'size_bytes',
        'disk',
        'path',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KycSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
