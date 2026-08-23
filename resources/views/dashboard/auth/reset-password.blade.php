@extends('dashboard.auth.simple')

@section('title', __('Reset password'))
@section('heading', __('Set a new password'))

@section('form')
    <form class="form-horizontal" action="{{ route('admin.password.update') }}" method="post" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="modern-input-group">
            <div class="input-icon-wrap">
                <i class="fas fa-envelope field-icon field-icon-end"></i>
                <input type="email" id="email" class="modern-input" placeholder="{{ __('email') }}"
                       name="email" value="{{ old('email', $email) }}" required autocomplete="username">
            </div>
        </div>

        <div class="modern-input-group">
            <div class="input-icon-wrap">
                <i class="fas fa-lock field-icon field-icon-end"></i>
                <button type="button" class="field-icon field-icon-start toggle-password"
                        data-target="password" aria-label="{{ __('Toggle password') }}">
                    <i class="fas fa-eye"></i>
                </button>
                <input type="password" id="password" class="modern-input" name="password"
                       placeholder="{{ __('New password') }}" required autocomplete="new-password" autofocus>
            </div>
        </div>

        <div class="modern-input-group">
            <div class="input-icon-wrap">
                <i class="fas fa-lock field-icon field-icon-end"></i>
                <button type="button" class="field-icon field-icon-start toggle-password"
                        data-target="password_confirmation" aria-label="{{ __('Toggle password') }}">
                    <i class="fas fa-eye"></i>
                </button>
                <input type="password" id="password_confirmation" class="modern-input" name="password_confirmation"
                       placeholder="{{ __('Confirm new password') }}" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="modern-btn submit_button">
            <span>{{ __('Reset password') }}</span>
            <i class="fas {{ app()->getLocale() === 'ar' ? 'fa-arrow-left' : 'fa-arrow-right' }} btn-arrow"></i>
        </button>
    </form>
@endsection
