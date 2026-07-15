@extends('dashboard.layout.main')
@section('title', __('banners'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('banners') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('name') }}</th>
                                        <th>{{ __('image') }}</th>
                                        <th>{{ __('banner_url') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($banners as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td>{{ $row->name }}</td>
                                            <td>
                                                @if ($row->image)
                                                    <img src="{{ $row->image }}" width="70" height="70" alt="{{ $row->name }}" style="object-fit:cover;border-radius:8px;">
                                                @else
                                                    <span class="text-muted">{{ __('no image') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $row->banner_url }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.banners.edit', $row) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.banners.destroy', $row) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
