<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'currency' => strtoupper((string) $this->input('currency')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $countryId = $this->route('country')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('countries', 'code')->ignore($countryId)],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du pays est obligatoire.',
            'code.required' => 'Le code du pays est obligatoire.',
            'code.size' => 'Le code du pays doit contenir 2 lettres.',
            'code.alpha' => 'Le code du pays doit contenir uniquement des lettres.',
            'code.unique' => 'Ce code pays est déjà utilisé.',
            'currency.required' => 'La devise est obligatoire.',
            'currency.size' => 'La devise doit contenir 3 lettres, par exemple MAD.',
        ];
    }
}
