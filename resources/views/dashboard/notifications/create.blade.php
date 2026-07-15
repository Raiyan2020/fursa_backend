@extends('dashboard.layout.main')
@section('title', __('send new'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('notifications') }} - {{ __('send new') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.notifications.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('target') }} <span class="text-danger">*</span></label>
                                <select name="target" class="form-control" required>
                                    <option value="all" {{ old('target') === 'all' ? 'selected' : '' }}>{{ __('all') }}</option>
                                    <option value="volunteers" {{ old('target') === 'volunteers' ? 'selected' : '' }}>{{ __('volunteers') }}</option>
                                    <option value="organizations" {{ old('target') === 'organizations' ? 'selected' : '' }}>{{ __('organizations') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-1"></div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('title (en)') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title_en" class="form-control" value="{{ old('title_en') }}" required>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('title (ar)') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar') }}" required>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('message (en)') }} <span class="text-danger">*</span></label>
                                <textarea name="message_en" class="form-control" rows="5" required>{{ old('message_en') }}</textarea>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('message (ar)') }} <span class="text-danger">*</span></label>
                                <textarea name="message_ar" class="form-control" rows="5" required>{{ old('message_ar') }}</textarea>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('send') }}</button>
                            <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
