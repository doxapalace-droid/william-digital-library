<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    /**
     * Display a listing of published books.
     */
    public function index(Request $request)
    {
        $books = Book::query()
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('is_published', true)

            /*
            |--------------------------------------------------------------------------
            | Optional category filter
            |--------------------------------------------------------------------------
            |
            | Example:
            | /api/books?category=new-creation-realities
            |
            */
            ->when(
                $request->filled('category'),
                function ($query) use ($request) {
                    $query->whereHas('categories', function ($categoryQuery) use ($request) {
                        $categoryQuery
                            ->where('slug', $request->string('category')->toString())
                            ->where('is_active', true);
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Optional featured filter
            |--------------------------------------------------------------------------
            |
            | Example:
            | /api/books?featured=1
            |
            */
            ->when(
                $request->boolean('featured'),
                fn ($query) => $query->where('is_featured', true)
            )

            ->orderByDesc('published_at')
            ->get();

        return response()->json([
            'data' => $books,
        ]);
    }

    /**
     * Store a newly created book.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'author' => ['nullable', 'string', 'max:255'],

            'isbn' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('books', 'isbn'),
            ],

            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],

            'cover_image' => [
                'nullable',
                'image',
                'max:2048',
            ],

            'pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:51200',
            ],

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            |
            | Send category database IDs:
            |
            | categories[] = 1
            | categories[] = 8
            |
            */
            'categories' => [
                'sometimes',
                'array',
            ],

            'categories.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],

            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Extract category IDs
        |--------------------------------------------------------------------------
        */

        $categoryIds = $validated['categories'] ?? [];

        unset($validated['categories']);

        /*
        |--------------------------------------------------------------------------
        | Generate unique slug
        |--------------------------------------------------------------------------
        */

        $validated['slug'] = $this->generateUniqueSlug(
            $validated['title']
        );

        /*
        |--------------------------------------------------------------------------
        | Publication date
        |--------------------------------------------------------------------------
        */

        if ($validated['is_published'] ?? false) {
            $validated['published_at'] = now();
        }

        /*
        |--------------------------------------------------------------------------
        | Store PDF
        |--------------------------------------------------------------------------
        */

        $filename = Str::uuid() . '.pdf';

        $request->file('pdf')->storeAs(
            '',
            $filename,
            'books'
        );

        $validated['pdf_path'] = $filename;

        /*
        |--------------------------------------------------------------------------
        | Never save uploaded file objects directly
        |--------------------------------------------------------------------------
        */

        unset($validated['pdf']);
        unset($validated['cover_image']);

        /*
        |--------------------------------------------------------------------------
        | Create book and attach categories
        |--------------------------------------------------------------------------
        */

        try {

            $book = DB::transaction(function () use (
                $validated,
                $categoryIds
            ) {
                $book = Book::create($validated);

                if (! empty($categoryIds)) {
                    $book->categories()->sync($categoryIds);
                }

                return $book;
            });

        } catch (\Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Remove PDF if database operation fails
            |--------------------------------------------------------------------------
            */

            Storage::disk('books')->delete($filename);

            throw $exception;
        }

        return response()->json([
            'message' => 'Book created successfully.',
            'data' => $book->load(
                'categories:id,uuid,name,slug'
            ),
        ], 201);
    }

    /**
     * Display a published book.
     */
    public function show(string $slug)
    {
        $book = Book::query()
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json([
            'data' => $book,
        ]);
    }

    /**
     * Update the specified book.
     */
    public function update(Request $request, string $uuid)
    {
        $book = Book::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'author' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'isbn' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('books', 'isbn')->ignore($book->id),
            ],

            'price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
            ],

            'cover_image' => [
                'nullable',
                'image',
                'max:2048',
            ],

            'pdf' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:51200',
            ],

            'categories' => [
                'sometimes',
                'array',
            ],

            'categories.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],

            'is_featured' => [
                'sometimes',
                'boolean',
            ],

            'is_published' => [
                'sometimes',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Determine whether categories were supplied
        |--------------------------------------------------------------------------
        */

        $categoriesWereProvided = array_key_exists(
            'categories',
            $validated
        );

        $categoryIds = $validated['categories'] ?? [];

        unset($validated['categories']);

        /*
        |--------------------------------------------------------------------------
        | Update slug when title changes
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['title']) &&
            $validated['title'] !== $book->title
        ) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['title'],
                $book->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Handle publication state
        |--------------------------------------------------------------------------
        */

        if (array_key_exists('is_published', $validated)) {

            if (
                $validated['is_published'] &&
                ! $book->published_at
            ) {
                $validated['published_at'] = now();
            }

            if (! $validated['is_published']) {
                $validated['published_at'] = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Replace PDF when supplied
        |--------------------------------------------------------------------------
        */

        $oldPdfPath = $book->pdf_path;
        $newPdfPath = null;

        if ($request->hasFile('pdf')) {

            $newPdfPath = Str::uuid() . '.pdf';

            $request->file('pdf')->storeAs(
                '',
                $newPdfPath,
                'books'
            );

            $validated['pdf_path'] = $newPdfPath;
        }

        /*
        |--------------------------------------------------------------------------
        | Uploaded objects must not reach Eloquent
        |--------------------------------------------------------------------------
        */

        unset($validated['pdf']);
        unset($validated['cover_image']);

        /*
        |--------------------------------------------------------------------------
        | Update book and categories atomically
        |--------------------------------------------------------------------------
        */

        try {

            DB::transaction(function () use (
                $book,
                $validated,
                $categoriesWereProvided,
                $categoryIds
            ) {
                $book->update($validated);

                /*
                | Only synchronize categories when the request actually
                | contains the categories field.
                |
                | Therefore:
                |
                | no categories field = leave existing categories alone
                | categories: []     = remove all categories
                */

                if ($categoriesWereProvided) {
                    $book->categories()->sync($categoryIds);
                }
            });

        } catch (\Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Database update failed
            |--------------------------------------------------------------------------
            */

            if ($newPdfPath) {
                Storage::disk('books')->delete($newPdfPath);
            }

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete old PDF after successful database update
        |--------------------------------------------------------------------------
        */

        if (
            $newPdfPath &&
            $oldPdfPath &&
            $oldPdfPath !== $newPdfPath &&
            Storage::disk('books')->exists($oldPdfPath)
        ) {
            Storage::disk('books')->delete($oldPdfPath);
        }

        return response()->json([
            'message' => 'Book updated successfully.',
            'data' => $book
                ->fresh()
                ->load('categories:id,uuid,name,slug'),
        ]);
    }

    /**
     * Soft delete the specified book.
     */
    public function destroy(string $uuid)
    {
        $book = Book::where('uuid', $uuid)->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Soft delete only
        |--------------------------------------------------------------------------
        |
        | The private PDF is intentionally retained so the book can
        | potentially be restored later.
        |
        */

        $book->delete();

        return response()->noContent();
    }

    /**
     * Generate a unique slug for a book.
     */
    private function generateUniqueSlug(
        string $title,
        ?int $ignoreBookId = null
    ): string {
        $baseSlug = Str::slug($title);

        /*
        | Fallback for unusual titles that produce an empty slug.
        */
        if ($baseSlug === '') {
            $baseSlug = 'book';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Book::withTrashed()
                ->when(
                    $ignoreBookId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreBookId
                    )
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}