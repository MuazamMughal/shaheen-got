<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Support\Permissions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsActivity;

    /**
     * Mass assignable. `type`, `status` and approval fields are deliberately
     * excluded so a request can never promote itself; set them explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function agency(): HasOne
    {
        return $this->hasOne(Agency::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->type === UserType::SuperAdmin;
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isAgent(): bool
    {
        return $this->type === UserType::Agent;
    }

    /** Super admin or staff: may enter /admin. */
    public function isAdminSide(): bool
    {
        return $this->type?->isAdminSide() ?? false;
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::Approved;
    }

    /**
     * Price visibility. Agents always see the fares they book at; on the admin
     * side only super admins and staff whose role grants `prices.view`.
     */
    public function canViewPrices(): bool
    {
        if ($this->isAgent()) {
            return true;
        }

        return $this->can(Permissions::PRICES_VIEW);
    }

    /** Permission names for the UI. Super admin implicitly has all of them. */
    public function permissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permissions::all();
        }

        if ($this->isStaff()) {
            return $this->getAllPermissions()->pluck('name')->values()->all();
        }

        return [];
    }

    /** Where this user lands after login. */
    public function homeRoute(): string
    {
        if ($this->isAdminSide()) {
            return route('admin.dashboard', absolute: false);
        }

        return $this->isApproved()
            ? route('agent.dashboard', absolute: false)
            : route('agent.pending', absolute: false);
    }

    public function scopeOfType(Builder $query, UserType $type): Builder
    {
        return $query->where('type', $type);
    }

    protected function activityAttributes(): array
    {
        return ['name', 'email', 'phone', 'type', 'status', 'status_reason'];
    }
}
