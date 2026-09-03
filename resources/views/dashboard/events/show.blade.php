@extends('dashboard.layout.main')
@section('title', __('show'))
@section('content')
    @php
        $locale = app()->getLocale();
        $choiceLabel = fn ($choice) => $choice
            ? ($locale === 'ar' ? ($choice->value_ar ?: $choice->value_en) : ($choice->value_en ?: $choice->value_ar))
            : '-';
    @endphp
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">{{ __('events') }} - {{ tr($event->title_en, $event->title_ar) }}</h4>
                    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-warning btn-sm">
                        <i class="feather icon-edit"></i> {{ __('edit') }}
                    </a>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        @if ($event->images->where('is_deleted', false)->isNotEmpty())
                            <div class="mb-2 d-flex flex-wrap">
                                @foreach ($event->images->where('is_deleted', false) as $image)
                                    <img src="{{ getimg($image->image) }}" alt="" class="mr-2 mb-2" style="max-height:120px;border-radius:8px;">
                                @endforeach
                            </div>
                        @endif
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
                                    <td>{!! nl2br(e($event->description_en)) !!}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('description') }} ({{ __('ar') }})</th>
                                    <td>{!! nl2br(e($event->description_ar)) !!}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('event type') }}</th>
                                    <td>{{ $choiceLabel($event->eventType) }}</td>
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
                                    <th>{{ __('location') }} ({{ __('en') }})</th>
                                    <td>{{ $event->location_en ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('location') }} ({{ __('ar') }})</th>
                                    <td>{{ $event->location_ar ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('from age') }}</th>
                                    <td>{{ $event->from_age ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('to age') }}</th>
                                    <td>{{ $event->to_age ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('gender') }}</th>
                                    <td>{{ $choiceLabel($event->genderChoice) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('participants needed') }}</th>
                                    <td>{{ $event->participants_needed }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('registration required') }}</th>
                                    <td>{{ $event->registration_required ? __('yes') : __('no') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('paid registration') }}</th>
                                    <td>{{ $event->paid_registration ? __('yes') : __('no') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('interests') }}</th>
                                    <td>
                                        @forelse ($event->interests as $interest)
                                            <span class="badge badge-primary">{{ $locale === 'ar' ? $interest->name_ar : $interest->name_en }}</span>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('event status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $event->resolvedOpportunityStatus()])</td>
                                </tr>
                                <tr>
                                    <th>{{ __('approval status') }}</th>
                                    <td>@include('dashboard.partials.status-badge', ['status' => $event->approval_status])</td>
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
