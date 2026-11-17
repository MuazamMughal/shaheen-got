<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'totalPermissions' => count(Permissions::all()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'groups' => Permissions::grouped(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
            $role->syncPermissions($request->validated('permissions', []));
            activity('role')->performedOn($role)->event('created')
                ->withProperties(['attributes' => ['name' => $role->name, 'permissions' => $request->validated('permissions', [])]])
                ->log('created');
        });

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
                'users_count' => $role->users()->count(),
            ],
            'groups' => Permissions::grouped(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $old = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions', []));
            $new = ['name' => $role->name, 'permissions' => collect($request->validated('permissions', []))->sort()->values()->all()];
            activity('role')->performedOn($role)->event('updated')->withProperties(['old' => $old, 'attributes' => $new])->log('updated');
        });

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to staff members. Reassign them before deleting it.');
        }

        activity('role')->event('deleted')->withProperties(['old' => ['name' => $role->name]])->log('deleted');
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
