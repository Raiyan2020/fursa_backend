@php
    $accountType = old(
        'account_type',
        isset($user) ? $user->accountTypeKey() : 'volunteer'
    );
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('first name') }} <span class="text-danger">*</span></label>
        <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('last name') }} <span class="text-danger">*</span></label>
        <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('email') }} <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
        <small class="text-muted">{{ __('Changing this email changes the sign-in email for this account') }}</small>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('phone') }}</label>
        <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $user->phone_number ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('country code') }}</label>
        <input type="text" name="country_code" class="form-control" value="{{ old('country_code', $user->country_code ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('nationality') }}</label>
        @php $currentNationality = old('nationality', $user->nationality->value ?? $user->nationality ?? ''); @endphp
        <select name="nationality" class="form-control">
            <option value="">{{ __('not specified') }}</option>
            @foreach (\App\Enums\Nationality::cases() as $nationality)
                <option value="{{ $nationality->value }}" {{ $currentNationality === $nationality->value ? 'selected' : '' }}>
                    {{ __('admin.nationalities.'.$nationality->value) }}
                </option>
            @endforeach
        </select>
        @error('nationality') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('civil id') }}</label>
        <input type="text" name="civil_id" class="form-control" value="{{ old('civil_id', $user->civil_id ?? '') }}">
        @error('civil_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    @php
        // Volunteer and entity are different kinds of account, not interchangeable
        // field sets, so an existing account cannot cross that boundary. The
        // controller enforces this; the options are disabled here so the reason
        // is visible before submitting.
        $isExisting = ! empty($user);
        $onVolunteerSide = $accountType === 'volunteer';
        $onOrganizationSide = in_array($accountType, ['organization', 'volunteer_team'], true);
        $lockCrossSide = $isExisting && ($onVolunteerSide || $onOrganizationSide);

        $blockedOption = function (string $value) use ($lockCrossSide, $onVolunteerSide, $onOrganizationSide) {
            if (! $lockCrossSide) {
                return false;
            }
            if ($onVolunteerSide) {
                return in_array($value, ['organization', 'volunteer_team'], true);
            }
            if ($onOrganizationSide) {
                return $value === 'volunteer';
            }

            return false;
        };
    @endphp
    <div class="col-md-6 mb-1">
        <label>{{ __('user type') }} <span class="text-danger">*</span></label>
        <select name="account_type" id="account_type" class="form-control" required>
            @foreach ([
                'volunteer' => __('admin.user_types.volunteer'),
                'organization' => __('admin.user_types.organization'),
                'volunteer_team' => __('admin.user_types.volunteer_team'),
                'admin' => __('admin.user_types.admin'),
            ] as $value => $label)
                <option value="{{ $value }}"
                    {{ $accountType === $value ? 'selected' : '' }}
                    {{ $blockedOption($value) ? 'disabled' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @if ($lockCrossSide)
            <small class="text-muted d-block mt-half">
                {{ __('An account cannot be switched between volunteer and entity. Register a separate account instead.') }}
            </small>
        @endif
        @error('account_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('company name') }}</label>
        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $user->organizationProfile->company_name ?? '') }}">
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('nickname') }}</label>
        <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $user->organizationProfile->nickname ?? $user->volunteerProfile->nickname ?? '') }}">
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('admin.attributes.license_number') }}</label>
        <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $user->organizationProfile->license_number ?? '') }}">
        @error('license_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('admin.attributes.license_documents') }}</label>
        <input type="file" name="license_documents[]" class="form-control" accept="image/*,.pdf" multiple>
        @error('license_documents') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('license_documents.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @if (! empty($user) && $user->organizationProfile)
            @php $currentDocuments = $user->organizationProfile->documents()->where('is_deleted', false)->get(); @endphp
            @if ($currentDocuments->isNotEmpty())
                <div class="d-flex flex-wrap mt-1">
                    @foreach ($currentDocuments as $document)
                        <a href="{{ getimg($document->document) }}" target="_blank" class="btn btn-sm btn-outline-primary mr-1 mb-1">
                            {{ __('admin.attributes.license_documents') }} #{{ $loop->iteration }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('preferred language') }} <span class="text-danger">*</span></label>
        @php $currentLang = old('preferred_language', $user->preferred_language?->value ?? $user->preferred_language ?? 'en'); @endphp
        <select name="preferred_language" class="form-control" required>
            <option value="en" {{ $currentLang === 'en' ? 'selected' : '' }}>{{ __('en') }}</option>
            <option value="ar" {{ $currentLang === 'ar' ? 'selected' : '' }}>{{ __('ar') }}</option>
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('password') }} @if (empty($user)) <span class="text-danger">*</span> @endif</label>
        <input type="password" name="password" class="form-control" @if (empty($user)) required @endif autocomplete="new-password">
        @if (! empty($user))
            <small class="text-muted">{{ __('Leave blank to keep current password') }}</small>
        @endif
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('password confirmation') }} @if (empty($user)) <span class="text-danger">*</span> @endif</label>
        <input type="password" name="password_confirmation" class="form-control" @if (empty($user)) required @endif autocomplete="new-password">
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('status') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_active">{{ __('active') }}</label>
        </div>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>

@push('scripts')
<script>
(function () {
    const select = document.getElementById('account_type');
    const orgFields = document.querySelectorAll('.js-org-fields');
    function toggle() {
        const show = select && (select.value === 'organization' || select.value === 'volunteer_team');
        orgFields.forEach((el) => { el.style.display = show ? '' : 'none'; });
    }
    if (select) {
        select.addEventListener('change', toggle);
        toggle();
    }
})();
</script>
@endpush
