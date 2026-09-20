<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BE-69 — «إذن تحضير»: one permission granting a volunteer both abilities
 * (add volunteers to the opportunity, record their attendance hours), scoped
 * to a single volunteer opportunity. Not QR scanning, and not built on
 * `ScanPermission`, which BE-61 Part C is retiring.
 */
class AttendancePermission extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id', 'opportunity_id', 'is_allowed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_allowed' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public static function grants(int $opportunityId, int $userId): bool
    {
        return static::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunityId)
            ->where('user_id', $userId)
            ->where('is_allowed', true)
            ->exists();
    }
}
