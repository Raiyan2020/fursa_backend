@extends('dashboard.layout.main')
@section('title', __('notifications'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('notifications') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('send new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('title (en)') }}</th>
                                        <th>{{ __('title (ar)') }}</th>
                                        <th>{{ __('message (en)') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($notifications as $notification)
                                        <tr>
                                            <td>{{ $notification->id }}</td>
                                            <td>{{ $notification->title_en }}</td>
                                            <td>{{ $notification->title_ar }}</td>
                                            <td>{{ $notification->message_en }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-danger" data-href="{{ route('admin.notifications.destroy', $notification) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
