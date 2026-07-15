@extends('dashboard.layout.main')
@section('title', __('volunteer details'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('volunteer details') }} - {{ $volunteer->nickname }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('email') }}</label>
                                <p>{{ $volunteer->user?->email }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('nickname') }}</label>
                                <p>{{ $volunteer->nickname }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('occupation') }}</label>
                                <p>{{ $volunteer->occupation }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('experience') }}</label>
                                <p>{{ $volunteer->experience }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('health concerns') }}</label>
                                <p>{{ $volunteer->health_concerns }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('uuid') }}</label>
                                <p>{{ $volunteer->uuid }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('total volunteer hours') }}</label>
                                <p>{{ $volunteer->total_volunteer_hours }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('total opportunities') }}</label>
                                <p>{{ $volunteer->total_opportunities }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('total certificates') }}</label>
                                <p>{{ $volunteer->total_certificates }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('current year hours') }}</label>
                                <p>{{ $volunteer->current_year_hours }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('public') }}</label>
                                <p>
                                    <span class="badge badge-light-{{ $volunteer->is_public ? 'success' : 'secondary' }}">{{ $volunteer->is_public ? __('yes') : __('no') }}</span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('verified') }}</label>
                                <p>
                                    <span class="badge badge-light-{{ $volunteer->is_verified ? 'success' : 'secondary' }}">{{ $volunteer->is_verified ? __('yes') : __('no') }}</span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('badge') }}</label>
                                <p>{{ $volunteer->currentBadge?->name }}</p>
                            </div>
                        </div>
                        <div class="mt-1">
                            <a href="{{ route('admin.volunteers.edit', $volunteer) }}" class="btn btn-warning">{{ __('edit') }}</a>
                            <a href="{{ route('admin.volunteers.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
