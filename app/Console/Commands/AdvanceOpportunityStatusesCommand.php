<?php

namespace App\Console\Commands;

use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\VolunteerOpportunity;
use App\Services\Opportunity\SyncService;
use Illuminate\Console\Command;

/** Port of Django Celery task apps.opportunity.tasks.update_opportunity_status */
class AdvanceOpportunityStatusesCommand extends Command
{
    protected $signature = 'fursa:advance-statuses';

    protected $description = 'Advance volunteer/learn-serve opportunities and events based on dates (Python: update-opportunity-status)';

    public function handle(): int
    {
        $today = now()->toDateString();
        $synced = [];

        foreach ([VolunteerOpportunity::class, LearnServeOpportunity::class] as $model) {
            $items = $model::query()
                ->notDeleted()
                ->with(['sponsorImages.organization'])
                ->get();

            foreach ($items as $opp) {
                if (! $opp->start_date || ! $opp->end_date) {
                    continue;
                }

                $start = optional($opp->start_date)->toDateString();
                $end = optional($opp->end_date)->toDateString();

                if ($start > $today) {
                    $newStatus = OpportunityStatus::UPCOMING;
                } elseif ($start <= $today && $end >= $today) {
                    $newStatus = OpportunityStatus::INPROGRESS;
                } else {
                    $newStatus = OpportunityStatus::COMPLETED;
                }

                $current = $opp->opportunity_status instanceof OpportunityStatus
                    ? $opp->opportunity_status
                    : OpportunityStatus::tryFrom((string) $opp->opportunity_status);

                if ($current === $newStatus) {
                    continue;
                }

                $opp->opportunity_status = $newStatus;

                if (
                    $model === VolunteerOpportunity::class
                    && $newStatus === OpportunityStatus::COMPLETED
                    && ! $opp->is_public
                ) {
                    $opp->is_public = true;
                }

                $opp->save();

                if ($newStatus === OpportunityStatus::COMPLETED) {
                    $synced[$opp->created_by] = true;
                    foreach ($opp->sponsorImages ?? [] as $sponsor) {
                        if ($sponsor->organization?->user_id) {
                            $synced[$sponsor->organization->user_id] = true;
                        }
                    }
                }
            }
        }

        Event::query()->notDeleted()->with(['sponsorImages.organization'])->get()->each(function (Event $event) use ($today, &$synced) {
            if (! $event->start_date || ! $event->end_date) {
                return;
            }

            $start = optional($event->start_date)->toDateString();
            $end = optional($event->end_date)->toDateString();

            if ($start > $today) {
                $newStatus = OpportunityStatus::UPCOMING;
            } elseif ($start <= $today && $end >= $today) {
                $newStatus = OpportunityStatus::INPROGRESS;
            } else {
                $newStatus = OpportunityStatus::COMPLETED;
            }

            $current = $event->event_status instanceof OpportunityStatus
                ? $event->event_status
                : OpportunityStatus::tryFrom((string) $event->event_status);

            if ($current === $newStatus) {
                return;
            }

            $event->event_status = $newStatus;
            $event->save();

            if ($newStatus === OpportunityStatus::COMPLETED) {
                foreach ($event->sponsorImages ?? [] as $sponsor) {
                    if ($sponsor->organization?->user_id) {
                        $synced[$sponsor->organization->user_id] = true;
                    }
                }
            }
        });

        foreach (array_keys($synced) as $userId) {
            SyncService::syncUser((int) $userId);
        }

        $this->info('Statuses advanced. Synced users: '.count($synced));

        return self::SUCCESS;
    }
}
