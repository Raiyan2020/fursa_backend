<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerOpportunityRegistrationResource;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAssignment;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerOpportunityRole;
use App\Models\VolunteerOpportunityTeam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VolunteerOpportunityRegistrationController extends Controller
{
    use HandlesOpportunities;

    public function index(Request $request): JsonResponse
    {
        $query = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user', 'assignment.role', 'assignment.team']);

        if ($opportunityId = $request->query('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }

        foreach (['role_id', 'team_id'] as $param) {
            $ids = (array) $request->query($param, []);
            if ($ids) {
                $query->whereHas('assignment', fn ($q) => $q->whereIn("{$param}", $ids));
            }
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $paginator = $this->paginateQuery($query->distinct(), $request);

        return ApiResponse::paginated(
            $paginator,
            VolunteerOpportunityRegistrationResource::collection($paginator->getCollection()),
            'Registrations retrieved successfully.',
            'تم استرجاع التسجيلات بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'role_id' => ['nullable', 'integer', 'exists:volunteer_opportunity_roles,id'],
            'team_id' => ['nullable', 'integer', 'exists:volunteer_opportunity_teams,id'],
            'organization_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if (VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->exists()) {
            return ApiResponse::error(
                'You are already registered for this opportunity.',
                'أنت مسجل بالفعل في هذه الفرصة.',
                400
            );
        }

        $userAge = $this->calculateAge($user->birth_year);
        if ($userAge === null) {
            return ApiResponse::error(
                'Please provide your birth year to check age eligibility.',
                'يرجى تقديم سنة ميلادك للتحقق من أهلية العمر.',
                400
            );
        }

        $fromAge = $opportunity->from_age ?? 7;
        $toAge = $opportunity->to_age;
        if ($toAge === null) {
            if ($userAge < $fromAge) {
                return ApiResponse::error(
                    'Sorry, you cannot register. This opportunity is restricted to a different age group.',
                    'عذرًا، لا يمكنك التسجيل. هذه الفرصة مخصصة لفئة عمرية مختلفة.',
                    400
                );
            }
        } elseif ($userAge < $fromAge || $userAge > $toAge) {
            return ApiResponse::error(
                'Sorry, you cannot register. This opportunity is restricted to a different age group.',
                'عذرًا، لا يمكنك التسجيل. هذه الفرصة مخصصة لفئة عمرية مختلفة.',
                400
            );
        }

        $role = null;
        $team = null;
        if (! empty($data['role_id'])) {
            $role = VolunteerOpportunityRole::query()->notDeleted()->find($data['role_id']);
            if (! $role || $role->opportunity_id !== $opportunity->id) {
                return ApiResponse::error('Role does not belong to opportunity.', 'الدور لا ينتمي إلى الفرصة.', 400);
            }
            $assigned = VolunteerOpportunityAssignment::query()
                ->notDeleted()
                ->where('role_id', $role->id)
                ->whereHas('registration', fn ($q) => $q->notDeleted()->where('opportunity_id', $opportunity->id))
                ->count();
            if ($assigned >= $role->participants_needed) {
                return ApiResponse::error('The role has no remaining slots available.', 'الدور ليس لديه أي فتحات متبقية متاحة.', 400);
            }
        }

        if (! empty($data['team_id'])) {
            $team = VolunteerOpportunityTeam::query()->notDeleted()->find($data['team_id']);
            if (! $team || $team->opportunity_id !== $opportunity->id) {
                return ApiResponse::error('Team does not belong to opportunity.', 'الفريق لا ينتمي إلى الفرصة.', 400);
            }
        }

        $registration = DB::transaction(function () use ($user, $opportunity, $role, $team) {
            $registration = VolunteerOpportunityRegistration::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'registration_date' => now(),
                'status' => ApprovalStatus::APPROVED,
            ]);

            if ($role || $team) {
                VolunteerOpportunityAssignment::create([
                    'registration_id' => $registration->id,
                    'role_id' => $role?->id,
                    'team_id' => $team?->id,
                ]);
            }

            return $registration;
        });

        $registration->load(['user', 'assignment.role', 'assignment.team']);
        $totalAssigned = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->count();

        return ApiResponse::success([
            'registration' => (new VolunteerOpportunityRegistrationResource($registration))->resolve(),
            'assignment_id' => $registration->assignment?->id,
            'remaining_slots' => max(0, $opportunity->participants_needed - $totalAssigned),
            'user_age' => $userAge,
            'required_age_from' => $fromAge,
            'required_age_to' => $toAge,
            'meets_age_requirement' => true,
        ], 'Successfully registered for the opportunity.', 'تم التسجيل بنجاح في الفرصة.', 201);
    }

    public function updateAssignment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration' => ['required', 'integer', 'exists:volunteer_opportunity_registrations,id'],
            'role' => ['nullable', 'integer', 'exists:volunteer_opportunity_roles,id'],
            'team' => ['nullable', 'integer', 'exists:volunteer_opportunity_teams,id'],
        ]);

        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with('opportunity')
            ->find($data['registration']);

        if (! $registration || $registration->opportunity?->is_deleted) {
            return ApiResponse::error('Registration does not exist or has been deleted.', 'التسجيل غير موجود أو تم حذفه.', 404);
        }

        if ($registration->opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'You can only update assignments for opportunities you created.',
                'يمكنك فقط تحديث التعيينات للفرص التي أنشأتها.',
                403
            );
        }

        $assignment = VolunteerOpportunityAssignment::query()
            ->notDeleted()
            ->firstOrCreate(['registration_id' => $registration->id]);

        if (! empty($data['role'])) {
            $role = VolunteerOpportunityRole::query()->notDeleted()->find($data['role']);
            if (! $role || $role->opportunity_id !== $registration->opportunity_id) {
                return ApiResponse::error('Role does not belong to opportunity.', 'الدور لا ينتمي إلى الفرصة.', 400);
            }
            $assignment->role_id = $role->id;
        }

        if (! empty($data['team'])) {
            $team = VolunteerOpportunityTeam::query()->notDeleted()->find($data['team']);
            if (! $team || $team->opportunity_id !== $registration->opportunity_id) {
                return ApiResponse::error('Team does not belong to opportunity.', 'الفريق لا ينتمي إلى الفرصة.', 400);
            }
            $assignment->team_id = $team->id;
        }

        $assignment->save();
        $registration->load(['user', 'assignment.role', 'assignment.team']);

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration),
            'Assignment updated successfully.',
            'تم تحديث التعيين بنجاح.'
        );
    }

    public function directRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to directly register volunteers for this opportunity.',
                'ليس لديك إذن لتسجيل المتطوعين مباشرة لهذه الفرصة.',
                403
            );
        }

        $successful = [];
        $failed = [];

        foreach ($data['user_ids'] as $userId) {
            $volunteerUser = User::query()->notDeleted()->find($userId);
            if (! $volunteerUser) {
                $failed[] = ['user_id' => $userId, 'error' => 'User does not exist.'];
                continue;
            }

            if (VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $userId)
                ->exists()) {
                $failed[] = ['user_id' => $userId, 'error' => 'User is already registered.'];
                continue;
            }

            $registration = VolunteerOpportunityRegistration::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $userId,
                'registration_date' => now(),
                'status' => ApprovalStatus::APPROVED,
            ]);
            $assignment = VolunteerOpportunityAssignment::create(['registration_id' => $registration->id]);
            $registration->load(['user', 'assignment']);

            $successful[] = [
                'user_id' => $userId,
                'user_name' => trim(($volunteerUser->first_name ?? '').' '.($volunteerUser->last_name ?? '')),
                'registration' => (new VolunteerOpportunityRegistrationResource($registration))->resolve(),
                'assignment_id' => $assignment->id,
            ];
        }

        $totalAssigned = VolunteerOpportunityRegistration::query()->notDeleted()->where('opportunity_id', $opportunity->id)->count();

        return ApiResponse::success([
            'successful_registrations' => $successful,
            'failed_registrations' => $failed,
            'success_count' => count($successful),
            'failed_count' => count($failed),
            'remaining_slots' => max(0, $opportunity->participants_needed - $totalAssigned),
        ], 'Direct registration processed.', 'تمت معالجة التسجيل المباشر.', count($successful) > 0 ? 201 : 400);
    }

    public function directUnregister(Request $request): JsonResponse
    {
        $userIds = $request->input('user_ids', []);
        if ($request->filled('user_id') && empty($userIds)) {
            $userIds = [$request->input('user_id')];
        }

        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        if (empty($userIds)) {
            return ApiResponse::error('At least one user ID is required.', 'مطلوب معرف مستخدم واحد على الأقل.', 400);
        }

        $removed = 0;
        foreach ($userIds as $userId) {
            $registration = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $userId)
                ->first();

            if ($registration) {
                VolunteerOpportunityAssignment::query()
                    ->where('registration_id', $registration->id)
                    ->get()
                    ->each->softDeleteFlags();
                $registration->softDeleteFlags();
                $removed++;
            }
        }

        return ApiResponse::success(
            ['removed_count' => $removed],
            'Direct unregistration processed.',
            'تمت معالجة إلغاء التسجيل المباشر.'
        );
    }

    public function unregister(Request $request, int $opportunity_id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        VolunteerOpportunityAssignment::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->each->softDeleteFlags();
        $registration->softDeleteFlags();

        return ApiResponse::success(null, 'Successfully unregistered from the opportunity.', 'تم إلغاء التسجيل من الفرصة بنجاح.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user.volunteerProfile', 'assignment.role', 'assignment.team'])
            ->find($id);

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration),
            'Registration retrieved successfully.',
            'تم استرداد التسجيل بنجاح.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()->notDeleted()->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'string'],
        ]);

        $registration->update($data);

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration->fresh(['user.volunteerProfile', 'assignment.role', 'assignment.team'])),
            'Registration updated successfully.',
            'تم تحديث التسجيل بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()->notDeleted()->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        VolunteerOpportunityAssignment::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->each->softDeleteFlags();
        $registration->softDeleteFlags();

        return ApiResponse::success(null, 'Registration deleted successfully.', 'تم حذف التسجيل بنجاح.');
    }
}
