<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'ordered_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Le nom du client est obligatoire.',
            'total_amount.numeric' => 'Le montant total doit être un nombre.',
            'ordered_at.date' => 'La date de commande n\'est pas valide.',
        ];
    }
}
