<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExpiringToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'user_id',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExpiringToken $token) {
            if (empty($token->key)) {
                $token->key = hash('sha256', Str::random(60));
            }
            if (empty($token->created_at)) {
                $token->created_at = Carbon::now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && Carbon::now()->greaterThan($this->expires_at);
    }

    public static function issueFor(User $user, int $days): self
    {
        static::query()->where('user_id', $user->id)->delete();

        return static::query()->create([
            'user_id' => $user->id,
            'expires_at' => Carbon::now()->addDays($days),
        ]);
    }
}
