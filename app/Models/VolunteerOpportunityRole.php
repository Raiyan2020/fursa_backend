<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerOpportunityRole extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'role_name_en', 'role_name_ar',
        'instructions_en', 'instructions_ar', 'participants_needed',
        'is_deleted', 'deleted_at',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }
}
