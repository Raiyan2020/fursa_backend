@php
    $locale = app()->getLocale();
    $choiceLabel = fn ($choice) => $choice
        ? ($locale === 'ar' ? ($choice->value_ar ?: $choice->value_en) : ($choice->value_en ?: $choice->value_ar))
        : '';
    $eventValue = function ($field, $fallback = null) use ($event) {
        $value = old($field, optional($event)->{$field} ?? $fallback);
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return str_ends_with($field, '_time')
                ? $value->format('H:i')
                : $value->format('Y-m-d');
        }
        if (is_string($value) && str_ends_with($field, '_time')) {
            return substr($value, 0, 5);
        }

        return $value;
    };
    $selected = fn ($field, $fallback = null) => (string) ($eventValue($field, $fallback) ?? '');
    $invalid = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
    $selectedInterests = collect(old('interest_ids', optional($event)->interests?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id);
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
    <div class="col-md-12 mb-1">
        <label>{{ __('organization') }} <span class="text-danger">*</span></label>
        <select name="created_by" class="form-control{{ $invalid('created_by') }}" required>
            <option value="">{{ __('select') }}</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" {{ $selected('created_by') === (string) $org->id ? 'selected' : '' }}>
                    {{ $org->company_name ?: ($org->nickname ?: '#'.$org->id) }}
                </option>
            @endforeach
        </select>
        @error('created_by') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-1">
        <label>{{ __('title_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_en" class="form-control{{ $invalid('title_en') }}" value="{{ $eventValue('title_en') }}" required>
        @error('title_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_ar" class="form-control{{ $invalid('title_ar') }}" value="{{ $eventValue('title_ar') }}" required>
        @error('title_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-1">
        <label>{{ __('description_en') }} <span class="text-danger">*</span></label>
        <textarea name="description_en" class="form-control{{ $invalid('description_en') }}" rows="5" required>{{ $eventValue('description_en') }}</textarea>
        @error('description_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('description_ar') }} <span class="text-danger">*</span></label>
        <textarea name="description_ar" class="form-control{{ $invalid('description_ar') }}" rows="5" required>{{ $eventValue('description_ar') }}</textarea>
        @error('description_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-1">
        <label>{{ __('event type') }}</label>
        <select name="event_type_id" class="form-control{{ $invalid('event_type_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($eventTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('event_type_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('event_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('gender') }}</label>
        <select name="gender_id" class="form-control{{ $invalid('gender_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($genders as $type)
                <option value="{{ $type->id }}" {{ $selected('gender_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('gender_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('preferred language') }}</label>
        <select name="primary_language" class="form-control{{ $invalid('primary_language') }}">
            @foreach (\App\Enums\Language::cases() as $lang)
                <option value="{{ $lang->value }}" {{ $selected('primary_language', 'en') === $lang->value ? 'selected' : '' }}>
                    {{ strtoupper($lang->value) }}
                </option>
            @endforeach
        </select>
        @error('primary_language') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 mb-1">
        <label>{{ __('start date') }} <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control{{ $invalid('start_date') }}" value="{{ $eventValue('start_date') }}" required>
        @error('start_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('end date') }} <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control{{ $invalid('end_date') }}" value="{{ $eventValue('end_date') }}" required>
        @error('end_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('start time') }}</label>
        <input type="time" name="start_time" class="form-control{{ $invalid('start_time') }}" value="{{ $eventValue('start_time') }}">
        @error('start_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('end time') }}</label>
        <input type="time" name="end_time" class="form-control{{ $invalid('end_time') }}" value="{{ $eventValue('end_time') }}">
        @error('end_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-1">
        <label>{{ __('location_en') }}</label>
        <input type="text" name="location_en" class="form-control{{ $invalid('location_en') }}" value="{{ $eventValue('location_en') }}">
        @error('location_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('location_ar') }}</label>
        <input type="text" name="location_ar" class="form-control{{ $invalid('location_ar') }}" value="{{ $eventValue('location_ar') }}">
        @error('location_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('latitude') }}</label>
        <input type="number" step="any" name="latitude" class="form-control{{ $invalid('latitude') }}" value="{{ $eventValue('latitude') }}">
        @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('longitude') }}</label>
        <input type="number" step="any" name="longitude" class="form-control{{ $invalid('longitude') }}" value="{{ $eventValue('longitude') }}">
        @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 mb-1">
        <label>{{ __('from age') }}</label>
        <input type="number" min="0" name="from_age" class="form-control{{ $invalid('from_age') }}" value="{{ $eventValue('from_age') }}">
        @error('from_age') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('to age') }}</label>
        <input type="number" min="0" name="to_age" class="form-control{{ $invalid('to_age') }}" value="{{ $eventValue('to_age') }}">
        @error('to_age') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('participants needed') }}</label>
        <input type="number" min="0" name="participants_needed" class="form-control{{ $invalid('participants_needed') }}" value="{{ $eventValue('participants_needed', 0) }}">
        @error('participants_needed') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-1">
        <label>{{ __('due date') }}</label>
        <input type="date" name="due_date" class="form-control{{ $invalid('due_date') }}" value="{{ $eventValue('due_date') }}">
        @error('due_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-1">
        <label>{{ __('attendance type') }}</label>
        <select name="attendance_type_id" class="form-control{{ $invalid('attendance_type_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($attendanceTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('attendance_type_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('attendance_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('participation type') }}</label>
        <select name="participation_type_id" class="form-control{{ $invalid('participation_type_id') }}">
            <option value="">{{ __('select') }}</option>
            @foreach ($participationTypes as $type)
                <option value="{{ $type->id }}" {{ $selected('participation_type_id') === (string) $type->id ? 'selected' : '' }}>
                    {{ $choiceLabel($type) }}
                </option>
            @endforeach
        </select>
        @error('participation_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-1">
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
    <div class="col-md-4 mb-1">
        <label>{{ __('event status') }} <span class="text-danger">*</span></label>
        <select name="event_status" class="form-control{{ $invalid('event_status') }}" required>
            @foreach (\App\Enums\OpportunityStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ $selected('event_status', 'upcoming') === $status->value ? 'selected' : '' }}>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('event_status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1">
        <label>{{ __('registration link') }}</label>
        <input type="url" name="registration_link" class="form-control{{ $invalid('registration_link') }}" value="{{ $eventValue('registration_link') }}">
        @error('registration_link') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-1">
        <label>{{ __('registration fee') }}</label>
        <input type="number" step="0.01" min="0" name="registration_fee" class="form-control{{ $invalid('registration_fee') }}" value="{{ $eventValue('registration_fee') }}">
        @error('registration_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-1 d-flex align-items-center">
        <div class="custom-control custom-switch mt-2">
            <input type="checkbox" class="custom-control-input" id="registration_required" name="registration_required" value="1"
                {{ old('registration_required', optional($event)->registration_required) ? 'checked' : '' }}>
            <label class="custom-control-label" for="registration_required">{{ __('registration required') }}</label>
        </div>
    </div>
    <div class="col-md-4 mb-1 d-flex align-items-center">
        <div class="custom-control custom-switch mt-2">
            <input type="checkbox" class="custom-control-input" id="paid_registration" name="paid_registration" value="1"
                {{ old('paid_registration', optional($event)->paid_registration) ? 'checked' : '' }}>
            <label class="custom-control-label" for="paid_registration">{{ __('paid registration') }}</label>
        </div>
    </div>

    <div class="col-md-12 mb-1">
        <label>{{ __('interests') }}</label>
        <select name="interest_ids[]" class="form-control{{ $invalid('interest_ids') }}" multiple size="6">
            @foreach ($interests as $interest)
                <option value="{{ $interest->id }}" {{ $selectedInterests->contains((string) $interest->id) ? 'selected' : '' }}>
                    {{ $locale === 'ar' ? ($interest->name_ar ?: $interest->name_en) : ($interest->name_en ?: $interest->name_ar) }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">{{ __('Hold Ctrl to select multiple') }}</small>
        @error('interest_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('interest_ids.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-12 mb-1">
        <label>{{ __('images') }}</label>
        <input type="file" name="images[]" class="form-control{{ $invalid('images') }}" accept="image/*" multiple>
        <small class="text-muted">{{ __('Event banner / gallery images') }}</small>
        @error('images') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    @if (! empty($event) && $event->images && $event->images->where('is_deleted', false)->isNotEmpty())
        <div class="col-md-12 mb-2">
            <label>{{ __('current images') }}</label>
            <div class="d-flex flex-wrap">
                @foreach ($event->images->where('is_deleted', false) as $image)
                    <div class="mr-2 mb-2 text-center">
                        <img src="{{ getimg($image->image) }}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                        <div class="mt-1">
                            <a class="btn btn-sm btn-danger" data-href="{{ route('admin.events.images.destroy', [$event, $image]) }}" onclick="delete_form(this)">
                                <i class="feather icon-trash"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
