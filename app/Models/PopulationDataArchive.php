<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopulationDataArchive extends Model
{
    public $timestamps = false;

    protected $table = 'population_data_archive';

    protected $fillable = [
        'original_id',
        'barangay_id',
        'total_population',
        'households',
        'elderly_count',
        'children_count',
        'pwd_count',
        'ips_count',
        'solo_parent_count',
        'widow_count',
        'data_date',
        'archived_at',
        'archived_by',
        'change_type',
    ];

    protected $casts = [
        'total_population'  => 'integer',
        'households'        => 'integer',
        'elderly_count'     => 'integer',
        'children_count'    => 'integer',
        'pwd_count'         => 'integer',
        'ips_count'         => 'integer',
        'solo_parent_count' => 'integer',
        'widow_count'       => 'integer',
        'data_date'         => 'date',
        'archived_at'       => 'datetime',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
