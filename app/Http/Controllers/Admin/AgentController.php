<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    private const SORTABLE = ['name', 'created_at', 'last_login_at'];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), array_column(UserStatus::cases(), 'value'), true)
            ? $request->query('status') : 'pending';
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $agents = User::ofType(UserType::Agent)
            ->with('agency')
            ->where('status', $status)
            ->when($request->query('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('agency', fn ($q) => $q->where('agency_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")));
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => $this->row($u));

        $counts = User::ofType(UserType::Agent)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Admin/Agents/Index', [
            'agents' => $agents,
            'counts' => collect(UserStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'filters' => ['status' => $status, ...$request->only('search', 'sort', 'direction')],
        ]);
    }

    public function show(Request $request, User $agent): Response
    {
        $agent->load('agency', 'approver:id,name');
        $bookings = $request->user()->can('bookings.view')
            ? BookingResource::collection(Booking::where('agent_id', $agent->id)
                ->with(['leadPassenger', 'flight.sector:id,name'])->latest('id')->limit(20)->get())->resolve($request)
            : [];

        return Inertia::render('Admin/Agents/Show', [
            'agent' => [
                ...$this->row($agent),
                'approved_by' => $agent->approver?->name,
                'approved_at' => $agent->approved_at?->format('d M Y, h:i A'),
                'address' => $agent->agency?->address,
                'notification_emails' => $agent->agency?->notification_emails ?? [],
            ],
            'bookings' => $bookings,
            'bookingCount' => Booking::where('agent_id', $agent->id)->count(),
        ]);
    }

    public function update(Request $request, User $agent): RedirectResponse
    {
        $request->merge(['whatsapp' => $request->whatsapp ? preg_replace('/\D/', '', $request->whatsapp) : null]);

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($agent->id)],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'digits_between:10,15'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $agent->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']]);
        $agent->agency()->updateOrCreate([], [
            'agency_name' => $data['agency_name'],
            'owner_name' => $data['name'],
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Agent details updated.');
    }

    public function approve(Request $request, User $agent): RedirectResponse
    {
        abort_unless(in_array($agent->status, [UserStatus::Pending, UserStatus::Rejected], true), 422, 'Only pending or rejected agents can be approved.');

        $agent->forceFill([
            'status' => UserStatus::Approved,
            'status_reason' => null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        app(Notifier::class)->agentApproved($agent->load('agency'));

        return back()->with('success', ($agent->agency?->agency_name ?? $agent->name).' approved. They have been emailed.');
    }

    public function reject(Request $request, User $agent): RedirectResponse
    {
        abort_unless($agent->status === UserStatus::Pending, 422, 'Only pending agents can be rejected.');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $agent->forceFill(['status' => UserStatus::Rejected, 'status_reason' => $reason])->save();
        app(Notifier::class)->agentRejected($agent->load('agency'));

        return back()->with('success', "{$agent->name} rejected.");
    }

    public function suspend(Request $request, User $agent): RedirectResponse
    {
        abort_unless($agent->status === UserStatus::Approved, 422, 'Only approved agents can be suspended.');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $agent->forceFill(['status' => UserStatus::Suspended, 'status_reason' => $reason])->save();

        return back()->with('success', "{$agent->name} suspended and signed out.");
    }

    public function reactivate(User $agent): RedirectResponse
    {
        abort_unless($agent->status === UserStatus::Suspended, 422, 'Only suspended agents can be reactivated.');

        $agent->forceFill(['status' => UserStatus::Approved, 'status_reason' => null])->save();

        return back()->with('success', "{$agent->name} reactivated.");
    }

    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'status' => $u->status->value,
            'status_label' => $u->status->label(),
            'status_reason' => $u->status_reason,
            'agency_name' => $u->agency?->agency_name,
            'city' => $u->agency?->city,
            'whatsapp' => $u->agency?->whatsapp,
            'logo_url' => $u->agency?->logo_url,
            'registered_at' => $u->created_at->format('d M Y'),
            'registered_ago' => $u->created_at->diffForHumans(),
            'last_login_at' => $u->last_login_at?->diffForHumans(),
        ];
    }
}
