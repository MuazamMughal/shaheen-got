<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Agent account events.
 *  registered → to admins ("approval needed") and to the agent ("received")
 *  approved / rejected → to the agent
 */
class AgentNotification extends BaseNotification
{
    public function __construct(public User $agent, public string $event, string $audience)
    {
        parent::__construct($audience);
    }

    protected function title(): string
    {
        $agency = $this->agent->agency?->agency_name ?? $this->agent->name;

        return match ([$this->event, $this->audience]) {
            ['registered', 'admin'] => "New agent waiting for approval: {$agency}",
            ['registered', 'agent'] => 'We received your registration',
            ['approved', 'agent'] => 'Your agent account is approved',
            ['rejected', 'agent'] => 'Your agent registration was not approved',
            default => 'Account update',
        };
    }

    protected function body(): string
    {
        $a = $this->agent;
        $agency = $a->agency;

        return match ([$this->event, $this->audience]) {
            ['registered', 'admin'] => "{$agency?->agency_name} ({$agency?->city}) registered as an agent. Contact: {$a->name}, {$a->phone}, {$a->email}. Please review and approve.",
            ['registered', 'agent'] => 'Thank you for registering '.($agency?->agency_name ?? 'your agency').'. Our team will review your details and email you as soon as your account is approved.',
            ['approved', 'agent'] => 'You can now sign in, browse live group fares and book seats for your customers.',
            ['rejected', 'agent'] => 'Unfortunately we could not approve your registration.'.($a->status_reason ? " Reason: {$a->status_reason}" : '').' Please contact us if you have questions.',
            default => '',
        };
    }

    protected function url(): ?string
    {
        return match ([$this->event, $this->audience]) {
            ['registered', 'admin'] => route('admin.agents.show', $this->agent),
            ['approved', 'agent'] => route('agent.groups.index'),
            default => null,
        };
    }

    protected function actionText(): string
    {
        return $this->audience === 'admin' ? 'Review agent' : 'Browse group fares';
    }

    protected function tone(): string
    {
        return match ($this->event) {
            'approved' => 'green',
            'rejected' => 'red',
            default => 'amber',
        };
    }
}
