@extends('dashboard.layout.main')
@section('title', __('sponsors'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('sponsors') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('organization name') }}</th>
                                        <th>{{ __('person name') }}</th>
                                        <th>{{ __('email') }}</th>
                                        <th>{{ __('phone') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sponsors as $s)
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td>{{ $s->org_name }}</td>
                                            <td>{{ $s->person_name }}</td>
                                            <td>{{ $s->email }}</td>
                                            <td>{{ $s->country_code . ' ' . $s->phone_number }}</td>
                                            <td>
                                                @include('dashboard.partials.status-badge', ['status' => $s->approval_status])
                                            </td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.sponsors.show', $s) }}"><i class="feather icon-eye"></i></a>
                                                @if ($s->approval_status === \App\Enums\ApprovalStatus::PENDING)
                                                    <a class="btn btn-success" href="#" onclick="forsaApprove('{{ route('admin.sponsors.approve', $s) }}')"><i class="feather icon-check"></i></a>
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.sponsors.reject', $s) }}')"><i class="feather icon-x"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.sponsors.destroy', $s) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
