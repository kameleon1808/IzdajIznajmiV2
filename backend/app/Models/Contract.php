<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'transaction_id',
        'version',
        'template_key',
        'contract_hash',
        'pdf_path',
        'rendered_payload',
        'status',
    ];

    protected $casts = [
        'rendered_payload' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RentalTransaction::class, 'transaction_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }
}
