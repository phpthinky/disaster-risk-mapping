<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayBoundaryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'barangay_id',
        'action',
        'drawn_by',
        'drawn_at',
    ];

    protected $casts = [
        'drawn_at' => 'datetime',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function drawnByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }
}
