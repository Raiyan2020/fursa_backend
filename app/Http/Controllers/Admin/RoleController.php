<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->where('guard_name', AdminPermissions::GUARD)
            ->withCount('permissions')
            ->withCount('users')
            ->latest()
            ->get();

        return view('dashboard.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissionGroups = AdminPermissions::grouped();
        $selectedPermissions = old('permissions', []);

        return view('dashboard.roles.create', compact('permissionGroups', 'selectedPermissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => AdminPermissions::GUARD,
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        added();

        return redirect()->route('admin.roles.index');
    }

    public function edit(Role $role)
    {
        abort_unless($role->guard_name === AdminPermissions::GUARD, 404);

        $permissionGroups = AdminPermissions::grouped();
        $selectedPermissions = old('permissions', $role->permissions->pluck('name')->all());

        return view('dashboard.roles.edit', compact('role', 'permissionGroups', 'selectedPermissions'));
    }

    public function update(Request $request, Role $role)
    {
        abort_unless($role->guard_name === AdminPermissions::GUARD, 404);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD))
                    ->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
        ]);

        if ($role->isSuperAdmin() && $data['name'] !== Role::SUPER_ADMIN) {
            return back()->withErrors(['name' => __('Cannot rename the super admin role.')])->withInput();
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        updated();

        return redirect()->route('admin.roles.index');
    }

    public function destroy(Role $role)
    {
        abort_unless($role->guard_name === AdminPermissions::GUARD, 404);
        abort_if($role->isSuperAdmin(), 403);

        $role->delete();
        deleted();

        return back();
    }
}
