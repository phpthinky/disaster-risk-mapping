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
        'birthday',
        'gender',
        'relationship',
        'is_pwd',
        'is_pregnant',
        'is_senior',
        'is_infant',
        'is_minor',
        'is_ip',
    ];

    protected $appends = ['name', 'sex', 'relation'];

    protected $casts = [
        'age'         => 'integer',
        'birthday'    => 'date',
        'is_pwd'      => 'boolean',
        'is_pregnant' => 'boolean',
        'is_senior'   => 'boolean',
        'is_infant'   => 'boolean',
        'is_minor'    => 'boolean',
        'is_ip'       => 'boolean',
    ];

    // Aliases so views/JSON can use 'name', 'sex', 'relation'
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getSexAttribute(): string
    {
        return $this->gender;
    }

    public function getRelationAttribute(): ?string
    {
        return $this->relationship;
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
