@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('email templates') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.email-templates.update', $template) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('name') }}</label>
                                <input type="text" class="form-control" value="{{ $template->name }}" readonly>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('language') }}</label>
                                <input type="text" class="form-control" value="{{ $template->language }}" readonly>
                            </div>
                            <div class="col-md-12 mb-1">
                                <label>{{ __('subject') }} <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject) }}" required>
                            </div>
                            <div class="col-md-12 mb-1">
                                <label>{{ __('content') }}</label>
                                <textarea id="ckeditor" name="content" class="form-control" rows="10">{{ old('content', $template->content) }}</textarea>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            <a href="{{ route('admin.email-templates.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
