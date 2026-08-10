<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReaderPreferenceRequest extends FormRequest
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
                'required',
                'string',
                Rule::in([
                    'light',
                    'dark',
                    'sepia',
                ]),
            ],

            'font_size' => [
                'required',
                'integer',
                'min:12',
                'max:32',
            ],

            'font_family' => [
                'required',
                'string',
                Rule::in([
                    'serif',
                    'sans-serif',
                    'monospace',
                ]),
            ],

            'line_spacing' => [
                'required',
                'numeric',
                'min:1.00',
                'max:3.00',
            ],

            'reading_mode' => [
                'required',
                'string',
                Rule::in([
                    'paginated',
                    'scroll',
                ]),
            ],
        ];
    }
}