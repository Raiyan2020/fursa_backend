<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies Laravel bcrypt/argon hashes and legacy Django pbkdf2_sha256 hashes.
 * On successful Django verification, upgrades the stored hash to bcrypt.
 */
class PasswordCompat
{
    public static function check(string $plain, ?string $hashed): bool
    {
        if ($hashed === null || $hashed === '') {
            return false;
        }

        if (self::isDjangoHash($hashed)) {
            return self::checkDjango($plain, $hashed);
        }

        return Hash::check($plain, $hashed);
    }

    public static function isDjangoHash(string $hashed): bool
    {
        return str_starts_with($hashed, 'pbkdf2_sha256$');
    }

    public static function checkDjango(string $plain, string $hashed): bool
    {
        $parts = explode('$', $hashed);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
            return false;
        }

        [, $iterations, $salt, $hash] = $parts;
        $iterations = (int) $iterations;
        if ($iterations < 1 || $salt === '' || $hash === '') {
            return false;
        }

        $calculated = base64_encode(
            hash_pbkdf2('sha256', $plain, $salt, $iterations, 32, true)
        );

        return hash_equals($hash, $calculated);
    }

    /**
     * If the stored password is still a Django hash, re-hash with bcrypt after a successful login.
     */
    public static function upgradeIfNeeded(Authenticatable $user, string $plain): void
    {
        $current = $user->getAuthPassword();
        if (! is_string($current) || ! self::isDjangoHash($current)) {
            return;
        }

        if (! self::checkDjango($plain, $current)) {
            return;
        }

        // Bypass "hashed" cast double-processing by setting a bcrypt string that isHashed() recognizes.
        $user->forceFill([
            'password' => Hash::make($plain),
        ])->save();
    }
}
