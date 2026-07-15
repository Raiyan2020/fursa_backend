@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('entities') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.entities.update', $entity) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('dashboard.entities.form')
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
