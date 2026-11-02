<?php

namespace App\Http\Requests;

use App\Support\OrderNormalizer;
use App\Support\SpreadsheetId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'phone_digits' => $this->input('phone_digits', 8),
            'date_format' => $this->input('date_format') ?: 'd/m/Y H:i:s',
            'decimal_format' => $this->input('decimal_format') ?: 'auto',
        ]);
    }

    public function rules(): array
    {
        $countryId = $this->route('country')?->id;
        $serviceId = $this->route('service')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('services', 'name')->where('country_id', $countryId)->ignore($serviceId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'source.spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'source.worksheet_name' => ['nullable', 'string', 'max:150'],
            'source.spreadsheet_id' => ['nullable', 'string', 'max:200'],
            'destination.spreadsheet_url' => ['nullable', 'string', 'max:500'],
            'destination.worksheet_name' => ['nullable', 'string', 'max:150'],
            'destination.spreadsheet_id' => ['nullable', 'string', 'max:200'],
            'mappings.source.*' => ['nullable', 'string', 'max:120'],
            'mappings.destination.*' => ['nullable', 'string', 'max:120'],
            'mappings.source.reference' => ['required_without:reference_prefix', 'nullable', 'string', 'max:120'],
            'duplicate_key' => ['nullable', Rule::in(array_keys(OrderNormalizer::DUPLICATE_MODES))],
            'phone_digits' => ['required', 'integer', 'min:6', 'max:15'],
            'date_format' => ['required', 'string', 'max:40'],
            'decimal_format' => ['required', Rule::in(array_keys(OrderNormalizer::DECIMAL_FORMATS))],
            'exclude_products' => ['nullable', 'string', 'max:500'],
            'reference_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'mappings.source.customer_name' => ['required', 'string', 'max:120'],
            'mappings.destination.reference' => ['required', 'string', 'max:120'],
            'mappings.destination.customer_name' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (['source' => 'source', 'destination' => 'destination'] as $direction => $label) {
                $url = $this->input($direction.'.spreadsheet_url');
                $explicit = $this->input($direction.'.spreadsheet_id');
                $worksheet = trim((string) $this->input($direction.'.worksheet_name'));
                $hasInput = filled($url) || filled($explicit) || $worksheet !== '';
                $id = SpreadsheetId::from(is_string($url) ? $url : null)
                    ?: SpreadsheetId::from(is_string($explicit) ? $explicit : null);

                if ($hasInput && $id === null) {
                    $validator->errors()->add(
                        $direction.'.spreadsheet_url',
                        'Indiquez une URL Google Sheets valide ou un identifiant de tableur.',
                    );
                }

                if ($id !== null && $worksheet === '') {
                    $validator->errors()->add(
                        $direction.'.worksheet_name',
                        'Le nom de l\'onglet est obligatoire lorsque le tableur est renseigné.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du service est obligatoire.',
            'name.unique' => 'Un service porte déjà ce nom dans ce pays.',
            'mappings.source.reference.required_without' => 'Indiquez la colonne source de la référence, ou un préfixe pour générer les identifiants.',
            'reference_prefix.regex' => 'Le préfixe ne peut contenir que des lettres, chiffres, tirets et underscores.',
            'mappings.source.customer_name.required' => 'La colonne source du nom du client est obligatoire.',
            'mappings.destination.reference.required' => 'La colonne de destination de la référence est obligatoire.',
            'mappings.destination.customer_name.required' => 'La colonne de destination du nom du client est obligatoire.',
        ];
    }
}
