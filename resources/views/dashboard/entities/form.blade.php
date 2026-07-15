<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('company name') }}</label>
        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $entity->company_name ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('nickname') }}</label>
        <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $entity->nickname ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('registration number') }}</label>
        <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number', $entity->registration_number ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('license number') }}</label>
        <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $entity->license_number ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('status') }} <span class="text-danger">*</span></label>
        @php $currentStatus = old('organization_status', $entity->organization_status?->value ?? ''); @endphp
        <select name="organization_status" class="form-control" required>
            @foreach (\App\Enums\ApprovalStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ $currentStatus === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.entities.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
