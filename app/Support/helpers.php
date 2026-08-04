<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('forsa_flash')) {
    /**
     * Flash a toast alert consumed by resources/views/dashboard/partials/nafas-alert.blade.php
     */
    function forsa_flash(string $title, string $icon = 'success', int $timer = 4000): void
    {
        session()->flash('alert.config', json_encode([
            'title' => $title,
            'icon' => $icon,
            'toast' => true,
            'timer' => $timer,
        ]));
    }
}

if (! function_exists('added')) {
    function added(): void
    {
        forsa_flash(__('Added successfully !'));
    }
}

if (! function_exists('updated')) {
    function updated(): void
    {
        forsa_flash(__('Updated successfully !'));
    }
}

if (! function_exists('deleted')) {
    function deleted(): void
    {
        forsa_flash(__('Deleted successfully !'));
    }
}

if (! function_exists('statusChange')) {
    function statusChange(): void
    {
        forsa_flash(__('Status changed successfully !'));
    }
}

if (! function_exists('approvedFlash')) {
    function approvedFlash(): void
    {
        forsa_flash(__('Approved successfully !'));
    }
}

if (! function_exists('rejectedFlash')) {
    function rejectedFlash(): void
    {
        forsa_flash(__('Rejected successfully !'));
    }
}

if (! function_exists('uploadpath')) {
    function uploadpath(): string
    {
        return 'uploads';
    }
}

if (! function_exists('normalize_storage_path')) {
    /**
     * Normalize a stored file path for the Laravel public disk.
     *
     * Legacy Django/GCS rows may include a leading "public/" segment even though
     * files live under storage/app/public (served via public/storage symlink).
     */
    function normalize_storage_path(string $path): string
    {
        $path = ltrim($path, '/');

        while (str_starts_with($path, 'storage/') || str_starts_with($path, 'public/')) {
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
                continue;
            }

            if (str_starts_with($path, 'public/')) {
                $path = substr($path, strlen('public/'));
            }
        }

        return $path;
    }
}

if (! function_exists('getimg')) {
    /**
     * Convert a stored path to a public asset URL (Nafas-compatible).
     */
    function getimg(?string $filename): ?string
    {
        if (empty($filename)) {
            return null;
        }

        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return $filename;
        }

        $path = normalize_storage_path($filename);

        $base = rtrim((string) (config('fursa.backend_host') ?: config('app.url')), '/');

        return $base.'/storage/'.$path;
    }
}

if (! function_exists('uploader')) {
    /**
     * Store an uploaded file on the public disk and return the relative disk path.
     */
    function uploader($file, string $folder = 'uploads'): ?string
    {
        if (! $file) {
            return null;
        }

        return Storage::disk('public')->putFile($folder, $file);
    }
}

if (! function_exists('admin_upload')) {
    /**
     * Alias for uploader() — store on public disk.
     */
    function admin_upload($file, string $folder = 'uploads'): ?string
    {
        return uploader($file, $folder);
    }
}

if (! function_exists('admin_asset_url')) {
    /**
     * Resolve a stored file path to a public URL.
     */
    function admin_asset_url(?string $path): ?string
    {
        return getimg($path);
    }
}

if (! function_exists('tr')) {
    /**
     * Pick the localized value based on the current app locale.
     */
    function tr(?string $en, ?string $ar): ?string
    {
        return app()->getLocale() === 'ar' ? ($ar ?: $en) : ($en ?: $ar);
    }
}

if (! function_exists('to_eastern_arabic_digits')) {
    /**
     * Replace Western digits (0-9) with Eastern Arabic digits (٠-٩).
     */
    function to_eastern_arabic_digits(string|int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr((string) $value, [
            '0' => '٠',
            '1' => '١',
            '2' => '٢',
            '3' => '٣',
            '4' => '٤',
            '5' => '٥',
            '6' => '٦',
            '7' => '٧',
            '8' => '٨',
            '9' => '٩',
        ]);
    }
}

if (! function_exists('to_western_digits')) {
    /**
     * Replace Eastern Arabic digits (٠-٩) with Western digits (0-9).
     */
    function to_western_digits(string|int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr((string) $value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            // Persian variants sometimes pasted from clients
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }
}

if (! function_exists('ar_num')) {
    /**
     * Localize numeric display values for Arabic locale.
     * Keeps original type for English; returns Eastern Arabic digit strings for Arabic.
     */
    function ar_num(string|int|float|null $value): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (app()->getLocale() !== 'ar') {
            return $value;
        }

        return to_eastern_arabic_digits($value);
    }
}

if (! function_exists('filter_int')) {
    /**
     * Parse an integer filter value, accepting Eastern Arabic digits.
     */
    function filter_int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = to_western_digits(is_scalar($value) ? (string) $value : null);
        if ($normalized === null || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }
}

if (! function_exists('filter_float')) {
    /**
     * Parse a float filter value, accepting Eastern Arabic digits.
     */
    function filter_float(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = to_western_digits(is_scalar($value) ? (string) $value : null);
        if ($normalized === null || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}

if (! function_exists('filter_bool')) {
    /**
     * Parse a boolean filter value from query strings (true/false/1/0/yes/no).
     */
    function filter_bool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed;
    }
}
