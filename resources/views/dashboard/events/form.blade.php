<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('title') }} ({{ __('en') }}) <span class="text-danger">*</span></label>
        <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $event->title_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title') }} ({{ __('ar') }}) <span class="text-danger">*</span></label>
        <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $event->title_ar ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('description') }} ({{ __('en') }}) <span class="text-danger">*</span></label>
        <textarea name="description_en" class="form-control" rows="4" required>{{ old('description_en', $event->description_en ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('description') }} ({{ __('ar') }}) <span class="text-danger">*</span></label>
        <textarea name="description_ar" class="form-control" rows="4" required>{{ old('description_ar', $event->description_ar ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('start date') }} <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($event->start_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('end date') }} <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($event->end_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('participants needed') }} <span class="text-danger">*</span></label>
        <input type="number" name="participants_needed" class="form-control" min="1" value="{{ old('participants_needed', $event->participants_needed ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('event status') }} <span class="text-danger">*</span></label>
        <select name="event_status" class="form-control" required>
            @foreach (\App\Enums\OpportunityStatus::cases() as $case)
                <option value="{{ $case->value }}" {{ old('event_status', ($event->event_status->value ?? '')) === $case->value ? 'selected' : '' }}>
                    {{ $case->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('registration required') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="registration_required" name="registration_required" value="1" {{ old('registration_required', $event->registration_required ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="registration_required">{{ __('required') }}</label>
        </div>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
