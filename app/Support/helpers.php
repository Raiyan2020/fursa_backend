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

        // Already stored as "/storage/..."
        if (str_starts_with($filename, '/storage/') || str_starts_with($filename, 'storage/')) {
            return asset(ltrim($filename, '/'));
        }

        // Relative disk path e.g. "banners/x.png"
        return asset('storage/'.ltrim($filename, '/'));
    }
}

if (! function_exists('uploader')) {
    /**
     * Store an uploaded file on the public disk and return "/storage/..." path (Nafas-style).
     */
    function uploader($file, string $folder = 'uploads'): ?string
    {
        if (! $file) {
            return null;
        }

        $path = Storage::disk('public')->putFile($folder, $file);

        return '/storage/'.$path;
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
