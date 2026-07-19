@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('home_sections') }} - {{ $section->slug }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.home-sections.update', $section) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('title_en') }}</label>
                                <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $section->title_en) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('title_ar') }}</label>
                                <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $section->title_ar) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('description_en') }}</label>
                                <textarea name="description_en" class="form-control" rows="5">{{ old('description_en', $section->description_en) }}</textarea>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('description_ar') }}</label>
                                <textarea name="description_ar" class="form-control" rows="5">{{ old('description_ar', $section->description_ar) }}</textarea>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('sort_order') }}</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $section->sort_order) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('image') }}</label>
                                <input type="file" name="image" class="dropify" data-height="180" accept="image/*"
                                    {{ $section->image ? 'data-default-file='.getimg($section->image) : '' }}>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            <a href="{{ route('admin.home-sections.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
