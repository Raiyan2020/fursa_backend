<?php

namespace App\Http\Controllers\Api\Event;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Event\EventRegistrationResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        if ($eventId = $request->query('event_id') ?? $request->query('event')) {
            $query->where('event_id', $eventId);
        }
        if ($status = $request->query('status')) {
            $query->where('registration_status', $status);
        }
        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }
        if ($request->has('is_attended')) {
            $query->where('is_attended', filter_var($request->query('is_attended'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->query('sort_by', 'registration_date');
        $sortOrder = $request->query('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'registration_date' => 'registration_date',
            'name' => 'users.first_name',
            'email' => 'users.email',
            'status' => 'registration_status',
            'attendance' => 'is_attended',
            'payment' => 'payment_status',
        ];
        if (isset($sortMap[$sortBy])) {
            if (in_array($sortBy, ['name', 'email'], true)) {
                $query->join('users', 'users.id', '=', 'event_registrations.user_id')
                    ->orderBy($sortMap[$sortBy], $sortOrder)
                    ->select('event_registrations.*');
            } else {
                $query->orderBy($sortMap[$sortBy], $sortOrder);
            }
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->with(['user', 'event'])->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            EventRegistrationResource::collection($paginator->getCollection()),
            'Registrations retrieved successfully.',
            'تم استرجاع التسجيلات بنجاح.'
        );
    }

    public function byEvent(Request $request, int $event_id): JsonResponse
    {
        $event = Event::query()->notDeleted()->find($event_id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $org = $request->user()->organizationProfile;
        if (! $org || $event->created_by !== $org->id) {
            return ApiResponse::error(
                'Only the event organizer can view all registrations.',
                'يمكن فقط لمنظم الحدث عرض جميع التسجيلات.',
                403
            );
        }

        $request->merge(['event_id' => $event_id]);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'integer', 'exists:events,id'],
            'time_slot_id' => ['nullable', 'integer', 'exists:event_time_slots,id'],
        ]);

        $event = Event::query()->notDeleted()->find($data['event']);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        if ($event->due_date && now()->gt($event->due_date)) {
            return ApiResponse::fail('Registration closed.', 400, [], [
                'event' => ['id' => $event->id, 'title' => $event->title_en ?: $event->title_ar],
                'due_date' => $event->due_date?->toIso8601String(),
            ]);
        }

        $existing = EventRegistration::query()
            ->notDeleted()
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($existing) {
            return ApiResponse::error('Already registered for this event.', 'مسجل بالفعل في هذا الحدث.', 400);
        }

        if ($event->registration_required && $event->participants_needed > 0) {
            $count = EventRegistration::query()->notDeleted()->where('event_id', $event->id)->count();
            if ($count >= $event->participants_needed) {
                return ApiResponse::fail('No remaining slots.', 400, [], [
                    'event' => ['id' => $event->id, 'title' => $event->title_en ?: $event->title_ar],
                    'remaining_slots' => 0,
                    'total_slots' => $event->participants_needed,
                ]);
            }
        }

        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $request->user()->id,
            'time_slot_id' => $data['time_slot_id'] ?? null,
            'registration_date' => now(),
            'registration_status' => ApprovalStatus::APPROVED,
            'payment_status' => $event->paid_registration ? PaymentStatus::PENDING : PaymentStatus::PAID,
        ]);

        $totalRegistered = EventRegistration::query()->notDeleted()->where('event_id', $event->id)->count();
        $remaining = max(0, ($event->participants_needed ?? 0) - $totalRegistered);

        return ApiResponse::success([
            'registration' => new EventRegistrationResource($registration->load(['user', 'event'])),
            'remaining_slots' => $remaining,
            'payment_required' => (bool) $event->paid_registration,
        ], 'Successfully registered for the event.', 'تم التسجيل بنجاح في الحدث.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $registration = EventRegistration::query()->notDeleted()->with('event')->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        $org = $request->user()->organizationProfile;
        if (! $org || $registration->event->created_by !== $org->id) {
            return ApiResponse::error(
                'Only the event organizer can update registrations.',
                'يمكن فقط لمنظم الحدث تحديث التسجيلات.',
                403
            );
        }

        $payload = $request->all();
        if (isset($payload['status']) && ! isset($payload['registration_status'])) {
            $payload['registration_status'] = $payload['status'];
        }

        $data = validator($payload, [
            'registration_status' => ['sometimes', 'string'],
            'payment_status' => ['sometimes', 'string'],
            'is_attended' => ['sometimes', 'boolean'],
            'time_slot_id' => ['nullable', 'integer', 'exists:event_time_slots,id'],
        ])->validate();

        $registration->update($data);

        return ApiResponse::success(
            new EventRegistrationResource($registration->fresh(['user', 'event'])),
            'Registration updated successfully.',
            'تم تحديث التسجيل بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $registration = EventRegistration::query()->notDeleted()->with('event')->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        $user = $request->user();
        $org = $user->organizationProfile;
        $isOrganizer = $org && $registration->event->created_by === $org->id;
        $isOwner = $registration->user_id === $user->id;

        if (! $isOrganizer && ! $isOwner) {
            return ApiResponse::error(
                "You don't have permission to cancel this registration.",
                'ليس لديك إذن لإلغاء هذا التسجيل.',
                403
            );
        }

        $registration->softDeleteFlags();

        return ApiResponse::success(null, 'Registration cancelled successfully.', 'تم إلغاء التسجيل بنجاح.');
    }

    public function unregister(Request $request, int $event_id): JsonResponse
    {
        $registration = EventRegistration::query()
            ->notDeleted()
            ->where('event_id', $event_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        $registration->delete();

        return ApiResponse::success(null, 'Successfully unregistered from the event.', 'تم إلغاء التسجيل في الحدث بنجاح.');
    }

    protected function baseQuery(Request $request)
    {
        $user = $request->user();
        $org = $user->organizationProfile;

        if ($org) {
            return EventRegistration::query()
                ->notDeleted()
                ->whereHas('event', fn ($q) => $q->where('created_by', $org->id));
        }

        return EventRegistration::query()->notDeleted()->where('user_id', $user->id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $registration = EventRegistration::query()
            ->notDeleted()
            ->with(['user', 'event', 'timeSlot'])
            ->find($id);

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        return ApiResponse::success(
            new EventRegistrationResource($registration),
            'Registration retrieved successfully.',
            'تم استرداد التسجيل بنجاح.'
        );
    }

    public function myRegistrations(Request $request): JsonResponse
    {
        $query = EventRegistration::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->with(['user', 'event', 'timeSlot']);

        if ($status = $request->query('status')) {
            $query->where('registration_status', $status);
        }

        $today = now()->toDateString();
        if ($request->query('time') === 'upcoming') {
            $query->whereHas('event', fn ($q) => $q->whereDate('start_date', '>=', $today));
        } elseif ($request->query('time') === 'past') {
            $query->whereHas('event', fn ($q) => $q->whereDate('end_date', '<', $today));
        }

        $paginator = $query->latest()->paginate(
            min(100, max(1, (int) $request->query('limit', 20))),
            ['*'],
            'page',
            max(1, (int) $request->query('page', 1))
        );

        return ApiResponse::paginated(
            $paginator,
            EventRegistrationResource::collection($paginator->getCollection()),
            'Your registrations retrieved successfully.',
            'تم استرداد تسجيلاتك بنجاح.'
        );
    }

    public function eventRegistrations(Request $request, int $id): JsonResponse
    {
        $event = Event::query()->notDeleted()->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $request->merge(['event_id' => $id]);

        return $this->byEvent($request, $id);
    }
}
