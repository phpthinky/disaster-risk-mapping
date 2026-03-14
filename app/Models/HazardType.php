<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HazardType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'icon',
    ];

    public function hazardZones(): HasMany
    {
        return $this->hasMany(HazardZone::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }
}
