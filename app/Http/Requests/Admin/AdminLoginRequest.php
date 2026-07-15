<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Support\PasswordCompat;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('admin.attributes.email'),
            'password' => __('admin.attributes.password'),
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = (string) $this->input('email');
        $password = (string) $this->input('password');

        /** @var Admin|null $admin */
        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin || ! PasswordCompat::check($password, $admin->getAuthPassword())) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('admin.messages.credentials'),
            ]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => __('admin.messages.inactive'),
            ]);
        }

        PasswordCompat::upgradeIfNeeded($admin, $password);

        Auth::guard('admin')->login($admin, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('admin.messages.throttle', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
