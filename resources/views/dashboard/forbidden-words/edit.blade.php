@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('forbidden words') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.forbidden-words.update', $forbiddenWord) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('dashboard.forbidden-words.form')
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
