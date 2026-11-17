<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaggageTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('baggage.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['label' => mb_strtoupper(trim((string) $this->label))]);
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120', Rule::unique('baggage_types', 'label')->ignore($this->route('baggage_type'))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
