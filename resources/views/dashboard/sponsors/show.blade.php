@extends('dashboard.layout.main')
@section('title', __('sponsor details'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('sponsor details') }} - {{ $sponsor->org_name }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('organization name') }}</label>
                                <p>{{ $sponsor->org_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('organization type') }}</label>
                                <p>{{ optional($sponsor->orgType)->value_en }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('sponsor type') }}</label>
                                <p>{{ optional($sponsor->sponsorType)->value_en }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('person name') }}</label>
                                <p>{{ $sponsor->person_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('email') }}</label>
                                <p>{{ $sponsor->email }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('phone') }}</label>
                                <p>{{ $sponsor->country_code . ' ' . $sponsor->phone_number }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('type of support') }}</label>
                                <p>{{ optional($sponsor->typeOfSupport)->value_en }}</p>
                            </div>
                            <div class="col-md-12 mb-1">
                                <label class="font-weight-bold">{{ __('sponsorship details') }}</label>
                                <p>{{ $sponsor->sponsorship_details }}</p>
                            </div>
                            <div class="col-md-12 mb-1">
                                <label class="font-weight-bold">{{ __('why interested') }}</label>
                                <p>{{ $sponsor->why_interested }}</p>
                            </div>
                            <div class="col-md-12 mb-1">
                                <label class="font-weight-bold">{{ __('resources expected') }}</label>
                                <p>{{ $sponsor->resources_expected }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('status') }}</label>
                                <p>@include('dashboard.partials.status-badge', ['status' => $sponsor->approval_status])</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('preferred language') }}</label>
                                <p>{{ $sponsor->preferred_language?->value ?? $sponsor->preferred_language }}</p>
                            </div>
                        </div>
                        <div class="mt-1">
                            <a href="{{ route('admin.sponsors.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
