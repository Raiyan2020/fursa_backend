<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\Event\EventTimeSlotResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTimeSlot;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventTimeSlotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');
        if (! $eventId) {
            return ApiResponse::error('Event ID is required.', 'معرف الحدث مطلوب.', 400);
        }

        $event = Event::query()->notDeleted()->find($eventId);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $slots = EventTimeSlot::query()
            ->notDeleted()
            ->where('event_id', $eventId)
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('date', $date))
            ->get()
            ->filter(function (EventTimeSlot $slot) {
                $count = EventRegistration::query()
                    ->notDeleted()
                    ->where('event_id', $slot->event_id)
                    ->where('time_slot_id', $slot->id)
                    ->count();

                return $count < $slot->participants_needed;
            })
            ->values();

        $remaining = $this->remainingParticipants($event);

        return ApiResponse::success([
            'items' => EventTimeSlotResource::collection($slots),
            'meta' => ['remaining_participants' => $remaining],
        ], 'Time slots retrieved successfully.', 'تم استرداد الفترات الزمنية بنجاح.');
    }

    public function show(int $id): JsonResponse
    {
        $slot = EventTimeSlot::query()->notDeleted()->with('event')->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        return ApiResponse::success([
            'slot' => new EventTimeSlotResource($slot),
            'meta' => ['remaining_participants' => $this->remainingParticipants($slot->event)],
        ], 'Time slot retrieved successfully.', 'تم استرداد الفترة الزمنية بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'integer', 'exists:events,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'participants_needed' => ['required', 'integer', 'min:1'],
        ]);

        $event = Event::query()->notDeleted()->find($data['event']);
        $this->assertEventOwner($request, $event);

        $slot = EventTimeSlot::create([
            'event_id' => $event->id,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'participants_needed' => $data['participants_needed'],
        ]);

        return ApiResponse::success(
            new EventTimeSlotResource($slot),
            'Time slot created successfully.',
            'تم إنشاء الفترة الزمنية بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $slot = EventTimeSlot::query()->notDeleted()->with('event')->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        $this->assertEventOwner($request, $slot->event);

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'string'],
            'end_time' => ['sometimes', 'string'],
            'participants_needed' => ['sometimes', 'integer', 'min:1'],
        ]);

        $slot->update($data);

        return ApiResponse::success(
            new EventTimeSlotResource($slot->fresh()),
            'Time slot updated successfully.',
            'تم تحديث الفترة الزمنية بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $slot = EventTimeSlot::query()->notDeleted()->with('event')->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        $this->assertEventOwner($request, $slot->event);
        $slot->softDeleteFlags();

        return ApiResponse::success(null, 'Time slot deleted successfully.', 'تم حذف الفترة الزمنية بنجاح.', 204);
    }

    protected function remainingParticipants(Event $event): int
    {
        $allocated = EventTimeSlot::query()->where('event_id', $event->id)->sum('participants_needed');

        return max(0, ($event->participants_needed ?? 0) - (int) $allocated);
    }

    protected function assertEventOwner(Request $request, ?Event $event): void
    {
        if (! $event) {
            throw ValidationException::withMessages(['event' => ['Event not found.']]);
        }

        $org = $request->user()->organizationProfile;
        if (! $org || $event->created_by !== $org->id) {
            throw ValidationException::withMessages([
                'permission' => ['Only the event creator can manage time slots.'],
            ]);
        }
    }
}
