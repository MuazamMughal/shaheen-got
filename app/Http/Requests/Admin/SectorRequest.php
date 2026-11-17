<?php

namespace App\Http\Requests\Admin;

use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sectors.manage');
    }

    protected function prepareForValidation(): void
    {
        // Sector names are shown uppercase everywhere (LAHORE-JEDDAH-LAHORE).
        $this->merge([
            'origin' => mb_strtoupper(trim((string) $this->origin)),
            'destination' => mb_strtoupper(trim((string) $this->destination)),
            'name' => mb_strtoupper(trim((string) $this->name)),
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'origin' => ['required', 'string', 'max:80'],
            'destination' => ['required', 'string', 'max:80'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('sectors', 'name')
                    ->where('category_id', $this->input('category_id'))
                    ->ignore($this->route('sector')),
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This sector already exists in the selected category.'];
    }
}
