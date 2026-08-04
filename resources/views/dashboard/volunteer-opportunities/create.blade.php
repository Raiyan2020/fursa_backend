@extends('dashboard.layout.main')
@section('title', __('add new'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('volunteer opportunities') }} - {{ __('add new') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.volunteer-opportunities.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @include('dashboard.volunteer-opportunities.form', ['opportunity' => null])
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
