<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvacuationCenter extends Model
{
    protected $fillable = [
        'name',
        'barangay_id',
        'capacity',
        'current_occupancy',
        'latitude',
        'longitude',
        'facilities',
        'contact_person',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'capacity'          => 'integer',
        'current_occupancy' => 'integer',
        'latitude'          => 'float',
        'longitude'         => 'float',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function getAvailabilityAttribute(): int
    {
        return max(0, $this->capacity - $this->current_occupancy);
    }
}
