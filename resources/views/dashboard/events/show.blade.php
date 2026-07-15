@extends('dashboard.layout.main')
@section('title', __('show'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('events') }} - {{ tr($event->title_en, $event->title_ar) }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th>{{ __('title') }} ({{ __('en') }})</th>
                                    <td>{{ $event->title_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('title') }} ({{ __('ar') }})</th>
                                    <td>{{ $event->title_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('description') }} ({{ __('en') }})</th>
                                    <td>{{ $event->description_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('description') }} ({{ __('ar') }})</th>
                                    <td>{{ $event->description_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('start date') }}</th>
                                    <td>{{ optional($event->start_date)->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('end date') }}</th>
                                    <td>{{ optional($event->end_date)->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('start time') }}</th>
                                    <td>{{ $event->start_time }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('end time') }}</th>
                                    <td>{{ $event->end_time }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('participants needed') }}</th>
                                    <td>{{ $event->participants_needed }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('from age') }}</th>
                                    <td>{{ $event->from_age }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('to age') }}</th>
                                    <td>{{ $event->to_age }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('location') }} ({{ __('en') }})</th>
                                    <td>{{ $event->location_en }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('location') }} ({{ __('ar') }})</th>
                                    <td>{{ $event->location_ar }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('registration required') }}</th>
                                    <td>{{ $event->registration_required ? __('yes') : __('no') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('event status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $event->event_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('approval status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $event->approval_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('deletion status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $event->deletion_status])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('rejected reason') }}</th>
                                    <td>{{ $event->rejected_reason }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('organization') }}</th>
                                    <td>{{ $event->organization?->company_name }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
