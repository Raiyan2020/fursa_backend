<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('question_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="question_en" class="form-control" value="{{ old('question_en', $faq->question_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('question_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="question_ar" class="form-control" value="{{ old('question_ar', $faq->question_ar ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('answer_en') }} <span class="text-danger">*</span></label>
        <textarea name="answer_en" class="form-control" rows="4" required>{{ old('answer_en', $faq->answer_en ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('answer_ar') }} <span class="text-danger">*</span></label>
        <textarea name="answer_ar" class="form-control" rows="4" required>{{ old('answer_ar', $faq->answer_ar ?? '') }}</textarea>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.faqs.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
