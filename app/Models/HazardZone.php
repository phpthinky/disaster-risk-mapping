<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazardZone extends Model
{
    protected $fillable = [
        'hazard_type_id',
        'barangay_id',
        'risk_level',
        'area_km2',
        'affected_population',
        'coordinates',
        'description',
    ];

    protected $casts = [
        'area_km2'            => 'float',
        'affected_population' => 'integer',
    ];

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
