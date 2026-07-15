@extends('dashboard.layout.main')
@section('title', __('edit'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('banners') }} - {{ __('edit') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.banners.update', $bannerImage) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        @include('dashboard.banners.form')
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
