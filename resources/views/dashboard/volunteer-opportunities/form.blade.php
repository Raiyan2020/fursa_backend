@php
    $locale = app()->getLocale();
    $choiceLabel = fn ($choice) => $choice
        ? ($locale === 'ar' ? ($choice->value_ar ?: $choice->value_en) : ($choice->value_en ?: $choice->value_ar))
        : '';
    $opportunityValue = function ($field, $fallback = null) use ($opportunity) {
        $value = old($field, optional($opportunity)->{$field} ?? $fallback);
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
    $selected = fn ($field, $fallback = null) => (string) ($opportunityValue($field, $fallback) ?? '');
    $invalid = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
    $selectedInterests = collect(old('interest_ids', optional($opportunity)->interests?->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id);
    $checked = fn (string $field, $fallback = false) => old($field, optional($opportunity)->{$field} ?? $fallback) ? 'checked' : '';
    $today = now()->format('Y-m-d');
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

{{-- Card 1: Organization & Basic Info --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('basic information') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-1">
                <label>{{ __('organization') }} <span class="text-danger">*</span></label>
                <select name="created_by" class="form-control{{ $invalid('created_by') }}" required>
                    <option value="">{{ __('select') }}</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->user_id }}" {{ $selected('created_by') === (string) $org->user_id ? 'selected' : '' }}>
                            {{ $org->company_name ?: ($org->nickname ?: '#'.$org->id) }}
                        </option>
                    @endforeach
                    @if (! empty(optional($opportunity)->created_by) && ! $organizations->contains('user_id', $opportunity->created_by))
                        <option value="{{ $opportunity->created_by }}" selected>
                            {{ optional($opportunity->creator)->email ?: '#'.$opportunity->created_by }}
                        </option>
                    @endif
                </select>
                @error('created_by') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-1">
                <label>{{ __('title_en') }} <span class="text-danger">*</span></label>
                <input type="text" name="title_en" class="form-control{{ $invalid('title_en') }}" value="{{ $opportunityValue('title_en') }}" required>
                @error('title_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('title_ar') }} <span class="text-danger">*</span></label>
                <input type="text" name="title_ar" class="form-control{{ $invalid('title_ar') }}" value="{{ $opportunityValue('title_ar') }}" required>
                @error('title_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-1">
                <label>{{ __('description_en') }} <span class="text-danger">*</span></label>
                <textarea name="description_en" class="form-control{{ $invalid('description_en') }}" rows="5" required>{{ $opportunityValue('description_en') }}</textarea>
                @error('description_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('description_ar') }} <span class="text-danger">*</span></label>
                <textarea name="description_ar" class="form-control{{ $invalid('description_ar') }}" rows="5" required>{{ $opportunityValue('description_ar') }}</textarea>
                @error('description_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Card 2: Date & Time --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('date and time') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-1">
                <label>{{ __('start date') }} <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="start_date"
                    class="form-control{{ $invalid('start_date') }}"
                    value="{{ $opportunityValue('start_date') }}"
                    min="{{ $today }}" required>
                @error('start_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('end date') }} <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="end_date"
                    class="form-control{{ $invalid('end_date') }}"
                    value="{{ $opportunityValue('end_date') }}"
                    min="{{ $today }}" required>
                @error('end_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('start time') }}</label>
                <input type="time" name="start_time" class="form-control{{ $invalid('start_time') }}" value="{{ $opportunityValue('start_time') }}">
                @error('start_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('end time') }}</label>
                <input type="time" name="end_time" class="form-control{{ $invalid('end_time') }}" value="{{ $opportunityValue('end_time') }}">
                @error('end_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('due date') }}</label>
                <input type="date" name="due_date"
                    class="form-control{{ $invalid('due_date') }}"
                    value="{{ $opportunityValue('due_date') }}"
                    min="{{ $today }}">
                @error('due_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Card 3: Location --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('location') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-1">
                <label>{{ __('location_en') }}</label>
                <input type="text" name="location_en" class="form-control{{ $invalid('location_en') }}" value="{{ $opportunityValue('location_en') }}">
                @error('location_en') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('location_ar') }}</label>
                <input type="text" name="location_ar" class="form-control{{ $invalid('location_ar') }}" value="{{ $opportunityValue('location_ar') }}">
                @error('location_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('latitude') }}</label>
                <input type="number" step="any" name="latitude" class="form-control{{ $invalid('latitude') }}" value="{{ $opportunityValue('latitude') }}">
                @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('longitude') }}</label>
                <input type="number" step="any" name="longitude" class="form-control{{ $invalid('longitude') }}" value="{{ $opportunityValue('longitude') }}">
                @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Card 4: Participant Requirements --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('participant requirements') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-1">
                <label>{{ __('from age') }}</label>
                <input type="number" min="0" name="from_age" class="form-control{{ $invalid('from_age') }}" value="{{ $opportunityValue('from_age') }}">
                @error('from_age') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('to age') }}</label>
                <input type="number" min="0" name="to_age" class="form-control{{ $invalid('to_age') }}" value="{{ $opportunityValue('to_age') }}">
                @error('to_age') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
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
            <div class="col-md-3 mb-1">
                <label>{{ __('opportunity nationality') }}</label>
                <select name="opportunity_nationality" class="form-control{{ $invalid('opportunity_nationality') }}">
                    <option value="">{{ __('select') }}</option>
                    @foreach (\App\Enums\Nationality::cases() as $nat)
                        <option value="{{ $nat->value }}" {{ $selected('opportunity_nationality') === $nat->value ? 'selected' : '' }}>
                            {{ __($nat->value) }}
                        </option>
                    @endforeach
                </select>
                @error('opportunity_nationality') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-1">
                <label>{{ __('participants needed') }} <span class="text-danger">*</span></label>
                <input type="number" min="1" name="participants_needed" class="form-control{{ $invalid('participants_needed') }}" value="{{ $opportunityValue('participants_needed', 1) }}" required>
                @error('participants_needed') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
            <div class="col-md-5 mb-1">
                <label>{{ __('volunteer hours per day') }}</label>
                <input type="number" step="0.1" min="0" name="volunteer_hours_per_day" class="form-control{{ $invalid('volunteer_hours_per_day') }}" value="{{ $opportunityValue('volunteer_hours_per_day') }}">
                @error('volunteer_hours_per_day') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-12 mb-1">
                <label>{{ __('link') }}</label>
                <input type="text" name="link" class="form-control{{ $invalid('link') }}" value="{{ $opportunityValue('link') }}" placeholder="https://">
                @error('link') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Card 5: Status & Interests --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('status and classification') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-1">
                <label>{{ __('approval status') }} <span class="text-danger">*</span></label>
                <select name="approval_status" class="form-control{{ $invalid('approval_status') }}" required>
                    @foreach (\App\Enums\ApprovalStatus::cases() as $case)
                        <option value="{{ $case->value }}" {{ $selected('approval_status', 'approved') === $case->value ? 'selected' : '' }}>
                            {{ $case->label() }}
                        </option>
                    @endforeach
                </select>
                @error('approval_status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-1">
                <label>{{ __('opportunity status') }} <span class="text-danger">*</span></label>
                <select name="opportunity_status" class="form-control{{ $invalid('opportunity_status') }}" required>
                    @foreach (\App\Enums\OpportunityStatus::cases() as $case)
                        <option value="{{ $case->value }}" {{ $selected('opportunity_status', 'upcoming') === $case->value ? 'selected' : '' }}>
                            {{ $case->label() }}
                        </option>
                    @endforeach
                </select>
                @error('opportunity_status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
                @error('interest_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Card 6: Flags --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('options') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is public') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" value="1" {{ $checked('is_public', true) }}>
                    <label class="custom-control-label" for="is_public">{{ __('public') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is calendar') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_calendar" name="is_calendar" value="1" {{ $checked('is_calendar') }}>
                    <label class="custom-control-label" for="is_calendar">{{ __('is calendar') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is kuwaitis') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_kuwaitis" name="is_kuwaitis" value="1" {{ $checked('is_kuwaitis') }}>
                    <label class="custom-control-label" for="is_kuwaitis">{{ __('is kuwaitis') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is relief') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_relief" name="is_relief" value="1" {{ $checked('is_relief') }}>
                    <label class="custom-control-label" for="is_relief">{{ __('is relief') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is interview needed') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_interview_needed" name="is_interview_needed" value="1" {{ $checked('is_interview_needed') }}>
                    <label class="custom-control-label" for="is_interview_needed">{{ __('is interview needed') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is urgent') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_urgent" name="is_urgent" value="1" {{ $checked('is_urgent') }}>
                    <label class="custom-control-label" for="is_urgent">{{ __('is urgent') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-1">
                <label class="d-block">{{ __('is supports disabled') }}</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_supports_disabled" name="is_supports_disabled" value="1" {{ $checked('is_supports_disabled') }}>
                    <label class="custom-control-label" for="is_supports_disabled">{{ __('is supports disabled') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Card 7: Images --}}
<div class="card border mb-2">
    <div class="card-header py-1">
        <h5 class="card-title mb-0">{{ __('images') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-1">
                <label>{{ __('license image') }}</label>
                <input type="file" name="license_image" class="form-control{{ $invalid('license_image') }}" accept="image/*">
                @error('license_image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @if (! empty(optional($opportunity)->license_image))
                    <div class="mt-1">
                        <img src="{{ getimg($opportunity->license_image) }}" alt="" style="max-height:90px;border-radius:8px;">
                    </div>
                @endif
            </div>
            <div class="col-md-4 mb-1">
                <label>{{ __('announcement images') }}</label>
                <input type="file" name="images[]" class="form-control{{ $invalid('images') }}" accept="image/*" multiple>
                <small class="text-muted">{{ __('Shown on the opportunity card') }}</small>
                @error('images') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-1">
                <label>{{ __('after completed images') }}</label>
                <input type="file" name="after_images[]" class="form-control{{ $invalid('after_images') }}" accept="image/*" multiple>
                <small class="text-muted">{{ __('Gallery inside the opportunity after it ends') }}</small>
                @error('after_images') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @error('after_images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            @php
                $currentImages = optional($opportunity)->images?->where('is_deleted', false) ?? collect();
                $announcementImages = $currentImages->where('is_after_completed', false);
                $afterImages = $currentImages->where('is_after_completed', true);
            @endphp

            @if ($announcementImages->isNotEmpty())
                <div class="col-md-12 mb-1">
                    <label>{{ __('current announcement images') }}</label>
                    <div class="d-flex flex-wrap">
                        @foreach ($announcementImages as $image)
                            <div class="mr-2 mb-2 text-center">
                                <img src="{{ getimg($image->image) }}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                                @include('dashboard.partials.nested-delete-form', [
                                    'formId' => 'delete-volunteer-image-'.$image->id,
                                    'action' => route('admin.volunteer-opportunities.images.destroy', [$opportunity, $image]),
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($afterImages->isNotEmpty())
                <div class="col-md-12 mb-1">
                    <label>{{ __('current after completed images') }}</label>
                    <div class="d-flex flex-wrap">
                        @foreach ($afterImages as $image)
                            <div class="mr-2 mb-2 text-center">
                                <img src="{{ getimg($image->image) }}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                                @include('dashboard.partials.nested-delete-form', [
                                    'formId' => 'delete-volunteer-after-image-'.$image->id,
                                    'action' => route('admin.volunteer-opportunities.images.destroy', [$opportunity, $image]),
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.volunteer-opportunities.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>

<script>
    // Keep end_date min always >= start_date
    document.addEventListener('DOMContentLoaded', function () {
        var startDate = document.getElementById('start_date');
        var endDate = document.getElementById('end_date');
        if (startDate && endDate) {
            startDate.addEventListener('change', function () {
                if (this.value) {
                    endDate.min = this.value;
                    if (endDate.value && endDate.value < this.value) {
                        endDate.value = this.value;
                    }
                }
            });
        }
    });
</script>
