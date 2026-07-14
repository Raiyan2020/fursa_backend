<?php

namespace App\Models;

use App\Enums\InterestType;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Interest extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'name_en',
        'name_ar',
        'interest_type',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'interest_type' => InterestType::class,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
