@extends('dashboard.layout.main')

@push('styles')
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/charts/apexcharts.css') }}">
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

        <div class="dash-stat-section mb-2">
            <h3 class="dash-stat-section__title">
                <span class="dash-stat-section__dot"></span>
                {{ __('Analytics') }}
            </h3>

            <div class="row match-height">
                <div class="col-xl-8 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Growth over last 12 months') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('New records created each month') }}</p>
                            </div>
                            <div class="dash-chart-badge">
                                <i class="feather icon-activity"></i>
                                <span>{{ number_format(array_sum($charts['growth']['totals'])) }}</span>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            <div id="chart-growth"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Users by type') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('Active accounts only') }}</p>
                            </div>
                            <div class="dash-chart-badge">
                                <i class="feather icon-users"></i>
                                <span>{{ number_format($charts['user_types']['total']) }}</span>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            @if ($charts['user_types']['total'] > 0)
                                <div id="chart-user-types"></div>
                            @else
                                <div class="dash-chart-empty">{{ __('No data available') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Approvals by module') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('Pending, approved, and rejected counts') }}</p>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            <div id="chart-approvals"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Pending queue') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('Items waiting for admin action') }}</p>
                            </div>
                            <div class="dash-chart-badge">
                                <i class="feather icon-clock"></i>
                                <span>{{ number_format($charts['pending']['total']) }}</span>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            @if ($charts['pending']['total'] > 0)
                                <div id="chart-pending"></div>
                            @else
                                <div class="dash-chart-empty">{{ __('No pending approvals') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Platform overview') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('Current totals across core modules') }}</p>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            <div id="chart-overview"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12 mb-2">
                    <div class="card dash-chart-card h-100">
                        <div class="dash-chart-header">
                            <div>
                                <h4 class="card-title mb-0">{{ __('Registrations breakdown') }}</h4>
                                <p class="dash-chart-subtitle mb-0">{{ __('Volunteer, learn & serve, and event signups') }}</p>
                            </div>
                            <div class="dash-chart-badge">
                                <i class="feather icon-check-circle"></i>
                                <span>{{ number_format($charts['registrations']['total']) }}</span>
                            </div>
                        </div>
                        <div class="card-body dash-chart-body">
                            @if ($charts['registrations']['total'] > 0)
                                <div id="chart-registrations"></div>
                            @else
                                <div class="dash-chart-empty">{{ __('No registrations yet') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('_dashboard/app-assets/vendors/js/charts/apexcharts.min.js') }}"></script>
    <script>
        (function () {
            const charts = @json($charts);
            const colors = charts.colors;
            const isRtl = !!charts.is_rtl;
            const fontFamily = document.body.classList.contains('rtl') || isRtl
                ? "'Almarai', 'Cairo', sans-serif"
                : "'Cairo', sans-serif";
            const isDark = document.body.classList.contains('dark-layout');
            const labelColor = isDark ? '#CBD5E1' : '#64748B';
            const gridColor = isDark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(148, 163, 184, 0.25)';

            const baseChart = {
                chart: {
                    fontFamily: fontFamily,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 700,
                    },
                },
                dataLabels: { enabled: false },
                legend: {
                    fontFamily: fontFamily,
                    labels: { colors: labelColor },
                    markers: { width: 10, height: 10, radius: 10 },
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    style: { fontFamily: fontFamily },
                },
                grid: {
                    borderColor: gridColor,
                    strokeDashArray: 4,
                    padding: { left: 8, right: 8 },
                },
                states: {
                    hover: { filter: { type: 'lighten', value: 0.04 } },
                    active: { filter: { type: 'darken', value: 0.08 } },
                },
            };

            function hasValues(series) {
                return Array.isArray(series) && series.some((value) => Number(value) > 0);
            }

            if (document.querySelector('#chart-growth')) {
                new ApexCharts(document.querySelector('#chart-growth'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'area', height: 340, stacked: false },
                    colors: [colors.primary, colors.info, colors.teal, colors.warning],
                    series: charts.growth.series,
                    stroke: { curve: 'smooth', width: 2.5 },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.35,
                            opacityTo: 0.05,
                            stops: [0, 90, 100],
                        },
                    },
                    markers: { size: 0, hover: { size: 5 } },
                    xaxis: {
                        categories: charts.growth.labels,
                        labels: { style: { colors: labelColor, fontFamily: fontFamily } },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                    yaxis: {
                        opposite: isRtl,
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            style: { colors: labelColor, fontFamily: fontFamily },
                            formatter: (value) => Math.round(value),
                        },
                    },
                    legend: { ...baseChart.legend, position: 'top', horizontalAlign: isRtl ? 'right' : 'left' },
                }).render();
            }

            if (document.querySelector('#chart-user-types') && hasValues(charts.user_types.series)) {
                new ApexCharts(document.querySelector('#chart-user-types'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'donut', height: 340 },
                    colors: [colors.primary, colors.info, colors.warning, colors.muted],
                    series: charts.user_types.series,
                    labels: charts.user_types.labels,
                    stroke: { width: 0 },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                                labels: {
                                    show: true,
                                    name: { show: true, fontFamily: fontFamily, color: labelColor },
                                    value: {
                                        show: true,
                                        fontFamily: fontFamily,
                                        color: isDark ? '#F8FAFC' : '#0F172A',
                                        formatter: (val) => Number(val).toLocaleString(),
                                    },
                                    total: {
                                        show: true,
                                        label: @json(__('total')),
                                        fontFamily: fontFamily,
                                        color: labelColor,
                                        formatter: () => Number(charts.user_types.total).toLocaleString(),
                                    },
                                },
                            },
                        },
                    },
                    legend: { ...baseChart.legend, position: 'bottom' },
                }).render();
            }

            if (document.querySelector('#chart-approvals')) {
                new ApexCharts(document.querySelector('#chart-approvals'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'bar', height: 360, stacked: true },
                    colors: [colors.warning, colors.success, colors.danger],
                    series: charts.approvals.series,
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '48%',
                            borderRadius: 6,
                            borderRadiusApplication: 'end',
                        },
                    },
                    xaxis: {
                        categories: charts.approvals.categories,
                        labels: {
                            style: { colors: labelColor, fontFamily: fontFamily, fontSize: '11px' },
                            trim: true,
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                    yaxis: {
                        opposite: isRtl,
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            style: { colors: labelColor, fontFamily: fontFamily },
                            formatter: (value) => Math.round(value),
                        },
                    },
                    legend: { ...baseChart.legend, position: 'top', horizontalAlign: isRtl ? 'right' : 'left' },
                }).render();
            }

            if (document.querySelector('#chart-pending') && hasValues(charts.pending.series)) {
                new ApexCharts(document.querySelector('#chart-pending'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'radialBar', height: 360 },
                    colors: [colors.warning, colors.primary, colors.info, colors.teal, colors.danger],
                    series: charts.pending.series.map((value) => {
                        const total = Math.max(1, charts.pending.total);
                        return Math.round((Number(value) / total) * 1000) / 10;
                    }),
                    labels: charts.pending.labels,
                    plotOptions: {
                        radialBar: {
                            offsetY: 0,
                            startAngle: 0,
                            endAngle: 270,
                            hollow: { margin: 8, size: '28%' },
                            track: { background: gridColor, strokeWidth: '100%' },
                            dataLabels: {
                                name: { fontSize: '12px', fontFamily: fontFamily, color: labelColor },
                                value: {
                                    fontSize: '14px',
                                    fontFamily: fontFamily,
                                    color: isDark ? '#F8FAFC' : '#0F172A',
                                    formatter: (val, opts) => {
                                        const index = opts.seriesIndex;
                                        return Number(charts.pending.series[index] || 0).toLocaleString();
                                    },
                                },
                            },
                        },
                    },
                    legend: {
                        ...baseChart.legend,
                        show: true,
                        position: 'bottom',
                        formatter: (seriesName, opts) => {
                            const value = charts.pending.series[opts.seriesIndex] || 0;
                            return seriesName + ': ' + Number(value).toLocaleString();
                        },
                    },
                    tooltip: {
                        ...baseChart.tooltip,
                        y: {
                            formatter: (val, opts) => {
                                const raw = charts.pending.series[opts.seriesIndex] || 0;
                                return Number(raw).toLocaleString() + ' (' + val + '%)';
                            },
                        },
                    },
                }).render();
            }

            if (document.querySelector('#chart-overview')) {
                new ApexCharts(document.querySelector('#chart-overview'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'bar', height: 360 },
                    colors: [
                        colors.primary,
                        colors.info,
                        colors.teal,
                        colors.secondary,
                        colors.warning,
                        colors.success,
                        colors.danger,
                    ],
                    series: [{
                        name: @json(__('total')),
                        data: charts.overview.series,
                    }],
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 8,
                            barHeight: '62%',
                            distributed: true,
                        },
                    },
                    xaxis: {
                        categories: charts.overview.labels,
                        labels: {
                            style: { colors: labelColor, fontFamily: fontFamily },
                            formatter: (value) => Math.round(value),
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                    yaxis: {
                        labels: { style: { colors: labelColor, fontFamily: fontFamily, fontSize: '12px' } },
                    },
                    legend: { show: false },
                    tooltip: {
                        ...baseChart.tooltip,
                        y: { formatter: (val) => Number(val).toLocaleString() },
                    },
                }).render();
            }

            if (document.querySelector('#chart-registrations') && hasValues(charts.registrations.series)) {
                new ApexCharts(document.querySelector('#chart-registrations'), {
                    ...baseChart,
                    chart: { ...baseChart.chart, type: 'donut', height: 360 },
                    colors: [colors.primary, colors.teal, colors.warning],
                    series: charts.registrations.series,
                    labels: charts.registrations.labels,
                    stroke: { width: 0 },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '70%',
                                labels: {
                                    show: true,
                                    name: { fontFamily: fontFamily, color: labelColor },
                                    value: {
                                        fontFamily: fontFamily,
                                        color: isDark ? '#F8FAFC' : '#0F172A',
                                        formatter: (val) => Number(val).toLocaleString(),
                                    },
                                    total: {
                                        show: true,
                                        label: @json(__('total')),
                                        fontFamily: fontFamily,
                                        color: labelColor,
                                        formatter: () => Number(charts.registrations.total).toLocaleString(),
                                    },
                                },
                            },
                        },
                    },
                    legend: { ...baseChart.legend, position: 'bottom' },
                }).render();
            }
        })();
    </script>
@endpush
