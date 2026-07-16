<?php

namespace App\Services\Volunteer;

use App\Models\VolunteerProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    public static function generateForProfile(VolunteerProfile $profile): bool
    {
        if (! $profile->uuid) {
            return false;
        }

        try {
            $response = Http::timeout(20)->get('https://api.qrserver.com/v1/create-qr-code/', [
                'size' => '300x300',
                'data' => (string) $profile->uuid,
            ]);

            if (! $response->successful()) {
                Log::warning("QR API failed for volunteer {$profile->id}");

                return false;
            }

            $path = 'qr_codes/'.$profile->uuid.'.png';
            Storage::disk('public')->put($path, $response->body());

            $profile->qr_code = $path;
            $profile->save();

            return true;
        } catch (\Throwable $e) {
            Log::error("QR generation failed for volunteer {$profile->id}: ".$e->getMessage());

            return false;
        }
    }
}
