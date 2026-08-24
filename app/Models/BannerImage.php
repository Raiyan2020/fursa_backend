<?php

namespace App\Models;

use App\Enums\BannerPlacement;
use App\Http\Traits\UploadTrait;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BannerImage extends Model
{
    use HasSoftFlags, UploadTrait;

    /** Used by UploadTrait when assigning an UploadedFile to `image`. */
    protected string $uploadFolder = 'banners';

    protected $fillable = [
        'image',
        'name',
        'placement',
        'banner_url',
        'start_date',
        'end_date',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'placement' => BannerPlacement::class,
    ];

    /**
     * Restrict to one page's banners. Unknown/empty placements fall back to
     * `home` so legacy rows keep showing where they always did.
     */
    public function scopeForPlacement(Builder $query, ?string $placement): Builder
    {
        $value = BannerPlacement::tryFrom((string) $placement)?->value
            ?? BannerPlacement::HOME->value;

        return $query->where('placement', $value);
    }

    /**
     * Banners with no dates are always visible; otherwise today must fall in [start_date, end_date].
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }
}
