@extends('dashboard.layout.main')
@section('title', __('faqs'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('faqs') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary mb-2 waves-effect waves-light">
                            <i class="fas fa-plus"></i>&nbsp; {{ __('add new') }}
                        </a>
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('question_en') }}</th>
                                        <th>{{ __('question_ar') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($faqs as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td>{{ $row->question_en }}</td>
                                            <td>{{ $row->question_ar }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.faqs.edit', $row) }}"><i class="feather icon-edit"></i></a>
                                                <a class="btn btn-danger" data-href="{{ route('admin.faqs.destroy', $row) }}" onclick="delete_form(this)"><i class="feather icon-trash"></i></a>
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
