<?php

namespace App\Console\Commands;

use App\Models\VolunteerProfile;
use App\Services\Volunteer\QrCodeService;
use Illuminate\Console\Command;

/** Port of Django apps.volunteerprofile.tasks.generate_missing_qr_codes */
class GenerateMissingQrCodesCommand extends Command
{
    protected $signature = 'fursa:generate-missing-qr-codes';

    protected $description = 'Generate missing volunteer QR codes (Python: generate_missing_qr_codes)';

    public function handle(): int
    {
        $profiles = VolunteerProfile::query()
            ->notDeleted()
            ->where(function ($q) {
                $q->whereNull('qr_code')->orWhere('qr_code', '');
            })
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('No missing QR codes found.');

            return self::SUCCESS;
        }

        $generated = 0;
        foreach ($profiles as $profile) {
            if (QrCodeService::generateForProfile($profile)) {
                $generated++;
            }
        }

        $this->info("QR codes generated: {$generated}/{$profiles->count()}");

        return self::SUCCESS;
    }
}
