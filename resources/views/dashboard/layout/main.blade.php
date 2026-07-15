@include('dashboard.layout.partial.header')
@include('dashboard.layout.partial.navbar')

@yield('modals')

@include('dashboard.layout.sidebar')

<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="header-navbar-shadow"></div>
    <div class="content-wrapper">
        @include('dashboard.layout.partial.breadcrumb-bar')
        @yield('breadcrumb')
        <div class="content-header row"></div>
        <div class="content-body">
            <section id="dashboard-ecommerce">
                @yield('content')
            </section>
        </div>
    </div>
</div>

@include('dashboard.layout.partial.footer')
