<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $fillable = [
        'household_head',
        'barangay_id',
        'zone',
        'sex',
        'age',
        'gender',
        'house_type',
        'family_members',
        'pwd_count',
        'pregnant_count',
        'senior_count',
        'infant_count',
        'minor_count',
        'child_count',
        'adolescent_count',
        'young_adult_count',
        'adult_count',
        'middle_aged_count',
        'latitude',
        'longitude',
        'sitio_purok_zone',
        'ip_non_ip',
        'hh_id',
        'preparedness_kit',
        'educational_attainment',
        'birthday',
    ];

    protected $casts = [
        'age'              => 'integer',
        'birthday'         => 'date',
        'family_members'   => 'integer',
        'pwd_count'        => 'integer',
        'pregnant_count'   => 'integer',
        'senior_count'     => 'integer',
        'infant_count'     => 'integer',
        'minor_count'      => 'integer',
        'child_count'      => 'integer',
        'adolescent_count' => 'integer',
        'young_adult_count'=> 'integer',
        'adult_count'      => 'integer',
        'middle_aged_count'=> 'integer',
        'latitude'         => 'float',
        'longitude'        => 'float',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function hasValidGps(): bool
    {
        return $this->latitude !== null
            && $this->longitude !== null
            && $this->latitude >= 12.50 && $this->latitude <= 13.20
            && $this->longitude >= 120.50 && $this->longitude <= 121.20;
    }
}
