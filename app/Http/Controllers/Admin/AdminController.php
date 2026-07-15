<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::query()
            ->withoutSuperAdmin()
            ->with('roles')
            ->latest()
            ->get();

        return view('dashboard.admins.index', compact('admins'));
    }

    public function create()
    {
        $roles = $this->assignableRoles();

        return view('dashboard.admins.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:admins,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active');
        $roleIds = $data['roles'] ?? [];
        unset($data['roles']);

        $admin = Admin::create($data);
        $admin->syncRoles(
            Role::query()
                ->where('guard_name', AdminPermissions::GUARD)
                ->whereIn('id', $roleIds)
                ->get()
        );
        added();

        return redirect()->route('admin.admins.index');
    }

    public function edit(Admin $admin)
    {
        $this->guardSuperAdmin($admin);
        $roles = $this->assignableRoles();
        $selectedRoles = old('roles', $admin->roles->pluck('id')->all());

        return view('dashboard.admins.edit', compact('admin', 'roles', 'selectedRoles'));
    }

    public function update(Request $request, Admin $admin)
    {
        $this->guardSuperAdmin($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($admin->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $roleIds = $data['roles'] ?? [];
        unset($data['roles']);

        $admin->update($data);
        $admin->syncRoles(
            Role::query()
                ->where('guard_name', AdminPermissions::GUARD)
                ->whereIn('id', $roleIds)
                ->get()
        );
        updated();

        return redirect()->route('admin.admins.index');
    }

    public function destroy(Admin $admin)
    {
        $this->guardSuperAdmin($admin);

        $admin->delete();
        deleted();

        return back();
    }

    protected function guardSuperAdmin(Admin $admin): void
    {
        abort_if((int) $admin->id === 1, 403);
    }

    protected function assignableRoles()
    {
        return Role::query()
            ->where('guard_name', AdminPermissions::GUARD)
            ->orderBy('name')
            ->get();
    }
}
