<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $badge->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('priority') }}</label>
        <input type="number" name="priority" class="form-control" value="{{ old('priority', $badge->priority ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('min_hours') }}</label>
        <input type="number" step="0.1" name="min_hours" class="form-control" value="{{ old('min_hours', $badge->min_hours ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('max_hours') }}</label>
        <input type="number" step="0.1" name="max_hours" class="form-control" value="{{ old('max_hours', $badge->max_hours ?? '') }}">
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('description') }}</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $badge->description ?? '') }}</textarea>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.badges.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
