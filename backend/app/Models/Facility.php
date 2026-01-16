<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Facility extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class);
    }
}
