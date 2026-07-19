<div class="row">
    <div class="col-md-12 mb-1">
        <label>{{ __('slug') }} <span class="text-danger">*</span></label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $page->slug ?? '') }}" required placeholder="about, privacy, terms">
        <small class="text-muted">{{ __('slug_hint') }}</small>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $page->title_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $page->title_ar ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('content_en') }}</label>
        <textarea name="content_en" class="form-control" rows="10">{{ old('content_en', $page->content_en ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('content_ar') }}</label>
        <textarea name="content_ar" class="form-control" rows="10">{{ old('content_ar', $page->content_ar ?? '') }}</textarea>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.pages.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
