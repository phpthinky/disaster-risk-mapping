<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = [
        'name',
        'population',
        'area_km2',
        'calculated_area_km2',
        'coordinates',
        'boundary_geojson',
        'household_count',
        'pwd_count',
        'senior_count',
        'children_count',
        'infant_count',
        'pregnant_count',
        'ip_count',
        'at_risk_count',
    ];

    protected $casts = [
        'population'           => 'integer',
        'household_count'      => 'integer',
        'pwd_count'            => 'integer',
        'senior_count'         => 'integer',
        'children_count'       => 'integer',
        'infant_count'         => 'integer',
        'pregnant_count'       => 'integer',
        'ip_count'             => 'integer',
        'at_risk_count'        => 'integer',
        'area_km2'             => 'float',
        'calculated_area_km2'  => 'float',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function hazardZones(): HasMany
    {
        return $this->hasMany(HazardZone::class);
    }

    public function populationData(): HasMany
    {
        return $this->hasMany(PopulationData::class);
    }

    public function evacuationCenters(): HasMany
    {
        return $this->hasMany(EvacuationCenter::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function affectedAreas(): HasMany
    {
        return $this->hasMany(AffectedArea::class);
    }

    public function boundaryLogs(): HasMany
    {
        return $this->hasMany(BarangayBoundaryLog::class);
    }
}
