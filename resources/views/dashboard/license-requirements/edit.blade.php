@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('license requirements') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.license-requirements.update', $requirement) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('user role') }}</label>
                                <input type="text" class="form-control" value="{{ $requirement->user_role }}" readonly>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="d-block">{{ __('license required') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="license_required" name="license_required" value="1" {{ old('license_required', $requirement->license_required) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="license_required">{{ __('yes') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            <a href="{{ route('admin.license-requirements.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
