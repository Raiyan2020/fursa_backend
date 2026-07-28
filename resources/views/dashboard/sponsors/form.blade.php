@php
    $locale = app()->getLocale();
    $choiceLabel = fn ($choice) => $choice
        ? ($locale === 'ar' ? ($choice->value_ar ?: $choice->value_en) : ($choice->value_en ?: $choice->value_ar))
        : '';
    $sponsorValue = function ($field, $fallback = null) use ($sponsor) {
        $value = old($field, optional($sponsor)->{$field} ?? $fallback);
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    };
    $selected = fn ($field, $fallback = null) => (string) ($sponsorValue($field, $fallback) ?? '');
    $invalid = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
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
        <label>{{ __('organization name') }} <span class="text-danger">*</span></label>
        <input type="text" name="org_name" class="form-control{{ $invalid('org_name') }}" value="{{ $sponsorValue('org_name') }}" required>
        @error('org_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('person name') }} <span class="text-danger">*</span></label>
        <input type="text" name="person_name" class="form-control{{ $invalid('person_name') }}" value="{{ $sponsorValue('person_name') }}" required>
        @error('person_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('email') }} <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control{{ $invalid('email') }}" value="{{ $sponsorValue('email') }}" required>
        @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('country_code') }}</label>
        <input type="text" name="country_code" class="form-control{{ $invalid('country_code') }}" value="{{ $sponsorValue('country_code') }}" placeholder="+965" maxlength="5">
        @error('country_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('phone') }}</label>
        <input type="text" name="phone_number" class="form-control{{ $invalid('phone_number') }}" value="{{ $sponsorValue('phone_number') }}" maxlength="20">
        @error('phone_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('sponsor type') }}</label>
        <select name="sponsor_type_id" class="form-control{{ $invalid('sponsor_type_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($sponsorTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('sponsor_type_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('sponsor_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('organization type') }}</label>
        <select name="org_type_id" class="form-control{{ $invalid('org_type_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($orgTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('org_type_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('org_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('type of support') }}</label>
        <select name="type_of_support_id" class="form-control{{ $invalid('type_of_support_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($supportTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('type_of_support_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('type_of_support_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('preferred language') }}</label>
        <select name="preferred_language" class="form-control{{ $invalid('preferred_language') }}">
            @foreach (\App\Enums\Language::cases() as $lang)
                <option value="{{ $lang->value }}" {{ $selected('preferred_language', 'en') === $lang->value ? 'selected' : '' }}>
                    {{ strtoupper($lang->value) }}
                </option>
            @endforeach
        </select>
        @error('preferred_language') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('status') }} <span class="text-danger">*</span></label>
        <select name="approval_status" class="form-control{{ $invalid('approval_status') }}" required>
            @foreach (\App\Enums\ApprovalStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ $selected('approval_status', 'approved') === $status->value ? 'selected' : '' }}>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('approval_status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('sponsorship details') }}</label>
        <textarea name="sponsorship_details" class="form-control{{ $invalid('sponsorship_details') }}" rows="3">{{ $sponsorValue('sponsorship_details') }}</textarea>
        @error('sponsorship_details') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('why interested') }}</label>
        <textarea name="why_interested" class="form-control{{ $invalid('why_interested') }}" rows="3">{{ $sponsorValue('why_interested') }}</textarea>
        @error('why_interested') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('resources expected') }}</label>
        <textarea name="resources_expected" class="form-control{{ $invalid('resources_expected') }}" rows="3">{{ $sponsorValue('resources_expected') }}</textarea>
        @error('resources_expected') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('logo') }}</label>
        <input type="file" name="sponsor_logo" class="dropify{{ $invalid('sponsor_logo') }}" data-height="200" accept="image/*"
            {{ ! empty(optional($sponsor)->sponsor_logo) ? 'data-default-file='.getimg($sponsor->sponsor_logo) : '' }}>
        <small class="text-muted">{{ __('Appears on the website sponsors section when approved') }}</small>
        @error('sponsor_logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.sponsors.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
