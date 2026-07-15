@extends('dashboard.layout.main')
@section('title', __('entities'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('entities') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('company name') }}</th>
                                        <th>{{ __('nickname') }}</th>
                                        <th>{{ __('organizer type') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($entities as $e)
                                        <tr>
                                            <td>{{ $e->id }}</td>
                                            <td>{{ $e->company_name }}</td>
                                            <td>{{ $e->nickname }}</td>
                                            <td>{{ $e->organizerType?->value_en }}</td>
                                            <td>
                                                @include('dashboard.partials.status-badge', ['status' => $e->organization_status])
                                            </td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.entities.show', $e) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.entities.edit', $e) }}"><i class="feather icon-edit"></i></a>
                                                @if ($e->organization_status === \App\Enums\ApprovalStatus::PENDING)
                                                    <a class="btn btn-success" href="#" onclick="forsaApprove('{{ route('admin.entities.approve', $e) }}')"><i class="feather icon-check"></i></a>
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.entities.reject', $e) }}')"><i class="feather icon-x"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.entities.destroy', $e) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
    @include('dashboard.partials.workflow-actions')
@endsection
@include('dashboard.layout.datatables')
