<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::query()
            ->where('guard_name', AdminPermissions::GUARD)
            ->withCount('roles')
            ->orderBy('name')
            ->get();

        return view('dashboard.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('dashboard.permissions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9\.\-]+$/',
                Rule::unique('permissions', 'name')->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD)),
            ],
        ]);

        Permission::create([
            'name' => $data['name'],
            'guard_name' => AdminPermissions::GUARD,
        ]);
        added();

        return redirect()->route('admin.permissions.index');
    }

    public function edit(Permission $permission)
    {
        abort_unless($permission->guard_name === AdminPermissions::GUARD, 404);

        return view('dashboard.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        abort_unless($permission->guard_name === AdminPermissions::GUARD, 404);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9\.\-]+$/',
                Rule::unique('permissions', 'name')
                    ->where(fn ($q) => $q->where('guard_name', AdminPermissions::GUARD))
                    ->ignore($permission->id),
            ],
        ]);

        $permission->update(['name' => $data['name']]);
        updated();

        return redirect()->route('admin.permissions.index');
    }

    public function destroy(Permission $permission)
    {
        abort_unless($permission->guard_name === AdminPermissions::GUARD, 404);

        $permission->delete();
        deleted();

        return back();
    }
}
