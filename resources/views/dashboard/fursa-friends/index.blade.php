@extends('dashboard.layout.main')
@section('title', __('fursa friends'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('fursa friends') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.fursa-friends.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('email') }}</th>
                                        <th>{{ __('added by') }}</th>
                                        <th>{{ __('created at') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fursaFriends as $f)
                                        <tr>
                                            <td>{{ $f->id }}</td>
                                            <td>{{ $f->user?->email }}</td>
                                            <td>{{ $f->addedBy?->email ?? '-' }}</td>
                                            <td>{{ $f->created_at }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-danger" data-href="{{ route('admin.fursa-friends.destroy', $f) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
