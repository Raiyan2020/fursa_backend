<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('title_en') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $item->title_en ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('title_ar') }} <span class="text-danger">*</span></label>
        <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $item->title_ar ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('sort_order') }}</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('icon') }}</label>
        <input type="file" name="icon" class="dropify" data-height="120" accept="image/*"
            {{ !empty($item->icon) ? 'data-default-file='.getimg($item->icon) : '' }}>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.why-fursa.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
