<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\MasterChoice;
use App\Models\Sponsor;
use Illuminate\Database\Seeder;

class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $sponsorType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'sponsor_type'))
            ->where('value_en', 'Supporting Partner')
            ->first();

        $orgType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Private')
            ->first();

        $support = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'type_of_support'))
            ->where('value_en', 'Financial')
            ->first()
            ?? MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'type_of_support'))
                ->first();

        Sponsor::query()->firstOrCreate(
            ['email' => 'sponsor@example.com'],
            [
                'org_name' => 'Fursa Sample Sponsor',
                'person_name' => 'Sponsor Contact',
                'country_code' => '+965',
                'phone_number' => '50000000',
                'sponsor_type_id' => $sponsorType?->id,
                'org_type_id' => $orgType?->id,
                'type_of_support_id' => $support?->id,
                'sponsorship_details' => 'Sample approved sponsor for local development.',
                'why_interested' => 'Supporting volunteering community.',
                'resources_expected' => 'Brand visibility.',
                'approval_status' => ApprovalStatus::APPROVED,
                'preferred_language' => 'en',
            ]
        );
    }
}
