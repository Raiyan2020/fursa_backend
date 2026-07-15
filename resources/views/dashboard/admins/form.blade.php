<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $admin->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('email') }} <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $admin->email ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('phone') }}</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $admin->phone ?? '') }}">
    </div>
    <div class="col-md-6 mb-1">
        <label>{{ __('password') }} @if(!isset($admin))<span class="text-danger">*</span>@else <small class="text-muted">({{ __('leave blank to keep') }})</small>@endif</label>
        <input type="password" name="password" class="form-control" {{ isset($admin) ? '' : 'required' }}>
    </div>
    <div class="col-md-6 mb-1">
        <label class="d-block">{{ __('status') }}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $admin->is_active ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_active">{{ __('active') }}</label>
        </div>
    </div>
    <div class="col-md-12 mb-1">
        <label>{{ __('roles') }}</label>
        @php $selected = collect($selectedRoles ?? old('roles', [])); @endphp
        <select name="roles[]" class="form-control select2" multiple>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" {{ $selected->contains($role->id) ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
