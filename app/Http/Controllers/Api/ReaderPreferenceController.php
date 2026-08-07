<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReaderPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReaderPreferenceController extends Controller
{
    /**
     * Return the authenticated user's reader preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $preference = ReaderPreference::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'data' => $preference ? [
                'font_size' => $preference->font_size,
                'font_family' => $preference->font_family,
                'theme' => $preference->theme,
                'line_spacing' => $preference->line_spacing,
            ] : null,
        ]);
    }

    /**
     * Create or update the authenticated user's
     * reader preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'font_size' => [
                'required',
                'integer',
                'min:12',
                'max:36',
            ],

            'font_family' => [
                'required',
                'string',
                Rule::in([
                    'serif',
                    'sans-serif',
                ]),
            ],

            'theme' => [
                'required',
                'string',
                Rule::in([
                    'light',
                    'dark',
                    'sepia',
                ]),
            ],

            'line_spacing' => [
                'required',
                'numeric',
                'min:1',
                'max:3',
            ],
        ]);

        $preference = ReaderPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
            ],
            [
                'font_size' => $validated['font_size'],
                'font_family' => $validated['font_family'],
                'theme' => $validated['theme'],
                'line_spacing' => $validated['line_spacing'],
            ]
        );

        return response()->json([
            'data' => [
                'font_size' => $preference->font_size,
                'font_family' => $preference->font_family,
                'theme' => $preference->theme,
                'line_spacing' => $preference->line_spacing,
            ],
        ]);
    }
}