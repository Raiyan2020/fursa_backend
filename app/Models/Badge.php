<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'name',
        'description',
        'min_hours',
        'max_hours',
        'priority',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'min_hours' => 'float',
        'max_hours' => 'float',
    ];

    public function volunteers(): HasMany
    {
        return $this->hasMany(VolunteerProfile::class, 'current_badge_id');
    }
}
