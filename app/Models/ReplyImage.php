<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplyImage extends Model
{
    use HasSoftFlags;

    protected $fillable = ['reply_id', 'image', 'is_deleted', 'deleted_at'];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(Reply::class);
    }
}
