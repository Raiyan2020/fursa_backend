@extends('dashboard.layout.main')
@section('title', __('add new'))
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">{{ __('fursa friends') }} - {{ __('add new') }}</h4></div>
                <div class="card-content"><div class="card-body">
                    <form action="{{ route('admin.fursa-friends.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('volunteer') }} <span class="text-danger">*</span></label>
                                <select name="user_id" class="form-control select2" required>
                                    <option value="">{{ __('select') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>{{ $user->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            <a href="{{ route('admin.fursa-friends.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
                        </div>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
