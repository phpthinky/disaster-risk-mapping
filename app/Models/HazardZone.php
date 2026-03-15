<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazardZone extends Model
{
    public const RISK_LEVELS = [
        'High Susceptible',
        'Moderate Susceptible',
        'Low Susceptible',
        'Not Susceptible',
        'Prone',
        'Generally Susceptible',
        'PEIS VIII - Very destructive to devastating ground shaking',
        'PEIS VII - Destructive ground shaking',
        'General Inundation',
    ];

    /** Bootstrap badge colour class per risk level */
    public static function riskBadgeClass(string $level): string
    {
        return match (true) {
            str_contains($level, 'High') || $level === 'Prone' || str_contains($level, 'PEIS VIII') => 'danger',
            str_contains($level, 'Moderate') || str_contains($level, 'PEIS VII')                    => 'warning',
            str_contains($level, 'Low') || str_contains($level, 'Generally') || str_contains($level, 'Inundation') => 'info',
            str_contains($level, 'Not Susceptible')                                                  => 'success',
            default                                                                                   => 'secondary',
        };
    }

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
