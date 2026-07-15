<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('title') }} ({{ __('en') }}) <span class="text-danger">*</span></label>
        <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $opportunity->title_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title') }} ({{ __('ar') }}) <span class="text-danger">*</span></label>
        <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $opportunity->title_ar ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('description') }} ({{ __('en') }}) <span class="text-danger">*</span></label>
        <textarea name="description_en" class="form-control" rows="4" required>{{ old('description_en', $opportunity->description_en ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('description') }} ({{ __('ar') }}) <span class="text-danger">*</span></label>
        <textarea name="description_ar" class="form-control" rows="4" required>{{ old('description_ar', $opportunity->description_ar ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('start date') }} <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($opportunity->start_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('end date') }} <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($opportunity->end_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('participants needed') }} <span class="text-danger">*</span></label>
        <input type="number" name="participants_needed" class="form-control" min="1" value="{{ old('participants_needed', $opportunity->participants_needed ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('opportunity status') }} <span class="text-danger">*</span></label>
        <select name="opportunity_status" class="form-control" required>
            @foreach (\App\Enums\OpportunityStatus::cases() as $case)
                <option value="{{ $case->value }}" {{ old('opportunity_status', ($opportunity->opportunity_status->value ?? '')) === $case->value ? 'selected' : '' }}>
                    {{ $case->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('is public') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" value="1" {{ old('is_public', $opportunity->is_public ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_public">{{ __('public') }}</label>
        </div>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.volunteer-opportunities.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
