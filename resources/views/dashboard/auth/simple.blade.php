{{--
    Shared shell for the dashboard's standalone auth screens (forgot / reset
    password). Mirrors the login page chrome so the flow does not feel bolted on.
--}}
<!DOCTYPE html>
<html class="loading" lang="{{ app()->getLocale() }}" data-textdirection="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forsa | @yield('title')</title>

    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/vendors-rtl.min.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/bootstrap.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/components.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/themes/dark-layout.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/custom-rtl.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/vendors.min.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/bootstrap.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/components.css') }}">
        <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/themes/dark-layout.css') }}">
    @endif

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if (app()->getLocale() === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif
    <link rel="stylesheet" href="{{ asset('_dashboard/assets/css/login.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/login.css')) }}">

    <style>
        .login-logo-icon .forsa-mark {
            display: flex; align-items: center; justify-content: center;
            width: 72px; height: 72px; border-radius: 20px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff; font-size: 34px; font-weight: 800; letter-spacing: 1px;
            box-shadow: 0 12px 30px rgba(124, 58, 237, .45);
        }
        .auth-hint { font-size: 14px; opacity: .8; margin-bottom: 20px; text-align: center; }
        .auth-back { display: block; text-align: center; margin-top: 18px; font-size: 14px; }
    </style>
</head>

<body id="content_body" class="login-page vertical-layout vertical-menu-modern 1-column blank-page dark-layout" data-open="click" data-menu="vertical-menu-modern" data-col="1-column" data-type="dark">

<canvas id="stars-canvas"></canvas>
<div class="cosmic-nebula cosmic-nebula-1"></div>
<div class="cosmic-nebula cosmic-nebula-2"></div>

<div class="login-controls">
    <a class="login-control-btn login-lang-toggle"
       href="{{ route('change-language', ['lang' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}">
        @if (app()->getLocale() === 'ar')
            <img src="{{ asset('_dashboard/assets/flags/svg/us.svg') }}" alt="US">
            <span class="lang-label">{{ __('English') }}</span>
        @else
            <img src="{{ asset('_dashboard/assets/flags/svg/sa.svg') }}" alt="SA">
            <span class="lang-label">{{ __('Arabic') }}</span>
        @endif
    </a>
    <a class="login-control-btn" href="#" id="layout-mode-login" title="{{ __('Theme') }}">
        <i class="ficon feather icon-moon"></i>
    </a>
</div>

<div class="login-page-wrapper">
    <div class="login-card">
        <div class="login-logo-rings">
            <span class="ring ring-1"></span>
            <span class="ring ring-2"></span>
            <span class="ring ring-3"></span>
            <div class="login-logo-icon">
                <span class="forsa-mark">F</span>
            </div>
        </div>

        <div class="login-header">
            <h4>@yield('heading')</h4>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('form')

        <a class="auth-back" href="{{ route('admin.login') }}">{{ __('Back to login') }}</a>
    </div>
</div>

<script src="{{ asset('_dashboard/app-assets/vendors/js/vendors.min.js') }}"></script>
<script src="{{ asset('_dashboard/app-assets/js/scripts/components.js') }}"></script>
<script>
function changeMode() {
    var body = document.getElementById('content_body');
    if (body.dataset.type === 'dark') {
        localStorage.setItem('caberz_currentLayout', 'light');
        body.dataset.type = 'light';
        body.classList.remove('dark-layout');
        body.classList.add('light-mode');
    } else {
        localStorage.setItem('caberz_currentLayout', 'dark');
        body.dataset.type = 'dark';
        body.classList.add('dark-layout');
        body.classList.remove('light-mode');
    }
}
(function () {
    function updateThemeIcon() {
        var isDark = localStorage.getItem('caberz_currentLayout') !== 'light';
        document.querySelector('#layout-mode-login').innerHTML =
            '<i class="ficon feather ' + (isDark ? 'icon-sun' : 'icon-moon') + '"></i>';
    }
    function initTheme() {
        var body = document.getElementById('content_body');
        var stored = localStorage.getItem('caberz_currentLayout');
        if (stored === null) { stored = 'dark'; localStorage.setItem('caberz_currentLayout', 'dark'); }
        if (stored === 'light') {
            body.classList.remove('dark-layout'); body.classList.add('light-mode'); body.dataset.type = 'light';
        } else {
            body.classList.add('dark-layout'); body.classList.remove('light-mode'); body.dataset.type = 'dark';
        }
        updateThemeIcon();
    }
    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        document.getElementById('layout-mode-login').addEventListener('click', function (e) {
            e.preventDefault();
            changeMode();
            updateThemeIcon();
        });
        document.querySelectorAll('.toggle-password').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                var icon = btn.querySelector('i');
                if (!input) return;
                if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
                else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
            });
        });
    });
})();
</script>
</body>
</html>
