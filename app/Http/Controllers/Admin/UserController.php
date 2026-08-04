<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->notDeleted()
            ->with(['organizationProfile.organizerType'])
            ->latest()
            ->get();

        return view('dashboard.users.index', compact('users'));
    }

    public function create()
    {
        return view('dashboard.users.create');
    }

    public function export()
    {
        $users = User::query()
            ->notDeleted()
            ->with(['organizationProfile.organizerType', 'volunteerProfile'])
            ->latest()
            ->get();

        $filename = 'users_'.now()->format('Y-m-d_His').'.xls';

        return response()->streamDownload(function () use ($users) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<tr>';
            foreach ([
                '#',
                __('first name'),
                __('last name'),
                __('email'),
                __('phone'),
                __('country code'),
                __('user type'),
                __('status'),
                __('banned'),
                __('preferred language'),
                __('company name'),
                __('nickname'),
                __('created at'),
            ] as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr>';

            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>'.e($user->id).'</td>';
                echo '<td>'.e($user->first_name).'</td>';
                echo '<td>'.e($user->last_name).'</td>';
                echo '<td>'.e($user->email).'</td>';
                echo '<td>'.e($user->phone_number).'</td>';
                echo '<td>'.e($user->country_code).'</td>';
                echo '<td>'.e($user->accountTypeLabel()).'</td>';
                echo '<td>'.e($user->is_active ? __('active') : __('inactive')).'</td>';
                echo '<td>'.e($user->is_banned ? __('banned') : __('active')).'</td>';
                echo '<td>'.e($user->preferred_language?->value ?? $user->preferred_language).'</td>';
                echo '<td>'.e($user->organizationProfile?->company_name).'</td>';
                echo '<td>'.e($user->organizationProfile?->nickname ?? $user->volunteerProfile?->nickname).'</td>';
                echo '<td>'.e(optional($user->created_at)?->format('Y-m-d H:i')).'</td>';
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            [$userType, $isVolunteerTeam] = $this->resolveAccountType($data['account_type']);

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => Str::lower(trim($data['email'])),
                'phone_number' => $data['phone_number'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'user_type' => $userType,
                'preferred_language' => $data['preferred_language'],
                'password' => $data['password'],
                'password_length' => strlen($data['password']),
                'is_active' => $request->boolean('is_active', true),
                'date_joined' => now(),
            ]);

            $this->syncProfileForAccountType($user, $data['account_type'], $data, creating: true);
        });

        added();

        return redirect()->route('admin.users.index');
    }

    public function show(User $user)
    {
        $user->load(['organizationProfile.organizerType', 'volunteerProfile']);

        return view('dashboard.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $user->load(['organizationProfile.organizerType', 'volunteerProfile']);

        return view('dashboard.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($data, $request, $user) {
            [$userType] = $this->resolveAccountType($data['account_type']);

            $payload = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => Str::lower(trim($data['email'])),
                'phone_number' => $data['phone_number'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'user_type' => $userType,
                'preferred_language' => $data['preferred_language'],
                'is_active' => $request->boolean('is_active'),
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
                $payload['password_length'] = strlen($data['password']);
            }

            $user->update($payload);
            $this->syncProfileForAccountType($user->fresh(), $data['account_type'], $data, creating: false);
        });

        updated();

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user)
    {
        $user->softDeleteFlags();
        deleted();

        return back();
    }

    public function ban(Request $request, User $user)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $user->is_banned = true;
        $user->manually_banned = true;
        $user->banned_time = now();
        $user->save();

        statusChange();

        return back();
    }

    public function unban(Request $request, User $user)
    {
        $user->is_banned = false;
        $user->manually_banned = false;
        $user->banned_time = null;
        $user->save();

        statusChange();

        return back();
    }

    /**
     * @return array{0: UserType, 1: bool}
     */
    protected function resolveAccountType(string $accountType): array
    {
        return match ($accountType) {
            'volunteer' => [UserType::VOLUNTEER, false],
            'volunteer_team' => [UserType::ORGANIZATION, true],
            'organization' => [UserType::ORGANIZATION, false],
            'admin' => [UserType::ADMIN, false],
            default => [UserType::VOLUNTEER, false],
        };
    }

    protected function syncProfileForAccountType(User $user, string $accountType, array $data, bool $creating): void
    {
        if ($accountType === 'volunteer') {
            VolunteerProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nickname' => $data['nickname'] ?? null,
                    'is_verified' => true,
                    'is_public' => true,
                    'uuid' => (string) Str::uuid(),
                ]
            );

            return;
        }

        if (in_array($accountType, ['organization', 'volunteer_team'], true)) {
            $organizerTypeId = $accountType === 'volunteer_team'
                ? $this->volunteerTeamTypeId()
                : null;

            $profile = OrganizationProfile::query()->firstOrNew(['user_id' => $user->id]);
            $profile->company_name = $data['company_name'] ?? $profile->company_name ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
            $profile->nickname = $data['nickname'] ?? $profile->nickname;
            $profile->organizer_type_id = $organizerTypeId;
            if (! $profile->exists || $creating) {
                $profile->organization_status = ApprovalStatus::APPROVED;
            }
            $profile->save();
        }
    }

    protected function volunteerTeamTypeId(): ?int
    {
        return MasterChoice::query()
            ->notDeleted()
            ->where('value_en', 'Volunteer Team')
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->value('id');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $updating = $user !== null;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [
                $updating ? 'nullable' : 'required',
                'confirmed',
                'string',
                'min:8',
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'account_type' => ['required', Rule::in(['volunteer', 'organization', 'volunteer_team', 'admin'])],
            'preferred_language' => ['required', 'in:en,ar'],
            'is_active' => ['nullable', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
        ], [], [
            'first_name' => __('admin.attributes.first_name'),
            'last_name' => __('admin.attributes.last_name'),
            'email' => __('admin.attributes.email'),
            'password' => __('admin.attributes.password'),
            'account_type' => __('admin.attributes.user_type'),
            'preferred_language' => __('admin.attributes.preferred_language'),
            'company_name' => __('admin.attributes.company_name'),
            'nickname' => __('admin.attributes.nickname'),
        ]);
    }
}
