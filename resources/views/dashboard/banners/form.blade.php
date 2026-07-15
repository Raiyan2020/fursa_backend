<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $bannerImage->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('banner_url') }}</label>
        <input type="url" name="banner_url" class="form-control" value="{{ old('banner_url', $bannerImage->banner_url ?? '') }}">
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('image') }}</label>
        <input type="file" name="image" class="dropify" data-height="200" accept="image/*"
            {{ !empty($bannerImage->image) ? 'data-default-file='.$bannerImage->image : '' }}>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
