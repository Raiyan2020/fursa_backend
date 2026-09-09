<?php

namespace App\Services\Opportunity;

use App\Models\Event;
use App\Models\MasterChoice;
use Illuminate\Validation\ValidationException;

class EventParticipation
{
    public static function normalize(array $data, ?Event $event = null): array
    {
        $id = $data['participation_type_id'] ?? $event?->participation_type_id;
        if (! $id) {
            return $data; // Legacy API clients without a choice retain their explicit flags.
        }
        $choice = MasterChoice::query()->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'event_participation_type'))->find($id);
        if (! $choice) {
            throw ValidationException::withMessages(['participation_type_id' => ['Select an event participation choice.']]);
        }
        $mapping = match (strtolower(trim($choice->value_en))) {
            'paid event' => [true, true],
            'free event' => [false, false],
            'free event (registration required)' => [true, false],
            // These describe audience, not payment. Require explicit controls.
            'individual', 'team' => null,
            default => throw ValidationException::withMessages(['participation_type_id' => ['Unsupported participation choice.']]),
        };
        if ($mapping) {
            foreach (['registration_required' => $mapping[0], 'paid_registration' => $mapping[1]] as $key => $value) {
                if (array_key_exists($key, $data) && (bool) $data[$key] !== $value) {
                    throw ValidationException::withMessages([$key => ['This flag conflicts with participation_type_id.']]);
                }
                $data[$key] = $value;
            }
        } else {
            foreach (['registration_required', 'paid_registration'] as $key) {
                if (! array_key_exists($key, $data) && (! $event || $event->participation_type_id != $id)) {
                    throw ValidationException::withMessages([$key => ['Explicit flags are required for Individual/Team.']]);
                }
            }
        }
        $required = (bool) ($data['registration_required'] ?? $event?->registration_required);
        $paid = (bool) ($data['paid_registration'] ?? $event?->paid_registration);
        $fee = $data['registration_fee'] ?? $event?->registration_fee ?? 0;
        if ($paid && (! $required || $fee <= 0)) {
            throw ValidationException::withMessages(['registration_fee' => ['Paid registration requires registration_required=true and a positive fee.']]);
        }
        if (! $paid) {
            if (isset($data['registration_fee']) && $data['registration_fee'] > 0) {
                throw ValidationException::withMessages(['registration_fee' => ['Free events cannot have a registration fee.']]);
            }
            $data['registration_fee'] = 0;
        }
        if (! $required) {
            if (! empty($data['participants_needed']) || ! empty($data['registration_link'])) {
                throw ValidationException::withMessages(['registration_required' => ['No-registration events cannot set registration capacity or a registration link.']]);
            }
            $data['participants_needed'] = 0;
            $data['registration_link'] = null;
        }

        return $data;
    }
}
