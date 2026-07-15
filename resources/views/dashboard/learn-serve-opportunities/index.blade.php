@extends('dashboard.layout.main')
@section('title', __('learn & serve opportunities'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('learn & serve opportunities') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('creator') }}</th>
                                        <th>{{ __('approval status') }}</th>
                                        <th>{{ __('deletion status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($opportunities as $opportunity)
                                        <tr>
                                            <td>{{ $opportunity->id }}</td>
                                            <td>{{ tr($opportunity->title_en, $opportunity->title_ar) }}</td>
                                            <td>{{ $opportunity->creator?->email }}</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->approval_status])</td>
                                            <td>@include('dashboard.partials.status-badge', ['status' => $opportunity->deletion_status])</td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.learn-serve-opportunities.show', $opportunity) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.learn-serve-opportunities.edit', $opportunity) }}"><i class="feather icon-edit"></i></a>
                                                @if ($opportunity->approval_status === \App\Enums\ApprovalStatus::PENDING)
                                                    <a class="btn btn-success" href="#" onclick="forsaApprove('{{ route('admin.learn-serve-opportunities.approve', $opportunity) }}')"><i class="feather icon-check"></i></a>
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.learn-serve-opportunities.reject', $opportunity) }}')"><i class="feather icon-x"></i></a>
                                                @endif
                                                @if ($opportunity->deletion_status === \App\Enums\DeletionStatus::PENDING)
                                                    <a class="btn btn-outline-danger" href="#" onclick="forsaConfirmPost('{{ route('admin.learn-serve-opportunities.approve-deletion', $opportunity) }}','{{ __('Approve deletion request ?') }}')"><i class="feather icon-trash-2"></i></a>
                                                    <a class="btn btn-outline-secondary" href="#" onclick="forsaReject('{{ route('admin.learn-serve-opportunities.reject-deletion', $opportunity) }}')"><i class="feather icon-rotate-ccw"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.learn-serve-opportunities.destroy', $opportunity) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
