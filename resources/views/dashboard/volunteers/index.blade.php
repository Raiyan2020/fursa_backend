@extends('dashboard.layout.main')
@section('title', __('volunteers'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('volunteers') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('email') }}</th>
                                        <th>{{ __('nickname') }}</th>
                                        <th>{{ __('gender') }}</th>
                                        <th>{{ __('public') }}</th>
                                        <th>{{ __('verified') }}</th>
                                        <th>{{ __('total volunteer hours') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($volunteers as $v)
                                        <tr>
                                            <td>{{ $v->id }}</td>
                                            <td>{{ $v->user?->email }}</td>
                                            <td>{{ $v->nickname }}</td>
                                            <td>{{ $v->gender?->value_en }}</td>
                                            <td>
                                                <span class="badge badge-light-{{ $v->is_public ? 'success' : 'secondary' }}">{{ $v->is_public ? __('yes') : __('no') }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-light-{{ $v->is_verified ? 'success' : 'secondary' }}">{{ $v->is_verified ? __('yes') : __('no') }}</span>
                                            </td>
                                            <td>{{ $v->total_volunteer_hours }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-info" href="{{ route('admin.volunteers.show', $v) }}"><i class="feather icon-eye"></i></a>
                                                <a class="btn btn-warning" href="{{ route('admin.volunteers.edit', $v) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.volunteers.destroy', $v) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
