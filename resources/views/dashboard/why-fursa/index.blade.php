@extends('dashboard.layout.main')
@section('title', __('why_fursa'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('why_fursa') }}</h4></div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.why-fursa.create') }}" class="btn btn-primary mb-2">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('icon') }}</th>
                                        <th>{{ __('title_en') }}</th>
                                        <th>{{ __('title_ar') }}</th>
                                        <th>{{ __('sort_order') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td>
                                                @if ($row->icon)
                                                    <img src="{{ getimg($row->icon) }}" alt="" height="40">
                                                @endif
                                            </td>
                                            <td>{{ $row->title_en }}</td>
                                            <td>{{ $row->title_ar }}</td>
                                            <td>{{ $row->sort_order }}</td>
                                            <td>
                                                <a class="btn btn-warning" href="{{ route('admin.why-fursa.edit', $row) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.why-fursa.destroy', $row) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
