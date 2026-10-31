<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'default_currency' => strtoupper((string) $this->input('default_currency')),
        ]);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'rows_per_page' => ['required', Rule::in([10, 15, 25, 50])],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Le nom de l\'application est obligatoire.',
            'support_email.email' => 'L\'e-mail de contact n\'est pas valide.',
            'default_currency.size' => 'La devise par défaut doit contenir 3 lettres.',
            'sync_interval_minutes.min' => 'L\'intervalle minimum est de 5 minutes.',
        ];
    }
}
