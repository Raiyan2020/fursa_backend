<?php

namespace App\Models;

use App\Enums\VerificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OtpVerification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'verification_type',
        'otp',
        'is_used',
        'attempts',
        'created_at',
    ];

    /**
     * Failed guesses allowed against one code before it is burned.
     */
    public const MAX_ATTEMPTS = 5;

    protected $casts = [
        'is_used' => 'boolean',
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'verification_type' => VerificationType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (OtpVerification $model) {
            if (empty($model->otp)) {
                $model->otp = (string) random_int(100000, 999999);
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

    /**
     * Count a failed guess, burning the code once the ceiling is reached.
     */
    public function registerFailedAttempt(): void
    {
        $this->attempts = (int) $this->attempts + 1;

        if ($this->attempts >= self::MAX_ATTEMPTS) {
            $this->is_used = true;
        }

        $this->save();
    }
}
