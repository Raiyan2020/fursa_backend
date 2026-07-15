@extends('dashboard.layout.main')
@section('title', __('license requirements'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('license requirements') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('user role') }}</th>
                                        <th>{{ __('license required') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($requirements as $requirement)
                                        <tr>
                                            <td>{{ $requirement->id }}</td>
                                            <td>{{ $requirement->user_role }}</td>
                                            <td>
                                                <span class="badge badge-light-{{ $requirement->license_required ? 'success' : 'secondary' }}">
                                                    {{ $requirement->license_required ? __('yes') : __('no') }}
                                                </span>
                                            </td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.license-requirements.edit', $requirement) }}"><i class="feather icon-edit"></i></a>
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
