<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    protected $fillable = [
        'household_id',
        'full_name',
        'age',
        'gender',
        'relationship',
        'is_pwd',
        'is_pregnant',
        'is_senior',
        'is_infant',
        'is_minor',
        'birthday',
    ];

    protected $casts = [
        'age'         => 'integer',
        'birthday'    => 'date',
        'is_pwd'      => 'boolean',
        'is_pregnant' => 'boolean',
        'is_senior'   => 'boolean',
        'is_infant'   => 'boolean',
        'is_minor'    => 'boolean',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
