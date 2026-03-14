<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectedArea extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'incident_id',
        'barangay_id',
        'affected_households',
        'affected_population',
        'affected_pwd',
        'affected_seniors',
        'affected_infants',
        'affected_minors',
        'affected_pregnant',
        'ip_count',
        'computed_at',
    ];

    protected $casts = [
        'affected_households' => 'integer',
        'affected_population' => 'integer',
        'affected_pwd'        => 'integer',
        'affected_seniors'    => 'integer',
        'affected_infants'    => 'integer',
        'affected_minors'     => 'integer',
        'affected_pregnant'   => 'integer',
        'ip_count'            => 'integer',
        'computed_at'         => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class, 'incident_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
