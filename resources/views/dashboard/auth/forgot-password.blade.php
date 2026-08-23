@extends('dashboard.auth.simple')

@section('title', __('Forgot password'))
@section('heading', __('Forgot your password?'))

@section('form')
    <p class="auth-hint">{{ __('Enter your admin email and we will send you a reset link.') }}</p>

    <form class="form-horizontal" action="{{ route('admin.password.email') }}" method="post" novalidate>
        @csrf

        <div class="modern-input-group">
            <div class="input-icon-wrap">
                <i class="fas fa-envelope field-icon field-icon-end"></i>
                <input type="email" id="email" class="modern-input" placeholder="{{ __('email') }}"
                       name="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
            </div>
        </div>

        <button type="submit" class="modern-btn submit_button">
            <span>{{ __('Send reset link') }}</span>
            <i class="fas {{ app()->getLocale() === 'ar' ? 'fa-arrow-left' : 'fa-arrow-right' }} btn-arrow"></i>
        </button>
    </form>
@endsection
