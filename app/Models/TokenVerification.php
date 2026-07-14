<?php

namespace App\Models;

use App\Enums\VerificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TokenVerification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'verification_type',
        'token',
        'is_used',
        'created_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'created_at' => 'datetime',
        'verification_type' => VerificationType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (TokenVerification $model) {
            if (empty($model->token)) {
                $model->token = Str::random(64);
            }
            if (empty($model->created_at)) {
                $model->created_at = Carbon::now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        $minutes = (int) config('fursa.otp_or_link_expiry_time', 30);

        return Carbon::now()->greaterThan($this->created_at->copy()->addMinutes($minutes));
    }
}
