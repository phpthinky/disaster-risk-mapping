<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentReport extends Model
{
    protected $fillable = [
        'title',
        'hazard_type_id',
        'incident_date',
        'status',
        'affected_polygon',
        'description',
        'reported_by',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function affectedAreas(): HasMany
    {
        return $this->hasMany(AffectedArea::class, 'incident_id');
    }
}
