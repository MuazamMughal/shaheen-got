<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    private const SORTABLE = ['name', 'email', 'status', 'last_login_at', 'created_at'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $staff = User::ofType(UserType::Staff)
            ->with('roles:id,name')
            ->when($request->query('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roles', fn ($q) => $q->where('id', $role)))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status->value,
                'role' => $u->roles->first()?->only('id', 'name'),
                'last_login_at' => $u->last_login_at?->diffForHumans(),
                'created_at' => $u->created_at->format('d M Y'),
            ]);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
            'roles' => $this->roleOptions(),
            'filters' => $request->only('search', 'role', 'status', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Staff/Form', [
            'staff' => null,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = new User($request->safe()->only('name', 'email', 'phone', 'password'));
            $user->forceFill([
                'type' => UserType::Staff,
                'status' => UserStatus::from($request->validated('status')),
                'email_verified_at' => now(),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            $user->syncRoles([$role = Role::findById($request->validated('role_id'), 'web')]);
            activity('user')->performedOn($user)->event('updated')->withProperties(['attributes' => ['role' => $role->name]])->log('role assigned');
        });

        return redirect()->route('admin.staff.index')->with('success', 'Staff member created.');
    }

    public function edit(Request $request, User $staff): Response
    {
        abort_if($staff->is($request->user()), 403, 'Use My Profile to edit your own account.');

        return Inertia::render('Admin/Staff/Form', [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'status' => $staff->status->value,
                'role_id' => $staff->roles->first()?->id,
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        DB::transaction(function () use ($request, $staff) {
            $staff->fill($request->safe()->only('name', 'email', 'phone'));
            if ($request->filled('password')) {
                $staff->password = $request->validated('password');
            }
            $staff->forceFill(['status' => UserStatus::from($request->validated('status'))])->save();
            $oldRole = $staff->roles->first()?->name;
            $staff->syncRoles([$role = Role::findById($request->validated('role_id'), 'web')]);
            if ($oldRole !== $role->name) {
                activity('user')->performedOn($staff)->event('updated')
                    ->withProperties(['old' => ['role' => $oldRole], 'attributes' => ['role' => $role->name]])->log('role changed');
            }
        });

        return redirect()->route('admin.staff.index')->with('success', 'Staff member updated.');
    }

    /** Activate / deactivate without opening the form. */
    public function toggleStatus(Request $request, User $staff): RedirectResponse
    {
        abort_if($staff->is($request->user()), 403, 'You cannot deactivate your own account.');

        $next = $staff->status === UserStatus::Suspended ? UserStatus::Approved : UserStatus::Suspended;
        $staff->forceFill(['status' => $next])->save();

        return back()->with('success', $next === UserStatus::Approved ? 'Staff member activated.' : 'Staff member deactivated.');
    }

    private function roleOptions(): array
    {
        return Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all();
    }
}
