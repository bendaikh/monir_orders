<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'orders.required' => 'Sélectionnez au moins une commande.',
            'orders.min' => 'Sélectionnez au moins une commande.',
        ];
    }
}
