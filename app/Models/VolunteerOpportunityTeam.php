<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerOpportunityTeam extends Model
{
    use HasSoftFlags;

    protected $fillable = ['opportunity_id', 'team_name_en', 'team_name_ar', 'is_deleted', 'deleted_at'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }
}
