<!DOCTYPE html>
<html class="loading" lang="{{ app()->getLocale() }}" data-textdirection="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forsa | {{ __('login') }}</title>

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
    </style>

    @php $loginWelcome = __('Welcome to Forsa control panel') . ' 👋'; @endphp
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
            <h4>{{ $loginWelcome }}</h4>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="login-form" class="form-horizontal" action="{{ route('admin.login') }}" method="post" novalidate>
            @csrf

            <div class="modern-input-group">
                <div class="input-icon-wrap">
                    <i class="fas fa-envelope field-icon field-icon-end"></i>
                    <input type="email" id="email" class="modern-input" placeholder="{{ __('email') }}" name="email" value="{{ old('email') }}" required>
                </div>
            </div>

            <div class="modern-input-group">
                <div class="input-icon-wrap">
                    <i class="fas fa-lock field-icon field-icon-end"></i>
                    <button type="button" class="field-icon field-icon-start toggle-password" aria-label="Toggle password">
                        <i class="fas fa-eye" id="toggle-password-icon"></i>
                    </button>
                    <input type="password" id="password" class="modern-input" name="password" placeholder="{{ __('password') }}" required>
                </div>
            </div>

            <button type="submit" class="modern-btn submit_button">
                <span>{{ __('login') }}</span>
                <i class="fas {{ app()->getLocale() === 'ar' ? 'fa-arrow-left' : 'fa-arrow-right' }} btn-arrow"></i>
            </button>
        </form>
    </div>
</div>

<script src="{{ asset('_dashboard/app-assets/vendors/js/vendors.min.js') }}"></script>
<script src="{{ asset('_dashboard/app-assets/js/scripts/components.js') }}"></script>
<script>
function changeMode() {
    var body = document.getElementById('content_body');
    var layoutOptions = body.dataset.type;
    if (layoutOptions === 'dark') {
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
    function updateLoginThemeIcon() {
        var isDark = localStorage.getItem('caberz_currentLayout') !== 'light';
        var iconClass = isDark ? 'icon-sun' : 'icon-moon';
        document.querySelector('#layout-mode-login').innerHTML = '<i class="ficon feather ' + iconClass + '"></i>';
    }
    function initLoginTheme() {
        var body = document.getElementById('content_body');
        var stored = localStorage.getItem('caberz_currentLayout');
        if (stored === null) { stored = 'dark'; localStorage.setItem('caberz_currentLayout', 'dark'); }
        if (stored === 'light') {
            body.classList.remove('dark-layout'); body.classList.add('light-mode'); body.dataset.type = 'light';
        } else {
            body.classList.add('dark-layout'); body.classList.remove('light-mode'); body.dataset.type = 'dark';
        }
        updateLoginThemeIcon();
    }
    function initStarfield() {
        var canvas = document.getElementById('stars-canvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var stars = [];
        function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        function createStars(count) {
            stars = [];
            for (var i = 0; i < count; i++) {
                stars.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, r: Math.random() * 1.5 + 0.3, alpha: Math.random(), speed: Math.random() * 0.3 + 0.05, twinkle: Math.random() * 0.02 + 0.005 });
            }
        }
        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (var i = 0; i < stars.length; i++) {
                var s = stars[i];
                s.alpha += s.twinkle * (Math.random() > 0.5 ? 1 : -1);
                if (s.alpha > 1) s.alpha = 1;
                if (s.alpha < 0.1) s.alpha = 0.1;
                s.y -= s.speed * 0.15;
                if (s.y < 0) s.y = canvas.height;
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                var isLight = document.body.classList.contains('light-mode');
                ctx.fillStyle = isLight ? 'rgba(124,58,237,' + (s.alpha * 0.55) + ')' : 'rgba(255,255,255,' + s.alpha + ')';
                ctx.fill();
            }
            requestAnimationFrame(draw);
        }
        resize();
        createStars(Math.floor((canvas.width * canvas.height) / 3500));
        draw();
        window.addEventListener('resize', function () { resize(); createStars(Math.floor((canvas.width * canvas.height) / 3500)); });
    }
    document.addEventListener('DOMContentLoaded', function () {
        initLoginTheme();
        initStarfield();
        document.getElementById('layout-mode-login').addEventListener('click', function (e) {
            e.preventDefault();
            if (typeof changeMode === 'function') changeMode();
            updateLoginThemeIcon();
        });
        document.querySelector('.toggle-password').addEventListener('click', function () {
            var input = document.getElementById('password');
            var icon = document.getElementById('toggle-password-icon');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
        });
    });
})();
</script>
</body>
</html>
