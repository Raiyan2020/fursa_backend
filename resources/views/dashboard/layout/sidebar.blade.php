<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">

    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" type="button" title="{{ __('admin.sidebar.collapse_menu') }}">
        <i class="feather icon-chevron-left"></i>
    </button>

    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item sidebar-logo-li">
                <a class="sidebar-logo navbar-brand" href="{{ route('admin.home') }}">
                    <span class="sidebar-logo-mark" aria-hidden="true">F</span>
                    <span class="sidebar-logo-text brand-text">Forsa</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-search">
        <i class="feather icon-search sidebar-search-icon"></i>
        <input type="text" id="sidebarSearch" placeholder="{{ __('admin.sidebar.search') }}..." autocomplete="off">
    </div>

    <div class="shadow-bottom"></div>
    <div class="main-menu-content">
        {{-- Sidebar mirrors Django Jazzmin order + kept CMS: Why Fursa, Pages, Site settings --}}
        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">
            <li class="nav-item {{ request()->routeIs('admin.home') ? 'active' : '' }}">
                <a href="{{ route('admin.home') }}">
                    <i class="feather icon-home"></i>
                    <span class="menu-title">{{ __('admin.sidebar.main_page') }}</span>
                </a>
            </li>

            {{-- Django: base — Tags, Banners, General Settings, License Requirements --}}
            <li class="nav-item has-sub {{ request()->is('dashboard/tags*', 'dashboard/banners*', 'dashboard/settings*', 'dashboard/license-requirements*') ? 'active open' : '' }}">
                <a href="#">
                    <i class="feather icon-grid"></i>
                    <span class="menu-title">{{ __('admin.sidebar.base') }}</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is('dashboard/tags*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.tags.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.tags') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/banners*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.banners.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.banners') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/settings*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.settings.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.general_settings') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/license-requirements*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.license-requirements.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.license_requirements') }}</span>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Django: opportunity --}}
            <li class="nav-item has-sub {{ request()->is('dashboard/volunteer-opportunities*', 'dashboard/learn-serve-opportunities*') ? 'active open' : '' }}">
                <a href="#">
                    <i class="feather icon-target"></i>
                    <span class="menu-title">{{ __('admin.sidebar.opportunities') }}</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is('dashboard/volunteer-opportunities*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.volunteer-opportunities.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.volunteer_opportunities') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/learn-serve-opportunities*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.learn-serve-opportunities.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.learn_share_opportunities') }}</span>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Django: event --}}
            <li class="nav-item {{ request()->is('dashboard/events*') ? 'active' : '' }}">
                <a href="{{ route('admin.events.index') }}">
                    <i class="feather icon-calendar"></i>
                    <span class="menu-title">{{ __('admin.sidebar.events') }}</span>
                </a>
            </li>

            {{-- Django: authentication — Users, UserType Approval --}}
            <li class="nav-item has-sub {{ request()->is('dashboard/users*', 'dashboard/user-type-approvals*') ? 'active open' : '' }}">
                <a href="#">
                    <i class="feather icon-user"></i>
                    <span class="menu-title">{{ __('admin.sidebar.users') }}</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is('dashboard/users*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.users.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.all_users') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/user-type-approvals*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.user-type-approvals.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.user_type_approvals') }}</span>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Django: volunteerprofile — Volunteer Profiles, Badges --}}
            <li class="nav-item has-sub {{ request()->is('dashboard/volunteers*', 'dashboard/badges*') ? 'active open' : '' }}">
                <a href="#">
                    <i class="feather icon-users"></i>
                    <span class="menu-title">{{ __('admin.sidebar.volunteer_profile') }}</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is('dashboard/volunteers*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.volunteers.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.volunteers') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/badges*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.badges.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.badges') }}</span>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Django: organizationprofile --}}
            <li class="nav-item {{ request()->is('dashboard/entities*') ? 'active' : '' }}">
                <a href="{{ route('admin.entities.index') }}">
                    <i class="feather icon-briefcase"></i>
                    <span class="menu-title">{{ __('admin.sidebar.entities') }}</span>
                </a>
            </li>

            {{-- Django: sponsors --}}
            <li class="nav-item {{ request()->is('dashboard/sponsors*') ? 'active' : '' }}">
                <a href="{{ route('admin.sponsors.index') }}">
                    <i class="feather icon-award"></i>
                    <span class="menu-title">{{ __('admin.sidebar.sponsors') }}</span>
                </a>
            </li>

            {{-- Django: fursa_friend --}}
            <li class="nav-item {{ request()->is('dashboard/fursa-friends*') ? 'active' : '' }}">
                <a href="{{ route('admin.fursa-friends.index') }}">
                    <i class="feather icon-heart"></i>
                    <span class="menu-title">{{ __('admin.sidebar.forsa_friends') }}</span>
                </a>
            </li>

            {{-- Django: community — Forbidden Words --}}
            <li class="nav-item {{ request()->is('dashboard/forbidden-words*') ? 'active' : '' }}">
                <a href="{{ route('admin.forbidden-words.index') }}">
                    <i class="feather icon-slash"></i>
                    <span class="menu-title">{{ __('admin.sidebar.forbidden_words') }}</span>
                </a>
            </li>

            {{-- Django: email_template --}}
            <li class="nav-item {{ request()->is('dashboard/email-templates*') ? 'active' : '' }}">
                <a href="{{ route('admin.email-templates.index') }}">
                    <i class="feather icon-mail"></i>
                    <span class="menu-title">{{ __('admin.sidebar.email_templates') }}</span>
                </a>
            </li>

            {{-- Django: faq --}}
            <li class="nav-item {{ request()->is('dashboard/faqs*') ? 'active' : '' }}">
                <a href="{{ route('admin.faqs.index') }}">
                    <i class="feather icon-help-circle"></i>
                    <span class="menu-title">{{ __('admin.sidebar.faqs') }}</span>
                </a>
            </li>

            {{-- Django: notification --}}
            <li class="nav-item {{ request()->is('dashboard/notifications*') ? 'active' : '' }}">
                <a href="{{ route('admin.notifications.index') }}">
                    <i class="feather icon-bell"></i>
                    <span class="menu-title">{{ __('admin.sidebar.notifications') }}</span>
                </a>
            </li>

            {{-- Kept Laravel CMS extras (not in Django admin) --}}
            <li class="nav-item has-sub {{ request()->is('dashboard/why-fursa*', 'dashboard/pages*', 'dashboard/site-settings*') ? 'active open' : '' }}">
                <a href="#">
                    <i class="feather icon-layout"></i>
                    <span class="menu-title">{{ __('admin.sidebar.website_cms') }}</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is('dashboard/why-fursa*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.why-fursa.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.why_fursa') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/pages*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.pages.index') }}">
                            <span class="menu-item">{{ __('admin.sidebar.pages') }}</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('dashboard/site-settings*') ? 'active' : '' }}">
                        <a class="check-active" href="{{ route('admin.site-settings.edit') }}">
                            <span class="menu-item">{{ __('admin.sidebar.site_settings') }}</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<form method="POST" action="{{ route('admin.logout') }}" id="logout-form" class="d-none">
    @csrf
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var SIDEBAR_KEY = 'forsa_admin_sidebar_collapsed';
    var collapseBtn = document.getElementById('sidebarCollapseBtn');
    var isRtl = document.documentElement.getAttribute('data-textdirection') === 'rtl';

    function isCollapsed() { return document.body.classList.contains('menu-collapsed'); }

    function persistSidebarState() {
        try { localStorage.setItem(SIDEBAR_KEY, isCollapsed() ? '1' : '0'); } catch (e) {}
    }

    function syncChevron() {
        if (!collapseBtn) return;
        var icon = collapseBtn.querySelector('i');
        if (!icon) return;
        var collapsed = isCollapsed();
        icon.classList.remove('icon-chevron-left', 'icon-chevron-right');
        if (isRtl) {
            icon.classList.add(collapsed ? 'icon-chevron-left' : 'icon-chevron-right');
        } else {
            icon.classList.add(collapsed ? 'icon-chevron-right' : 'icon-chevron-left');
        }
        collapseBtn.setAttribute('title', collapsed ? '{{ __('admin.sidebar.expand_menu') }}' : '{{ __('admin.sidebar.collapse_menu') }}');
    }

    function clearHoverExpanded() {
        if (typeof jQuery === 'undefined') return;
        jQuery('.main-menu, .navbar-header').removeClass('expanded');
    }

    function applyExpandedFallback() {
        document.body.classList.remove('menu-collapsed');
        document.body.classList.add('menu-expanded');
    }

    function applyCollapsedFallback() {
        document.body.classList.remove('menu-expanded');
        document.body.classList.add('menu-collapsed');
    }

    function toggleSidebar() {
        clearHoverExpanded();
        if (typeof jQuery !== 'undefined' && jQuery.app && jQuery.app.menu) {
            jQuery.app.menu.toggle();
        } else if (isCollapsed()) {
            applyExpandedFallback();
        } else {
            applyCollapsedFallback();
        }
        setTimeout(function () {
            persistSidebarState();
            syncChevron();
            if (typeof jQuery !== 'undefined') { jQuery(window).trigger('resize'); }
        }, 220);
    }

    try {
        if (localStorage.getItem(SIDEBAR_KEY) === null) {
            applyExpandedFallback();
            localStorage.setItem(SIDEBAR_KEY, '0');
        }
    } catch (e) {}

    syncChevron();

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.menu-toggle, .modern-nav-toggle');
        if (!toggle || toggle.id === 'sidebarCollapseBtn') return;
        setTimeout(function () { persistSidebarState(); syncChevron(); }, 220);
    });

    var searchInput = document.getElementById('sidebarSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        var query = this.value.trim().toLowerCase();
        var nav = document.getElementById('main-menu-navigation');
        if (!nav) return;
        nav.querySelectorAll(':scope > li').forEach(function (li) {
            if (!query) {
                li.style.display = '';
                li.querySelectorAll('.menu-content > li').forEach(function (child) { child.style.display = ''; });
                return;
            }
            var isDropdown = li.classList.contains('has-sub') || li.querySelector('.menu-content') !== null;
            var directAnchor = li.querySelector(':scope > a');
            var directText = directAnchor ? directAnchor.textContent.toLowerCase() : '';
            if (!isDropdown) {
                li.style.display = directText.indexOf(query) !== -1 ? '' : 'none';
                return;
            }
            var children = li.querySelectorAll('.menu-content > li');
            var anyChildMatch = false;
            children.forEach(function (child) {
                var matches = child.textContent.toLowerCase().indexOf(query) !== -1;
                child.style.display = matches ? '' : 'none';
                if (matches) anyChildMatch = true;
            });
            var parentMatch = directText.indexOf(query) !== -1;
            if (parentMatch) { children.forEach(function (child) { child.style.display = ''; }); }
            li.style.display = (parentMatch || anyChildMatch) ? '' : 'none';
            if (anyChildMatch && !parentMatch) { li.classList.add('open'); }
        });
    });
});
</script>
