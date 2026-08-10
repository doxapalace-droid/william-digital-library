<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHighlightRequest extends FormRequest
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
                'required',
                'integer',
                'min:1',
            ],

            'location' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'selected_text' => [
                'required',
                'string',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'color' => [
                'required',
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