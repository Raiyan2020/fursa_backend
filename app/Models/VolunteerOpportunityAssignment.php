<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerOpportunityAssignment extends Model
{
    use HasSoftFlags;

    protected $fillable = ['registration_id', 'team_id', 'role_id', 'is_deleted', 'deleted_at'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunityRegistration::class, 'registration_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunityTeam::class, 'team_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunityRole::class, 'role_id');
    }
}
