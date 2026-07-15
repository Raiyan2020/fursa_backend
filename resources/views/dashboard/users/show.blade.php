@extends('dashboard.layout.main')
@section('title', __('user details'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('user details') }} - {{ $user->first_name . ' ' . $user->last_name }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('email') }}</label>
                                <p>{{ $user->email }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('username') }}</label>
                                <p>{{ $user->username }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('first name') }}</label>
                                <p>{{ $user->first_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('last name') }}</label>
                                <p>{{ $user->last_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('phone') }}</label>
                                <p>{{ $user->phone_number }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('country code') }}</label>
                                <p>{{ $user->country_code }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('civil id') }}</label>
                                <p>{{ $user->civil_id }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('user type') }}</label>
                                <p>{{ $user->user_type?->label() ?? '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('preferred language') }}</label>
                                <p>{{ $user->preferred_language?->value ?? $user->preferred_language }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('date of birth') }}</label>
                                <p>{{ $user->dob }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('nationality') }}</label>
                                <p>{{ $user->nationality?->value ?? $user->nationality }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('status') }}</label>
                                <p>
                                    <span class="badge badge-light-{{ $user->is_banned ? 'danger' : 'success' }}">{{ $user->is_banned ? __('banned') : __('active') }}</span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('instagram link') }}</label>
                                <p>{{ $user->instagram_link }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('whatsapp link') }}</label>
                                <p>{{ $user->whatsapp_link }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('linkedin link') }}</label>
                                <p>{{ $user->linkedin_link }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('facebook link') }}</label>
                                <p>{{ $user->facebook_link }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('twitter link') }}</label>
                                <p>{{ $user->twitter_link }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('emergency contact name') }}</label>
                                <p>{{ $user->emergency_contact_name }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('emergency contact phone') }}</label>
                                <p>{{ $user->emergency_contact_phone }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('emergency contact country code') }}</label>
                                <p>{{ $user->emergency_contact_country_code }}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="font-weight-bold">{{ __('emergency contact civil id') }}</label>
                                <p>{{ $user->emergency_contact_civil_id }}</p>
                            </div>
                        </div>
                        <div class="mt-1">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">{{ __('edit') }}</a>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
