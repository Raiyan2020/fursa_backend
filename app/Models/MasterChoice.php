<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MasterChoice extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'choice_type_id',
        'value_en',
        'value_ar',
        'is_deleted',
        'deleted_at',
    ];

    public function choiceType(): BelongsTo
    {
        return $this->belongsTo(ChoiceType::class);
    }

    public function relatedTags(): BelongsToMany
    {
        return $this->belongsToMany(
            MasterChoice::class,
            'master_choice_related_tags',
            'master_choice_id',
            'related_master_choice_id'
        );
    }
}
