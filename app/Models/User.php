<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\Nationality;
use App\Enums\SocialMediaProvider;
use App\Enums\UserType;
use App\Models\Concerns\HasSoftFlags;
use Carbon\Carbon;
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
    use HasFactory, Notifiable;
    use HasSoftFlags {
        softDeleteFlags as protected softDeleteFlagsOnly;
    }

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

        // Registration only ever asked for one of `dob` / `birth_year`, so an
        // account could end up with a full birth date and an empty birth_year —
        // which blocked opportunity sign-up and age-targeted notifications even
        // though the data was there. Derive it on every write instead.
        static::saving(function (User $user) {
            $user->syncBirthYearFromDob();
        });
    }

    /**
     * Fill `birth_year` from `dob` when it is missing. Never overwrites an
     * explicitly provided birth_year.
     */
    public function syncBirthYearFromDob(): void
    {
        if (! empty($this->birth_year)) {
            return;
        }

        if (empty($this->dob)) {
            return;
        }

        try {
            $this->birth_year = (int) Carbon::parse($this->dob)->year;
        } catch (\Throwable) {
            // A malformed dob must not block saving the rest of the profile.
        }
    }

    /**
     * The birth year to use for age checks: the stored value, else derived from
     * `dob`. Returns null only when the account really has neither.
     */
    public function effectiveBirthYear(): ?int
    {
        if (! empty($this->birth_year)) {
            return (int) $this->birth_year;
        }

        if (empty($this->dob)) {
            return null;
        }

        try {
            return (int) Carbon::parse($this->dob)->year;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    /**
     * Free the unique email slot so the address can be used for a fresh registration.
     */
    public function softDeleteFlags(): bool
    {
        if (! $this->is_deleted) {
            $this->email = 'deleted_'.$this->id.'_'.now()->timestamp.'_'.$this->email;
        }

        return $this->softDeleteFlagsOnly();
    }

    public function volunteerProfile(): HasOne
    {
        return $this->hasOne(VolunteerProfile::class);
    }

    public function organizationProfile(): HasOne
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    /**
     * Admin/UI account bucket: volunteer | organization | volunteer_team | admin
     */
    public function accountTypeKey(): string
    {
        if ($this->user_type === UserType::ORGANIZATION) {
            $this->loadMissing('organizationProfile.organizerType');
            if ($this->organizationProfile?->organizerType?->value_en === 'Volunteer Team') {
                return 'volunteer_team';
            }
        }

        return $this->user_type?->value ?? '';
    }

    public function accountTypeLabel(): string
    {
        return match ($this->accountTypeKey()) {
            'volunteer_team' => __('admin.user_types.volunteer_team'),
            'volunteer' => __('admin.user_types.volunteer'),
            'organization' => __('admin.user_types.organization'),
            'admin' => __('admin.user_types.admin'),
            default => $this->user_type?->label() ?? '-',
        };
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

    public function volunteerStatistics(): HasMany
    {
        return $this->hasMany(VolunteerStatistic::class);
    }

    public function organizationStatistics(): HasMany
    {
        return $this->hasMany(OrganizationStatistic::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
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
