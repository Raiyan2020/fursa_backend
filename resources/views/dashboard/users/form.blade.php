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
    <div class="col-md-6 mb-1">
        <label>{{ __('user type') }} <span class="text-danger">*</span></label>
        <select name="account_type" id="account_type" class="form-control" required>
            <option value="volunteer" {{ $accountType === 'volunteer' ? 'selected' : '' }}>{{ __('admin.user_types.volunteer') }}</option>
            <option value="organization" {{ $accountType === 'organization' ? 'selected' : '' }}>{{ __('admin.user_types.organization') }}</option>
            <option value="volunteer_team" {{ $accountType === 'volunteer_team' ? 'selected' : '' }}>{{ __('admin.user_types.volunteer_team') }}</option>
            <option value="admin" {{ $accountType === 'admin' ? 'selected' : '' }}>{{ __('admin.user_types.admin') }}</option>
        </select>
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('company name') }}</label>
        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $user->organizationProfile->company_name ?? '') }}">
    </div>
    <div class="col-md-6 mb-1 js-org-fields">
        <label>{{ __('nickname') }}</label>
        <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $user->organizationProfile->nickname ?? $user->volunteerProfile->nickname ?? '') }}">
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
