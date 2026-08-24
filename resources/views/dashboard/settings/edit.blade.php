@extends('dashboard.layout.main')
@section('title', __('settings'))
@section('content')
    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('cycle settings') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('cycle type') }}</label>
                                    <select name="cycle_type" class="form-control">
                                        @foreach (['monthly', 'quarterly', 'semi_annual', 'annual'] as $option)
                                            <option value="{{ $option }}" {{ old('cycle_type', $config->cycle_type) === $option ? 'selected' : '' }}>{{ __($option) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('cycle scope') }}</label>
                                    <select name="cycle_scope" class="form-control">
                                        @foreach (['current', 'previous', 'custom'] as $option)
                                            <option value="{{ $option }}" {{ old('cycle_scope', $config->cycle_scope) === $option ? 'selected' : '' }}>{{ __($option) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('cycle year') }}</label>
                                    <input type="number" name="cycle_year" class="form-control" value="{{ old('cycle_year', $config->cycle_year) }}">
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('cycle index') }}</label>
                                    <input type="number" name="cycle_index" class="form-control" value="{{ old('cycle_index', $config->cycle_index) }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('opportunity settings') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('number of opportunities') }}</label>
                                    <input type="number" name="number_of_opportunities" class="form-control" value="{{ old('number_of_opportunities', $config->number_of_opportunities) }}">
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('time duration') }}</label>
                                    <input type="number" name="time_duration" class="form-control" value="{{ old('time_duration', $config->time_duration) }}">
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('time unit') }}</label>
                                    <select name="time_unit" class="form-control">
                                        @foreach (['days', 'weeks', 'months', 'years'] as $option)
                                            <option value="{{ $option }}" {{ old('time_unit', $config->time_unit) === $option ? 'selected' : '' }}>{{ __($option) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('manual attendance threshold') }}</label>
                                    <input type="number" name="manual_attendance_threshold" class="form-control" value="{{ old('manual_attendance_threshold', $config->manual_attendance_threshold) }}">
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('economic impact rate kwd') }}</label>
                                    <input type="number" step="0.01" min="0" name="economic_impact_rate_kwd" class="form-control" value="{{ old('economic_impact_rate_kwd', $config->economic_impact_rate_kwd) }}">
                                    <small class="text-muted">{{ __('Economic impact = total volunteer hours x this rate') }}</small>
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('check-in window hours') }}</label>
                                    <input type="number" min="0" name="preparation_validity_hours" class="form-control" value="{{ old('preparation_validity_hours', $config->preparation_validity_hours) }}">
                                    <small class="text-muted">{{ __('Hours after the end date during which attendance can still be recorded (72 = 3 days, 168 = 1 week)') }}</small>
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label>{{ __('check-in reminder hours before closing') }}</label>
                                    <input type="number" min="0" name="preparation_reminder_hours_before" class="form-control" value="{{ old('preparation_reminder_hours_before', $config->preparation_reminder_hours_before) }}">
                                </div>
                            </div>
                            <div class="mt-1">
                                <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
