@extends('dashboard.layout.main')
@section('title', __('events'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('events') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.events.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('image') }}</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('organization') }}</th>
                                        <th>{{ __('start date') }}</th>
                                        <th>{{ __('approval status') }}</th>
                                        <th>{{ __('event status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($events as $event)
                                        @php
                                            $cover = $event->images?->first(fn ($img) => ! $img->is_deleted);
                                        @endphp
                                        <tr>
                                            <td>{{ $event->id }}</td>
                                            <td>
                                                @if ($cover?->image)
                                                    <img src="{{ getimg($cover->image) }}" width="60" height="60" alt="" style="object-fit:cover;border-radius:8px;">
                                                @else
                                                    <span class="text-muted">{{ __('no image') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ tr($event->title_en, $event->title_ar) }}</td>
                                            <td>{{ $event->organization?->company_name }}</td>
                                            <td>{{ optional($event->start_date)->format('Y-m-d') }}</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $event->approval_status])</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $event->event_status])</td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.events.show', $event) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.events.edit', $event) }}"><i class="feather icon-edit"></i></a>
                                                @if ($event->approval_status === \App\Enums\ApprovalStatus::PENDING)
                                                    <a class="btn btn-success" href="#" onclick="forsaApprove('{{ route('admin.events.approve', $event) }}')"><i class="feather icon-check"></i></a>
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.events.reject', $event) }}')"><i class="feather icon-x"></i></a>
                                                @endif
                                                @if ($event->deletion_status === \App\Enums\DeletionStatus::PENDING)
                                                    <a class="btn btn-outline-danger" href="#" onclick="forsaConfirmPost('{{ route('admin.events.approve-deletion', $event) }}','{{ __('Approve deletion request ?') }}')"><i class="feather icon-trash-2"></i></a>
                                                    <a class="btn btn-outline-secondary" href="#" onclick="forsaReject('{{ route('admin.events.reject-deletion', $event) }}')"><i class="feather icon-rotate-ccw"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.events.destroy', $event) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@include('dashboard.layout.datatables')
@include('dashboard.partials.workflow-actions')
