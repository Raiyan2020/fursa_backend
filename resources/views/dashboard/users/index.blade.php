@extends('dashboard.layout.main')
@section('title', __('users'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('users') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('name') }}</th>
                                        <th>{{ __('email') }}</th>
                                        <th>{{ __('phone') }}</th>
                                        <th>{{ __('user type') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $u)
                                        <tr>
                                            <td>{{ $u->id }}</td>
                                            <td>{{ $u->first_name . ' ' . $u->last_name }}</td>
                                            <td>{{ $u->email }}</td>
                                            <td>{{ $u->phone_number }}</td>
                                            <td>{{ $u->user_type?->label() ?? '-' }}</td>
                                            <td>
                                                <span class="badge badge-light-{{ $u->is_banned ? 'danger' : 'success' }}">{{ $u->is_banned ? __('banned') : __('active') }}</span>
                                            </td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.users.show', $u) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.users.edit', $u) }}"><i class="feather icon-edit"></i></a>
                                                @if ($u->is_banned)
                                                    <a class="btn btn-success" href="#" onclick="forsaConfirmPost('{{ route('admin.users.unban', $u) }}','{{ __('Unban this user ?') }}')"><i class="feather icon-unlock"></i></a>
                                                @else
                                                    <a class="btn btn-danger" href="#" onclick="forsaReject('{{ route('admin.users.ban', $u) }}')"><i class="feather icon-slash"></i></a>
                                                @endif
                                                <a class="btn btn-danger" data-href="{{ route('admin.users.destroy', $u) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
