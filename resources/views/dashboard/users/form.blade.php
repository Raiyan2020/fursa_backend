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
        <label>{{ __('user type') }} <span class="text-danger">*</span></label>
        <select name="user_type" class="form-control" required>
            @foreach (\App\Enums\UserType::cases() as $type)
                <option value="{{ $type->value }}" {{ old('user_type', $user->user_type?->value ?? '') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
            @endforeach
        </select>
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
