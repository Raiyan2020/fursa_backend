<div class="admin-breadcrumb-bar">
    <div class="admin-breadcrumb-bar__inner">
        <nav class="admin-breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('admin.home') }}" class="admin-breadcrumb__item {{ request()->routeIs('admin.home') ? 'admin-breadcrumb__item--active' : '' }}">
                <i class="feather icon-home"></i>
                <span>{{ __('admin.sidebar.main_page') }}</span>
            </a>

            @php
                $breadcrumbParent = trim($__env->yieldContent('breadcrumbParent'));
                $breadcrumbParentUrl = trim($__env->yieldContent('breadcrumbParentUrl'));
                $pageTitle = trim($__env->yieldContent('title'));
            @endphp

            @if (!request()->routeIs('admin.home') && $breadcrumbParent !== '' && $breadcrumbParentUrl !== '')
                <span class="admin-breadcrumb__sep" aria-hidden="true">
                    <i class="feather icon-chevron-left"></i>
                </span>
                <a href="{{ $breadcrumbParentUrl }}" class="admin-breadcrumb__item">
                    <span>{{ $breadcrumbParent }}</span>
                </a>
            @endif

            @if (!request()->routeIs('admin.home') && $pageTitle !== '')
                <span class="admin-breadcrumb__sep" aria-hidden="true">
                    <i class="feather icon-chevron-left"></i>
                </span>
                <span class="admin-breadcrumb__item admin-breadcrumb__item--active">
                    <span>{{ $pageTitle }}</span>
                </span>
            @endif
        </nav>
    </div>
</div>
