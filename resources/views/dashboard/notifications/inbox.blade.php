@extends('dashboard.layout.main')
@section('title', __('admin.sidebar.notifications'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('admin.sidebar.notifications') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-primary mb-2 waves-effect waves-light">
                            {{ __('sent broadcasts') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('title (en)') }}</th>
                                        <th>{{ __('message (en)') }}</th>
                                        <th>{{ __('date') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($adminNotifications as $adminNotification)
                                        <tr class="{{ $adminNotification->is_read ? '' : 'font-weight-bold' }}">
                                            <td>{{ $adminNotification->notification->title_en }}</td>
                                            <td>{{ $adminNotification->notification->message_en }}</td>
                                            <td>{{ $adminNotification->created_at->format('Y-m-d H:i') }}</td>
                                            <td class="product-action">
                                                @if ($adminNotification->notification->link)
                                                    <a class="btn btn-primary" href="{{ $adminNotification->notification->link }}">{{ __('view') }}</a>
                                                @endif
                                                @unless ($adminNotification->is_read)
                                                    <form method="POST" action="{{ route('admin.notifications.mark-read', $adminNotification) }}" style="display:inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-secondary">{{ __('mark as read') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">{{ __('no data found') }}</td>
                                        </tr>
                                    @endforelse
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
