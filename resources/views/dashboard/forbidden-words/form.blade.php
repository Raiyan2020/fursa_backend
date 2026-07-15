<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('word_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="word_en" class="form-control" value="{{ old('word_en', $forbiddenWord->word_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('word_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="word_ar" class="form-control" value="{{ old('word_ar', $forbiddenWord->word_ar ?? '') }}" required>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.forbidden-words.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
