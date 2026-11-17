<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AirlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('airlines.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['iata_code' => $this->iata_code ? mb_strtoupper(trim($this->iata_code)) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('airlines', 'name')->ignore($this->route('airline'))],
            'iata_code' => ['nullable', 'string', 'alpha_num', 'between:2,3', Rule::unique('airlines', 'iata_code')->ignore($this->route('airline'))],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['iata_code' => 'IATA code'];
    }
}
