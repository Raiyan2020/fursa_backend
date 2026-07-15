@extends('dashboard.layout.main')
@section('title', __('show'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('learn & serve opportunities') }} - {{ tr($opportunity->title_en, $opportunity->title_ar) }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th>{{ __('title') }} ({{ __('en') }})</th>
                                    <td>{{ $opportunity->title_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('title') }} ({{ __('ar') }})</th>
                                    <td>{{ $opportunity->title_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('description') }} ({{ __('en') }})</th>
                                    <td>{{ $opportunity->description_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('description') }} ({{ __('ar') }})</th>
                                    <td>{{ $opportunity->description_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('start date') }}</th>
                                    <td>{{ optional($opportunity->start_date)->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('end date') }}</th>
                                    <td>{{ optional($opportunity->end_date)->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('start time') }}</th>
                                    <td>{{ $opportunity->start_time }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('end time') }}</th>
                                    <td>{{ $opportunity->end_time }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('participants needed') }}</th>
                                    <td>{{ $opportunity->participants_needed }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('from age') }}</th>
                                    <td>{{ $opportunity->from_age }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('to age') }}</th>
                                    <td>{{ $opportunity->to_age }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('location') }} ({{ __('en') }})</th>
                                    <td>{{ $opportunity->location_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('location') }} ({{ __('ar') }})</th>
                                    <td>{{ $opportunity->location_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('opportunity status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->opportunity_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('approval status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->approval_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('deletion status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->deletion_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('rejected reason') }}</th>
                                    <td>{{ $opportunity->rejected_reason }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('creator') }}</th>
                                    <td>{{ $opportunity->creator?->email }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <a href="{{ route('admin.learn-serve-opportunities.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
