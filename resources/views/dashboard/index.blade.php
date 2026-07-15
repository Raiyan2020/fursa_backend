@extends('dashboard.layout.main')

@push('styles')
    <link rel="stylesheet" href="{{ asset('_dashboard/assets/css/dashboard-charts.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/dashboard-charts.css')) }}">
@endpush

@section('title', __('main page'))

@section('content')
    <div class="dashboard-home">
        <section class="dash-welcome mb-2">
            <div class="dash-welcome__inner">
                <div class="dash-welcome__content">
                    <p class="dash-welcome__greeting">{{ $welcome['greeting'] }}</p>
                    <h1 class="dash-welcome__name">{{ $welcome['name'] }}</h1>
                    <p class="dash-welcome__subtitle">{{ __('Welcome to Forsa control panel') }}</p>

                    <div class="dash-welcome__badges">
                        <span class="dash-welcome__badge">
                            <i class="feather icon-monitor"></i>
                            <span>{{ __('System is running') }}</span>
                        </span>
                        <span class="dash-welcome__badge dash-welcome__badge--link">
                            <i class="feather icon-clock"></i>
                            <span>{{ __('Pending approvals') }}: {{ number_format($welcome['pending_count']) }}</span>
                        </span>
                        <a href="{{ route('admin.users.index') }}" class="dash-welcome__badge dash-welcome__badge--link">
                            <i class="feather icon-users"></i>
                            <span>{{ __('New users today') }}: {{ number_format($welcome['new_users_today']) }}</span>
                        </a>
                    </div>
                </div>

                <aside class="dash-welcome__date" aria-label="{{ $welcome['month_year'] }}">
                    <span class="dash-welcome__day">{{ $welcome['day'] }}</span>
                    <span class="dash-welcome__month">{{ $welcome['month_year'] }}</span>
                </aside>
            </div>
        </section>

        @php
            $statMax = max(1, collect($menus)->max('count'));
            $statGroups = collect($menus)->groupBy(fn ($menu) => $menu['group'] ?? 'content');
            $statSections = [
                'users' => __('Users section'),
                'content' => __('Content section'),
            ];
            $statIndex = 0;
        @endphp

        @foreach ($statSections as $groupKey => $groupTitle)
            @php $groupItems = $statGroups->get($groupKey); @endphp
            @if ($groupItems && $groupItems->count())
                <div class="dash-stat-section mb-2">
                    <h3 class="dash-stat-section__title">
                        <span class="dash-stat-section__dot"></span>
                        {{ $groupTitle }}
                    </h3>
                    <div class="row dash-stat-grid">
                        @foreach ($groupItems as $menu)
                            @php
                                $ratio = (int) round(min(100, max(6, ($menu['count'] / $statMax) * 100)));
                                $statIndex++;
                            @endphp
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 dash-stat-col">
                                <a href="{{ $menu['url'] }}" class="dash-stat-link">
                                    <article class="dash-stat-card" style="--bar: {{ $ratio }}%; --stat-delay: {{ $statIndex * 0.05 }}s">
                                        <div class="dash-stat-card__head">
                                            <div class="dash-stat-card__icon">
                                                <i class="feather {{ $menu['icon'] }}"></i>
                                            </div>
                                            <span class="dash-stat-card__chip">
                                                <i class="feather icon-trending-up"></i>
                                                {{ $ratio }}%
                                            </span>
                                        </div>
                                        <div class="dash-stat-card__body">
                                            <p class="dash-stat-card__title">{{ $menu['name'] }}</p>
                                            <h2 class="dash-stat-card__value">{{ number_format($menu['count']) }}</h2>
                                            <p class="dash-stat-card__sub">{{ __('total') }}</p>
                                        </div>
                                        <span class="dash-stat-card__bar" aria-hidden="true">
                                            <span class="dash-stat-card__bar-fill"></span>
                                        </span>
                                        <div class="dash-stat-card__footer">
                                            <span class="dash-stat-card__action">{{ __('view') }}</span>
                                            <i class="feather icon-chevron-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }} dash-stat-card__arrow"></i>
                                        </div>
                                    </article>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection
