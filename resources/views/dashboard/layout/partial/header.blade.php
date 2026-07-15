<!DOCTYPE html>
<html class="loading" lang="{{ app()->getLocale() }}" data-textdirection="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forsa | @yield('title')</title>

    @include('dashboard.layout.styles')
    @stack('styles')
</head>

<body
    @if (app()->getLocale() === 'ar')
        style="font-family: 'Almarai', sans-serif !important; font-weight: 400;"
    @else
        style="font-family: 'Cairo', sans-serif !important; font-weight: 500;"
    @endif
    id="content_body"
    class="position-relative vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static dark-layout"
    data-open="click"
    data-menu="vertical-menu-modern"
    data-col="2-columns"
    data-type="dark">

    <script>
        (function () {
            try {
                var collapsed = localStorage.getItem('forsa_admin_sidebar_collapsed') === '1';
                document.body.classList.add(collapsed ? 'menu-collapsed' : 'menu-expanded');
            } catch (e) {
                document.body.classList.add('menu-expanded');
            }
        })();
    </script>
    <div class="loader">
        <div class="sk-chase">
            <div class="sk-chase-dot"></div>
            <div class="sk-chase-dot"></div>
            <div class="sk-chase-dot"></div>
            <div class="sk-chase-dot"></div>
            <div class="sk-chase-dot"></div>
            <div class="sk-chase-dot"></div>
        </div>
    </div>
