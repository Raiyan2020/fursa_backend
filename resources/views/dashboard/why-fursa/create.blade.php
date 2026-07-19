@extends('dashboard.layout.main')
@section('title', __('add new'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('why_fursa') }} - {{ __('add new') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.why-fursa.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @include('dashboard.why-fursa.form')
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
