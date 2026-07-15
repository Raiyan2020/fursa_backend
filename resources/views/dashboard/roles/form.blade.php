<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $role->name ?? '') }}"
               placeholder="content-manager" {{ isset($role) && $role->isSuperAdmin() ? 'readonly' : 'required' }}>
        <small class="text-muted">{{ __('Use lowercase letters, numbers and dashes only') }}</small>
        @error('name') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mt-2 mb-1 d-flex align-items-center justify-content-between flex-wrap gap-1">
    <h5 class="mb-0">{{ __('permissions') }}</h5>
    <div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="checkAllPermissions">{{ __('Select all') }}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="uncheckAllPermissions">{{ __('Clear all') }}</button>
    </div>
</div>

@php $selected = collect($selectedPermissions ?? []); @endphp

<div class="row">
    @foreach ($permissionGroups as $module => $permissions)
        <div class="col-md-6 col-xl-4 mb-2">
            <div class="border rounded p-1 h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong>{{ __('admin.permission_modules.'.$module) !== 'admin.permission_modules.'.$module ? __('admin.permission_modules.'.$module) : $module }}</strong>
                    <button type="button" class="btn btn-xs btn-light module-toggle" data-module="{{ $module }}">{{ __('Toggle') }}</button>
                </div>
                @foreach ($permissions as $permission)
                    <div class="custom-control custom-checkbox mb-50">
                        <input type="checkbox"
                               class="custom-control-input permission-checkbox module-{{ $module }}"
                               id="perm_{{ str_replace('.', '_', $permission) }}"
                               name="permissions[]"
                               value="{{ $permission }}"
                               {{ $selected->contains($permission) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="perm_{{ str_replace('.', '_', $permission) }}">{{ $permission }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>

@push('scripts')
<script>
    document.getElementById('checkAllPermissions')?.addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(function (el) { el.checked = true; });
    });
    document.getElementById('uncheckAllPermissions')?.addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(function (el) { el.checked = false; });
    });
    document.querySelectorAll('.module-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var module = btn.getAttribute('data-module');
            var boxes = Array.from(document.querySelectorAll('.module-' + module));
            var allChecked = boxes.every(function (el) { return el.checked; });
            boxes.forEach(function (el) { el.checked = !allChecked; });
        });
    });
</script>
@endpush
