<div class="row">
    <div class="col-md-6 mb-1">
        <label>{{ __('name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $permission->name ?? '') }}"
               placeholder="module.action" required>
        <small class="text-muted">{{ __('Example: users.view or banners.create') }}</small>
        @error('name') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mt-1">
    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">{{ __('back') }}</a>
</div>
