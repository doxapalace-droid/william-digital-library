<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReaderPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'theme' => [
                'sometimes',
                'string',
                Rule::in([
                    'light',
                    'dark',
                    'sepia',
                ]),
            ],

            'font_size' => [
                'sometimes',
                'integer',
                'min:12',
                'max:32',
            ],

            'font_family' => [
                'sometimes',
                'string',
                Rule::in([
                    'serif',
                    'sans-serif',
                    'monospace',
                ]),
            ],

            'line_spacing' => [
                'sometimes',
                'numeric',
                'min:1.00',
                'max:3.00',
            ],

            'reading_mode' => [
                'sometimes',
                'string',
                Rule::in([
                    'paginated',
                    'scroll',
                ]),
            ],
        ];
    }
}