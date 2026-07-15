<nav class="header-navbar navbar-expand-lg navbar navbar-with-menu floating-nav navbar-light navbar-shadow">
    <div class="navbar-wrapper">
        <div class="navbar-container content">
            <div class="navbar-collapse" id="navbar-mobile">
                <div class="mr-auto float-left bookmark-wrapper d-flex align-items-center">
                    <ul class="nav navbar-nav">
                        <li class="nav-item mobile-menu d-xl-none mr-auto">
                            <a class="nav-link nav-menu-main menu-toggle hidden-xs" href="#">
                                <i class="ficon feather icon-menu"></i>
                            </a>
                        </li>
                    </ul>
                    <ul class="nav navbar-nav admin-navbar-start-tools d-none d-lg-flex">
                        <li class="nav-item">
                            <a class="nav-link admin-nav-icon-btn" id="layout-mode" href="#" title="{{ __('Theme mode') }}">
                                <i class="ficon feather icon-moon" onclick="changeMode()"></i>
                            </a>
                        </li>
                        <li class="dropdown dropdown-theme nav-item">
                            <a class="dropdown-toggle nav-link admin-nav-icon-btn" id="dropdown-theme-switcher" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{ __('Theme color') }}">
                                <i class="ficon ti ti-palette"></i>
                            </a>
                            <div class="dropdown-menu theme-switcher-menu" aria-labelledby="dropdown-theme-switcher">
                                <div class="theme-switcher-menu__title">{{ __('Theme color') }}</div>
                                <div class="theme-switcher-menu__colors">
                                    <span class="theme-color-circle" data-color="#7c3aed" data-hover="#6d28d9" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)" title="Forsa Purple"></span>
                                    <span class="theme-color-circle" data-color="#29246d" data-hover="#1f1b52" style="background:linear-gradient(135deg,#3a3499,#29246d)" title="Forsa Deep"></span>
                                    <span class="theme-color-circle" data-color="#6366f1" data-hover="#4f46e5" style="background:linear-gradient(135deg,#6366f1,#4f46e5)" title="Indigo"></span>
                                    <span class="theme-color-circle" data-color="#10b981" data-hover="#059669" style="background:linear-gradient(135deg,#10b981,#059669)" title="Emerald"></span>
                                    <span class="theme-color-circle" data-color="#f97316" data-hover="#ea580c" style="background:linear-gradient(135deg,#f97316,#ea580c)" title="Orange"></span>
                                    <span class="theme-color-circle" data-color="#1f2937" data-hover="#111827" style="background:linear-gradient(135deg,#1f2937,#111827)" title="Dark"></span>
                                    <span class="theme-color-circle" data-color="#64748b" data-hover="#475569" style="background:linear-gradient(135deg,#64748b,#475569)" title="Slate"></span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <ul class="nav navbar-nav float-right resp-wrap-icon">
                    <li class="dropdown dropdown-language admin-lang-dropdown nav-item">
                        <a class="dropdown-toggle nav-link admin-lang-toggle" id="dropdown-flag" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            @if (app()->getLocale() === 'ar')
                                <img class="admin-lang-flag" src="{{ asset('_dashboard/assets/flags/svg/sa.svg') }}" alt="Saudi Arabia" width="22" height="16">
                                <span class="lang-label selected-language">{{ __('Arabic') }}</span>
                            @else
                                <img class="admin-lang-flag" src="{{ asset('_dashboard/assets/flags/svg/us.svg') }}" alt="United States" width="22" height="16">
                                <span class="lang-label selected-language">{{ __('English') }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu admin-lang-menu" aria-labelledby="dropdown-flag">
                            <div class="admin-lang-menu__title">{{ __('Language') }}</div>
                            <a class="dropdown-item admin-lang-option {{ app()->getLocale() === 'ar' ? 'active' : '' }}" href="{{ route('change-language', ['lang' => 'ar']) }}">
                                <img class="admin-lang-flag" src="{{ asset('_dashboard/assets/flags/svg/sa.svg') }}" alt="Saudi Arabia" width="22" height="16">
                                <span>{{ __('Arabic') }}</span>
                            </a>
                            <a class="dropdown-item admin-lang-option {{ app()->getLocale() === 'en' ? 'active' : '' }}" href="{{ route('change-language', ['lang' => 'en']) }}">
                                <img class="admin-lang-flag" src="{{ asset('_dashboard/assets/flags/svg/us.svg') }}" alt="United States" width="22" height="16">
                                <span>{{ __('English') }}</span>
                            </a>
                        </div>
                    </li>

                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-link nav-link-expand"><i class="ficon feather icon-maximize"></i></a>
                    </li>

                    <li class="dropdown dropdown-user nav-item">
                        <a class="dropdown-toggle nav-link dropdown-user-link" href="#" data-toggle="dropdown">
                            <div class="user-nav d-sm-flex d-none">
                                <span class="user-name text-bold-600">{{ auth('admin')->user()->name }}</span>
                                <span class="user-status">{{ __('Available') }}</span>
                            </div>
                            <span>
                                <span class="avatar avatar-md">
                                    <span class="avatar-content">
                                        <i class="feather icon-user font-medium-3"></i>
                                    </span>
                                </span>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="feather icon-power"></i> {{ __('logout') }}
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
