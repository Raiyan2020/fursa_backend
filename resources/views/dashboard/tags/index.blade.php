@extends('dashboard.layout.main')
@section('title', __('tags'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('tags') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.tags.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('choice type') }}</th>
                                        <th>{{ __('value_en') }}</th>
                                        <th>{{ __('value_ar') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tags as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td>{{ $row->choiceType?->name }}</td>
                                            <td>{{ $row->value_en }}</td>
                                            <td>{{ $row->value_ar }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.tags.edit', $row) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.tags.destroy', $row) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
