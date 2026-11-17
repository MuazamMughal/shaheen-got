<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Nobody changes their own role/status here (no self-escalation).
        return (bool) $this->user()?->can('staff.manage')
            && $this->route('staff')?->id !== $this->user()->id;
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$staff ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'status' => ['required', Rule::in([UserStatus::Approved->value, UserStatus::Suspended->value])],
        ];
    }

    public function attributes(): array
    {
        return ['role_id' => 'role'];
    }
}
