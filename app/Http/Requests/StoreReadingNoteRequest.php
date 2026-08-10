<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingNoteRequest extends FormRequest
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
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Position
            |--------------------------------------------------------------------------
            |
            | current_page supports PDF/page-based books.
            |
            */

            'current_page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Reader location
            |--------------------------------------------------------------------------
            |
            | Supports EPUB and other reader formats.
            |
            */

            'location' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Note content
            |--------------------------------------------------------------------------
            */

            'note' => [
                'required',
                'string',
                'max:10000',
            ],
        ];
    }
}