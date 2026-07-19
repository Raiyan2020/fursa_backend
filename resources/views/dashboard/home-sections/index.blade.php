@extends('dashboard.layout.main')
@section('title', __('home_sections'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('home_sections') }}</h4></div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('slug') }}</th>
                                        <th>{{ __('title_en') }}</th>
                                        <th>{{ __('title_ar') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sections as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td><code>{{ $row->slug }}</code></td>
                                            <td>{{ $row->title_en }}</td>
                                            <td>{{ $row->title_ar }}</td>
                                            <td>
                                                <a class="btn btn-warning" href="{{ route('admin.home-sections.edit', $row) }}"><i class="feather icon-edit"></i></a>
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
