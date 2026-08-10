<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHighlightRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'current_page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'selected_text' => [
                'sometimes',
                'string',
            ],

            'note' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'color' => [
                'sometimes',
                Rule::in([
                    'yellow',
                    'green',
                    'blue',
                    'pink',
                    'orange',
                ]),
            ],
        ];
    }
}