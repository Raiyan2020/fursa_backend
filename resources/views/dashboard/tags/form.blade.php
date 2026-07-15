<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('choice type') }} <span class="text-danger">*</span></label>
        <select name="choice_type_id" class="form-control" required>
            <option value="">{{ __('select') }}</option>
            @foreach ($choiceTypes as $choiceType)
                <option value="{{ $choiceType->id }}" {{ (string) old('choice_type_id', $tag->choice_type_id ?? '') === (string) $choiceType->id ? 'selected' : '' }}>{{ $choiceType->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('value_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="value_en" class="form-control" value="{{ old('value_en', $tag->value_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('value_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="value_ar" class="form-control" value="{{ old('value_ar', $tag->value_ar ?? '') }}" required>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.tags.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
