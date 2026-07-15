<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\Nationality;
use App\Enums\SocialMediaProvider;
use App\Enums\UserType;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, HasSoftFlags, Notifiable;

    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'password',
        'is_staff',
        'is_active',
        'is_superuser',
        'last_login',
        'date_joined',
        'dob',
        'phone_number',
        'country_code',
        'is_social_login',
        'social_media_id',
        'social_media_provider',
        'social_profile_pic_url',
        'manual_id',
        'profile_pic',
        'instagram_link',
        'whatsapp_link',
        'linkedin_link',
        'facebook_link',
        'twitter_link',
        'user_type',
        'preferred_language',
        'password_length',
        'nationality',
        'birth_year',
        'civil_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_country_code',
        'emergency_contact_civil_id',
        'emergency_contact_relationship_id',
        'is_banned',
        'banned_time',
        'manually_banned',
        'badge_id',
        'is_deleted',
        'deleted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date',
        'last_login' => 'datetime',
        'date_joined' => 'datetime',
        'banned_time' => 'datetime',
        'is_staff' => 'boolean',
        'is_active' => 'boolean',
        'is_superuser' => 'boolean',
        'is_social_login' => 'boolean',
        'is_banned' => 'boolean',
        'manually_banned' => 'boolean',
        'user_type' => UserType::class,
        'social_media_provider' => SocialMediaProvider::class,
        'preferred_language' => Language::class,
        'password' => 'hashed',
    ];

    public function setNationalityAttribute(mixed $value): void
    {
        $this->attributes['nationality'] = Nationality::normalize($value);
    }

    public function getNationalityAttribute(mixed $value): ?Nationality
    {
        return Nationality::tryFromInput($value);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->manual_id)) {
                $user->manual_id = Str::random(22);
            }
            if (empty($user->date_joined)) {
                $user->date_joined = now();
            }
            if (empty($user->username)) {
                $user->username = Str::before($user->email, '@').'_'.Str::random(4);
            }
        });
    }

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public function volunteerProfile(): HasOne
    {
        return $this->hasOne(VolunteerProfile::class);
    }

    public function organizationProfile(): HasOne
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    public function expiringTokens(): HasMany
    {
        return $this->hasMany(ExpiringToken::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function emergencyContactRelationship(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'emergency_contact_relationship_id');
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    public function masterInterests(): BelongsToMany
    {
        return $this->belongsToMany(MasterChoice::class, 'master_choice_user');
    }

    public function isVolunteer(): bool
    {
        return $this->user_type === UserType::VOLUNTEER;
    }

    public function isOrganization(): bool
    {
        return $this->user_type === UserType::ORGANIZATION;
    }

    public function isAdmin(): bool
    {
        return $this->user_type === UserType::ADMIN || $this->is_superuser;
    }
}
