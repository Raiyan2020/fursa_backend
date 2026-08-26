<?php

namespace App\Console\Commands;

use App\Models\VolunteerProfile;
use App\Services\Volunteer\QrCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Port of Django apps.volunteerprofile.tasks.generate_missing_qr_codes */
class GenerateMissingQrCodesCommand extends Command
{
    protected $signature = 'fursa:generate-missing-qr-codes';

    protected $description = 'Generate missing volunteer QR codes (Python: generate_missing_qr_codes)';

    public function handle(): int
    {
        // An empty column is not the only way a QR goes missing — the file it
        // points at can be gone from disk too (a media restore, a moved folder).
        // Those rows were silently skipped, so the profile stayed broken forever.
        $candidates = VolunteerProfile::query()
            ->notDeleted()
            ->whereNotNull('uuid')
            ->get()
            ->filter(function (VolunteerProfile $profile) {
                if (blank($profile->qr_code)) {
                    return true;
                }

                return ! Storage::disk('public')->exists(
                    normalize_storage_path($profile->qr_code)
                );
            })
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('No missing QR codes found.');

            return self::SUCCESS;
        }

        $this->info("Profiles needing a QR code: {$candidates->count()}");

        $generated = 0;
        $failed = 0;
        foreach ($candidates as $profile) {
            if (QrCodeService::generateForProfile($profile)) {
                $generated++;
            } else {
                $failed++;
            }
        }

        $this->info("QR codes generated: {$generated}/{$candidates->count()}");

        if ($failed > 0) {
            // Generation calls an external service, so a network/firewall problem
            // shows up here rather than as a silent no-op.
            $this->warn("Failed: {$failed}. Check the log and that the server can reach api.qrserver.com.");
        }

        return self::SUCCESS;
    }
}
