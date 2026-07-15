@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('user type approvals') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.user-type-approvals.update', $approval) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('user type') }}</label>
                                <input type="text" class="form-control" value="{{ $approval->user_type?->label() }}" readonly>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="d-block">{{ __('requires approval') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="requires_approval" name="requires_approval" value="1" {{ old('requires_approval', $approval->requires_approval) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="requires_approval">{{ __('yes') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            <a href="{{ route('admin.user-type-approvals.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
