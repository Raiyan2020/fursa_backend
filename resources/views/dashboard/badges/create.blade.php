@extends('dashboard.layout.main')
@section('title', __('add new'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('badges') }} - {{ __('add new') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.badges.store') }}" method="POST">
                        @csrf
                        @include('dashboard.badges.form')
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
