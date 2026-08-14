<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthorController extends Controller
{
    /**
     * Display all active authors.
     *
     * Public endpoint.
     */
    public function index(): JsonResponse
    {
        $authors = Author::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $authors,
        ]);
    }

    /**
     * Store a newly created author.
     *
     * Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:authors,name',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:authors,slug',
            ],

            'bio' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $author = Author::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'bio' => $validated['bio'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Author created successfully.',
            'data' => $author,
        ], 201);
    }

    /**
     * Display a single active author.
     *
     * Public endpoint.
     *
     * Only published books belonging to the author
     * are included in the response.
     */
    public function show(Author $author): JsonResponse
    {
        /*
         * Inactive authors are not publicly accessible.
         */
        abort_unless(
            $author->is_active,
            404,
            'Author not found.'
        );

        $author->load([
            'books' => function ($query) {
                $query
                    ->where('is_published', true)
                    ->orderBy('title');
            },
        ]);

        return response()->json([
            'data' => $author,
        ]);
    }

    /**
     * Update an existing author.
     *
     * Admin only.
     */
    public function update(
        Request $request,
        Author $author
    ): JsonResponse {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('authors', 'name')
                    ->ignore($author->id),
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('authors', 'slug')
                    ->ignore($author->id),
            ],

            'bio' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $author->update($validated);

        return response()->json([
            'message' => 'Author updated successfully.',
            'data' => $author->fresh(),
        ]);
    }

    /**
     * Delete an author.
     *
     * Admin only.
     *
     * Detaches the author from all books before deletion.
     * The books themselves are not deleted.
     */
    public function destroy(Author $author): JsonResponse
    {
        $author->books()->detach();

        $author->delete();

        return response()->json([
            'message' => 'Author deleted successfully.',
        ]);
    }
}