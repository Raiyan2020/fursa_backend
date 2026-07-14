<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChoiceType extends Model
{
    use HasSoftFlags;

    protected $fillable = ['name', 'is_deleted', 'deleted_at'];

    public function choices(): HasMany
    {
        return $this->hasMany(MasterChoice::class);
    }
}
