@extends('dashboard.layout.main')
@section('title', __('volunteer opportunities'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('volunteer opportunities') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.volunteer-opportunities.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <a href="{{ route('admin.volunteer-opportunities.export') }}" class="btn btn-success mb-2 waves-effect waves-light">
                            <i class="fas fa-file-excel"></i>&nbsp; {{ __('export excel') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('image') }}</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('creator') }}</th>
                                        <th>{{ __('approval status') }}</th>
                                        <th>{{ __('deletion status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($opportunities as $opportunity)
                                        @php
                                            $cover = opportunity_cover_image($opportunity->images);
                                        @endphp
                                        <tr>
                                            <td>{{ $opportunity->id }}</td>
                                            <td>
                                                @if ($cover?->image)
                                                    <img src="{{ getimg($cover->image) }}" width="60" height="60" alt="" style="object-fit:cover;border-radius:8px;">
                                                @endif
                                            </td>
                                            <td>{{ tr($opportunity->title_en, $opportunity->title_ar) }}</td>
                                            <td>{{ $opportunity->creator?->email }}</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->approval_status])</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->deletion_status])</td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.volunteer-opportunities.show', $opportunity) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.volunteer-opportunities.edit', $opportunity) }}"><i class="feather icon-edit"></i></a>
                                                @if ($opportunity->approval_status === \App\Enums\ApprovalStatus::PENDING)
                                                    <a class="btn btn-success" href="#" onclick="forsaApprove('{{ route('admin.volunteer-opportunities.approve', $opportunity) }}')"><i class="feather icon-check"></i></a>
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.volunteer-opportunities.reject', $opportunity) }}')"><i class="feather icon-x"></i></a>
                                                @endif
                                                @if ($opportunity->deletion_status === \App\Enums\DeletionStatus::PENDING)
                                                    <a class="btn btn-outline-danger" href="#" onclick="forsaConfirmPost('{{ route('admin.volunteer-opportunities.approve-deletion', $opportunity) }}','{{ __('Approve deletion request ?') }}')"><i class="feather icon-trash-2"></i></a>
                                                    <a class="btn btn-outline-secondary" href="#" onclick="forsaReject('{{ route('admin.volunteer-opportunities.reject-deletion', $opportunity) }}')"><i class="feather icon-rotate-ccw"></i></a>
                                                @endif
                                                @if ($opportunity->is_registration_closed)
                                                    <a class="btn btn-outline-success" title="{{ __('reopen registration') }}" href="#" onclick="forsaConfirmPost('{{ route('admin.volunteer-opportunities.toggle-registration', $opportunity) }}','{{ __('Reopen registration ?') }}')"><i class="feather icon-unlock"></i></a>
                                                @else
                                                    <a class="btn btn-outline-warning" title="{{ __('close registration') }}" href="#" onclick="forsaConfirmPost('{{ route('admin.volunteer-opportunities.toggle-registration', $opportunity) }}','{{ __('Close registration ?') }}')"><i class="feather icon-lock"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.volunteer-opportunities.destroy', $opportunity) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
