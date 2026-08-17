@php
    $startDate = old('start_date', optional(optional($bannerImage ?? null)->start_date)->format('Y-m-d'));
    $endDate = old('end_date', optional(optional($bannerImage ?? null)->end_date)->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $bannerImage->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('banner_url') }}</label>
        <input type="url" name="banner_url" class="form-control" value="{{ old('banner_url', $bannerImage->banner_url ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('start date') }}</label>
        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
        <small class="text-muted">{{ __('Leave empty to show immediately') }}</small>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('end date') }}</label>
        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
        <small class="text-muted">{{ __('Leave empty to keep showing with no end date') }}</small>
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('image') }} @if (empty(optional($bannerImage ?? null)->image))<span class="text-danger">*</span>@endif</label>
        <input type="file" name="image" class="dropify{{ $errors->has('image') ? ' is-invalid' : '' }}"
            data-height="200"
            accept="image/*"
            data-max-file-size="1M"
            {{ ! empty(optional($bannerImage ?? null)->image) ? 'data-default-file='.getimg($bannerImage->image) : '' }}>
        <small class="text-muted d-block">{{ __('Maximum slider image size is 1 MB') }}</small>
        @error('image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
