<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
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
    public function index(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate catalogue filters
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'category' => [
                'nullable',
                'string',
                'max:255',
            ],

            'featured' => [
                'nullable',
                'boolean',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'title_asc',
                    'title_desc',
                    'price_asc',
                    'price_desc',
                ]),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Build public catalogue query
        |--------------------------------------------------------------------------
        */

        $books = Book::query()
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('is_published', true)

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            |
            | Searches title, subtitle, author and description.
            |
            | Example:
            | /api/books?search=dominion
            |
            */

            ->when(
                ! empty($validated['search']),
                function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('subtitle', 'like', "%{$search}%")
                            ->orWhere('author', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Category filter
            |--------------------------------------------------------------------------
            |
            | The main catalogue uses the category slug.
            |
            | Example:
            | /api/books?category=leadership
            |
            */

            ->when(
                ! empty($validated['category']),
                function ($query) use ($validated) {
                    $query->whereHas(
                        'categories',
                        function ($categoryQuery) use ($validated) {
                            $categoryQuery
                                ->where('slug', $validated['category'])
                                ->where('is_active', true);
                        }
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Featured filter
            |--------------------------------------------------------------------------
            |
            | Example:
            | /api/books?featured=1
            |
            */

            ->when(
                $request->boolean('featured'),
                fn ($query) => $query->where('is_featured', true)
            );

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        $sort = $validated['sort'] ?? 'newest';

        match ($sort) {
            'oldest' => $books->orderBy('published_at'),
            'title_asc' => $books->orderBy('title'),
            'title_desc' => $books->orderByDesc('title'),
            'price_asc' => $books->orderBy('price'),
            'price_desc' => $books->orderByDesc('price'),
            default => $books->orderByDesc('published_at'),
        };

        $books = $books->get();

        return response()->json([
            'data' => $books,
        ]);
    }

    /**
     * Search published books.
     *
     * Supported query parameters:
     *
     * q
     *     Search by title or author.
     *
     * category
     *     Filter by category database ID.
     *
     * Examples:
     *
     * /api/books/search?q=dominion
     * /api/books/search?q=William
     * /api/books/search?category=1
     * /api/books/search?q=dominion&category=1
     */
    public function search(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate search parameters
        |--------------------------------------------------------------------------
        |
        | q is optional because category-only searches are supported.
        | When q is supplied, it must contain at least two characters.
        |
        */

        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'min:2',
                'max:100',
            ],

            'category' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ]);

        $search = isset($validated['q'])
            ? trim($validated['q'])
            : null;

        $categoryId = $validated['category'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Build search query
        |--------------------------------------------------------------------------
        |
        | Public search must never expose unpublished books.
        |
        */

        $books = Book::query()
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('is_published', true)

            /*
            |--------------------------------------------------------------------------
            | Search title and author
            |--------------------------------------------------------------------------
            */

            ->when(
                $search !== null && $search !== '',
                function ($query) use ($search) {
                    $normalizedSearch = mb_strtolower($search);

                    $query->where(
                        function ($searchQuery) use ($normalizedSearch) {
                            $searchQuery
                                ->whereRaw(
                                    'LOWER(title) LIKE ?',
                                    ['%' . $normalizedSearch . '%']
                                )
                                ->orWhereRaw(
                                    'LOWER(author) LIKE ?',
                                    ['%' . $normalizedSearch . '%']
                                );
                        }
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Category filter
            |--------------------------------------------------------------------------
            |
            | The dedicated search endpoint uses category database IDs.
            |
            */

            ->when(
                $categoryId !== null,
                function ($query) use ($categoryId) {
                    $query->whereHas(
                        'categories',
                        function ($categoryQuery) use ($categoryId) {
                            $categoryQuery->where(
                                'categories.id',
                                $categoryId
                            );
                        }
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Stable ordering
            |--------------------------------------------------------------------------
            */

            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $books,
        ]);
    }

    /**
     * Store a newly created book.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => [
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
                'nullable',
                'string',
                'max:255',
            ],

            'isbn' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('books', 'isbn'),
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'currency' => [
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
            | Category database IDs are expected.
            |
            | Example:
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
    public function show(string $slug): JsonResponse
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
    public function update(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = Book::where('uuid', $uuid)
            ->firstOrFail();

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
                Rule::unique('books', 'isbn')
                    ->ignore($book->id),
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
                | no categories field = leave existing categories alone
                | categories: []      = remove all categories
                */

                if ($categoriesWereProvided) {
                    $book->categories()->sync($categoryIds);
                }
            });
        } catch (\Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | Remove newly uploaded PDF if database update fails
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
    public function destroy(string $uuid): JsonResponse
    {
        $book = Book::where('uuid', $uuid)
            ->firstOrFail();

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

        return response()->json(
            null,
            204
        );
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
        |--------------------------------------------------------------------------
        | Fallback for unusual titles
        |--------------------------------------------------------------------------
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