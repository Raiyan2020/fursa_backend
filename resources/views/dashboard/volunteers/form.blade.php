<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('nickname') }}</label>
        <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $volunteer->nickname ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('occupation') }}</label>
        <input type="text" name="occupation" class="form-control" value="{{ old('occupation', $volunteer->occupation ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('experience') }}</label>
        <textarea name="experience" class="form-control" rows="3">{{ old('experience', $volunteer->experience ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('badge') }}</label>
        <select name="current_badge_id" class="form-control">
            <option value="">{{ __('none') }}</option>
            @foreach ($badges as $badge)
                <option value="{{ $badge->id }}" {{ old('current_badge_id', $volunteer->current_badge_id ?? '') == $badge->id ? 'selected' : '' }}>{{ $badge->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('public') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" value="1" {{ old('is_public', $volunteer->is_public ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_public">{{ __('public') }}</label>
        </div>
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('verified') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="is_verified" name="is_verified" value="1" {{ old('is_verified', $volunteer->is_verified ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_verified">{{ __('verified') }}</label>
        </div>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.volunteers.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
