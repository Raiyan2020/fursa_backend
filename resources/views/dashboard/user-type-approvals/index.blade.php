@extends('dashboard.layout.main')
@section('title', __('user type approvals'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('user type approvals') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('user type') }}</th>
                                        <th>{{ __('requires approval') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($approvals as $a)
                                        <tr>
                                            <td>{{ $a->id }}</td>
                                            <td>{{ $a->user_type?->label() ?? '-' }}</td>
                                            <td>
                                                <span class="badge badge-light-{{ $a->requires_approval ? 'success' : 'secondary' }}">
                                                    {{ $a->requires_approval ? __('yes') : __('no') }}
                                                </span>
                                            </td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.user-type-approvals.edit', $a) }}"><i class="feather icon-edit"></i></a>
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
