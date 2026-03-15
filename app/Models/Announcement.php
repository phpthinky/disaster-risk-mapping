<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'announcement_type',
        'target_audience',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope announcements visible to a given user based on their role.
     * Admins see everything; other roles see 'all' + their own role.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query; // admins see all
        }

        return $query->whereIn('target_audience', ['all', $user->role]);
    }

    /** Badge colour class for Bootstrap based on announcement type. */
    public static function typeBadgeClass(string $type): string
    {
        return match ($type) {
            'emergency'   => 'danger',
            'info'        => 'info',
            'maintenance' => 'warning',
            default       => 'secondary',
        };
    }

    /** Border-left colour (inline style) per announcement type. */
    public static function typeBorderColor(string $type): string
    {
        return match ($type) {
            'emergency'   => '#dc3545',
            'info'        => '#0dcaf0',
            'maintenance' => '#ffc107',
            default       => '#6c757d',
        };
    }

    /** Font Awesome icon class per announcement type. */
    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'emergency'   => 'fa-triangle-exclamation',
            'info'        => 'fa-circle-info',
            'maintenance' => 'fa-screwdriver-wrench',
            default       => 'fa-bullhorn',
        };
    }
}
