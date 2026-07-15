@extends('dashboard.layout.main')
@section('title', __('permissions'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('permissions') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('name') }}</th>
                                        <th>{{ __('roles') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($permissions as $permission)
                                        <tr>
                                            <td>{{ $permission->id }}</td>
                                            <td><code>{{ $permission->name }}</code></td>
                                            <td>{{ number_format($permission->roles_count) }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.permissions.edit', $permission) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.permissions.destroy', $permission) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
