@extends('dashboard.layout.main')
@section('title', __('email templates'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('email templates') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body card-dashboard">
                        <div class="table-responsive">
                            <table class="dataex-html5-selectors table">
                                <thead>
                                    <tr>
                                        <th>{{ __('id') }}</th>
                                        <th>{{ __('name') }}</th>
                                        <th>{{ __('subject') }}</th>
                                        <th>{{ __('language') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($templates as $template)
                                        <tr>
                                            <td>{{ $template->id }}</td>
                                            <td>{{ $template->name }}</td>
                                            <td>{{ $template->subject }}</td>
                                            <td>{{ $template->language }}</td>
                                            <td class="product-action">
                                                <a class="btn btn-warning" href="{{ route('admin.email-templates.edit', $template) }}"><i class="feather icon-edit"></i></a>
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
