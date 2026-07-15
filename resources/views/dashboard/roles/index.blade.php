@extends('dashboard.layout.main')
@section('title', __('roles'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('roles') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('name') }}</th>
                                        <th>{{ __('permissions') }}</th>
                                        <th>{{ __('admins') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($roles as $role)
                                        <tr>
                                            <td>{{ $role->id }}</td>
                                            <td>
                                                {{ $role->name }}
                                                @if ($role->isSuperAdmin())
                                                    <span class="badge badge-light-primary">{{ __('Super Admin') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ number_format($role->permissions_count) }}</td>
                                            <td>{{ number_format($role->users_count) }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.roles.edit', $role) }}"><i class="feather icon-edit"></i></a>
                                                @unless($role->isSuperAdmin())
                                                    <a class="btn btn-danger" data-href="{{ route('admin.roles.destroy', $role) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
                                                @endunless
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
