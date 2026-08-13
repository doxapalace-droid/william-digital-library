<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
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
 * Display a paginated listing of published books.
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

        'author' => [
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

        'per_page' => [
            'nullable',
            'integer',
            'min:1',
            'max:50',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Build public catalogue query
    |--------------------------------------------------------------------------
    */

    $books = Book::query()
        ->with([
            'authors:id,uuid,name,slug',
            'categories:id,uuid,name,slug',
        ])
        ->where('is_published', true)

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        |
        | Searches:
        | - title
        | - subtitle
        | - legacy author field
        | - related authors
        | - description
        |
        */

        ->when(
            !empty($validated['search']),
            function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas(
                            'authors',
                            function ($authorQuery) use ($search) {
                                $authorQuery->where(
                                    'authors.name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                });
            }
        )

        /*
        |--------------------------------------------------------------------------
        | Category filter
        |--------------------------------------------------------------------------
        |
        | Example:
        | /api/books?category=christian-living
        |
        */

        ->when(
            !empty($validated['category']),
            function ($query) use ($validated) {
                $query->whereHas(
                    'categories',
                    function ($categoryQuery) use ($validated) {
                        $categoryQuery
                            ->where(
                                'categories.slug',
                                $validated['category']
                            )
                            ->where('categories.is_active', true);
                    }
                );
            }
        )

        /*
        |--------------------------------------------------------------------------
        | Author filter
        |--------------------------------------------------------------------------
        |
        | Example:
        | /api/books?author=william-k-danquah
        |
        */

        ->when(
            !empty($validated['author']),
            function ($query) use ($validated) {
                $query->whereHas(
                    'authors',
                    function ($authorQuery) use ($validated) {
                        $authorQuery
                            ->where(
                                'authors.slug',
                                $validated['author']
                            )
                            ->where('authors.is_active', true);
                    }
                );
            }
        )

        /*
        |--------------------------------------------------------------------------
        | Featured filter
        |--------------------------------------------------------------------------
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
        'oldest' => $books
            ->orderBy('published_at')
            ->orderBy('id'),

        'title_asc' => $books
            ->orderBy('title')
            ->orderBy('id'),

        'title_desc' => $books
            ->orderByDesc('title')
            ->orderBy('id'),

        'price_asc' => $books
            ->orderBy('price')
            ->orderBy('id'),

        'price_desc' => $books
            ->orderByDesc('price')
            ->orderBy('id'),

        default => $books
            ->orderByDesc('published_at')
                ->orderByDesc('id'),
     };

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $perPage = $validated['per_page'] ?? 12;

    $books = $books
        ->paginate($perPage)
        ->withQueryString();

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return response()->json([
        'data' => $books->items(),

        'meta' => [
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'per_page' => $books->perPage(),
            'total' => $books->total(),
            'from' => $books->firstItem(),
            'to' => $books->lastItem(),
        ],
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
     */
    public function search(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate search parameters
        |--------------------------------------------------------------------------
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
        */

        $books = Book::query()
            ->with([
                'authors:id,uuid,name,slug',
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

                    $query->where(function ($searchQuery) use ($normalizedSearch) {
                        $searchQuery
                            ->whereRaw(
                                'LOWER(title) LIKE ?',
                                ['%' . $normalizedSearch . '%']
                            )
                            ->orWhereRaw(
                                'LOWER(author) LIKE ?',
                                ['%' . $normalizedSearch . '%']
                            )
                            ->orWhereHas(
                                'authors',
                                function ($authorQuery) use ($normalizedSearch) {
                                    $authorQuery->whereRaw(
                                        'LOWER(authors.name) LIKE ?',
                                        ['%' . $normalizedSearch . '%']
                                    );
                                }
                            );
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Category filter
            |--------------------------------------------------------------------------
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
        /*
        |--------------------------------------------------------------------------
        | Validate book data
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | Legacy author field
            |--------------------------------------------------------------------------
            |
            | Kept for backwards compatibility.
            |
            */

            'author' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | New author relationship
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | authors[] = 1
            | authors[] = 2
            |
            */

            'authors' => [
                'sometimes',
                'array',
            ],

            'authors.*' => [
                'integer',
                'distinct',
                Rule::exists('authors', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
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
        | Extract relationships
        |--------------------------------------------------------------------------
        */

        $categoryIds = $validated['categories'] ?? [];

        $authorsWereProvided = array_key_exists(
            'authors',
            $validated
        );

        $authorIds = $validated['authors'] ?? [];

        unset($validated['categories']);
        unset($validated['authors']);

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
        | Keep legacy author field synchronized
        |--------------------------------------------------------------------------
        |
        | If authors are supplied through the new relationship and the
        | legacy author field is empty, populate it automatically.
        |
        */

        if (
            $authorsWereProvided &&
            !empty($authorIds) &&
            empty($validated['author'])
        ) {
            $authorNames = Author::query()
                ->whereIn('id', $authorIds)
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');

            $validated['author'] = $authorNames;
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
        | Create book and attach relationships
        |--------------------------------------------------------------------------
        */

        try {
            $book = DB::transaction(function () use (
                $validated,
                $categoryIds,
                $authorsWereProvided,
                $authorIds
            ) {
                $book = Book::create($validated);

                if (!empty($categoryIds)) {
                    $book->categories()->sync($categoryIds);
                }

                if ($authorsWereProvided) {
                    $book->authors()->sync($authorIds);
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

            'data' => $book->load([
                'authors:id,uuid,name,slug',
                'categories:id,uuid,name,slug',
            ]),
        ], 201);
    }

    /**
     * Display a published book.
     */
    public function show(string $slug): JsonResponse
    {
        $book = Book::query()
            ->with([
                'authors:id,uuid,name,slug',
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

        /*
        |--------------------------------------------------------------------------
        | Validate book data
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | Legacy author field
            |--------------------------------------------------------------------------
            */

            'author' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | New author relationship
            |--------------------------------------------------------------------------
            */

            'authors' => [
                'sometimes',
                'array',
            ],

            'authors.*' => [
                'integer',
                'distinct',
                Rule::exists('authors', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
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
        | Determine whether relationships were supplied
        |--------------------------------------------------------------------------
        */

        $categoriesWereProvided = array_key_exists(
            'categories',
            $validated
        );

        $authorsWereProvided = array_key_exists(
            'authors',
            $validated
        );

        $categoryIds = $validated['categories'] ?? [];
        $authorIds = $validated['authors'] ?? [];

        unset($validated['categories']);
        unset($validated['authors']);

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
                !$book->published_at
            ) {
                $validated['published_at'] = now();
            }

            if (!$validated['is_published']) {
                $validated['published_at'] = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Synchronize legacy author field
        |--------------------------------------------------------------------------
        */

        if (
            $authorsWereProvided &&
            !empty($authorIds) &&
            empty($validated['author'])
        ) {
            $authorNames = Author::query()
                ->whereIn('id', $authorIds)
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');

            $validated['author'] = $authorNames;
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
        | Update book and relationships atomically
        |--------------------------------------------------------------------------
        */

        try {
            DB::transaction(function () use (
                $book,
                $validated,
                $categoriesWereProvided,
                $categoryIds,
                $authorsWereProvided,
                $authorIds
            ) {
                $book->update($validated);

                /*
                |--------------------------------------------------------------------------
                | Categories
                |--------------------------------------------------------------------------
                |
                | No categories field:
                |     Leave existing categories alone.
                |
                | categories: []
                |     Remove all categories.
                |
                */

                if ($categoriesWereProvided) {
                    $book->categories()->sync($categoryIds);
                }

                /*
                |--------------------------------------------------------------------------
                | Authors
                |--------------------------------------------------------------------------
                |
                | No authors field:
                |     Leave existing authors alone.
                |
                | authors: []
                |     Remove all authors.
                |
                */

                if ($authorsWereProvided) {
                    $book->authors()->sync($authorIds);
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
                ->load([
                    'authors:id,uuid,name,slug',
                    'categories:id,uuid,name,slug',
                ]),
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