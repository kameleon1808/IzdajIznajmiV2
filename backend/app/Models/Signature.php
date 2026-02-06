<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    public const METHOD_TYPED_NAME = 'typed_name';
    public const METHOD_CHECKBOX = 'checkbox_consent';

    protected $fillable = [
        'contract_id',
        'user_id',
        'role',
        'signed_at',
        'ip',
        'user_agent',
        'signature_method',
        'signature_data',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'signature_data' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
