@extends('dashboard.layout.main')
@section('title', __('entity details'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('entity details') }} - {{ $entity->company_name }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('company name') }}</label>
                                <p>{{ $entity->company_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('nickname') }}</label>
                                <p>{{ $entity->nickname }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('registration number') }}</label>
                                <p>{{ $entity->registration_number }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('license number') }}</label>
                                <p>{{ $entity->license_number }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('sector') }}</label>
                                <p>{{ $entity->sector?->value_en }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('organizer type') }}</label>
                                <p>{{ $entity->organizerType?->value_en }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('status') }}</label>
                                <p>
                                    @include('dashboard.partials.status-badge', ['status' => $entity->organization_status])
                                </p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('rejection reason') }}</label>
                                <p>{{ $entity->rejection_reason }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('email') }}</label>
                                <p>{{ $entity->user?->email }}</p>
                            </div>
                        </div>
                        <div class="mt-1">
                            <a href="{{ route('admin.entities.edit', $entity) }}" class="btn btn-warning">{{ __('edit') }}</a>
                            <a href="{{ route('admin.entities.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
