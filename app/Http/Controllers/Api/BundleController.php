<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\JsonResponse;

class BundleController extends Controller
{
    /**
     * Display the publicly available bundle catalogue.
     */
    public function index(): JsonResponse
    {
        $bundles = Bundle::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->with([
                'items.book:id,uuid,title,slug,cover_image,price,currency',
                'items.audiobook:id,uuid,book_id,title,price,currency',
                'items.course:id,uuid,title,slug,cover_image,price,currency',
                'items.video:id,uuid,title,price,currency',
            ])
            ->withCount('items')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $bundles->through(
                fn (Bundle $bundle) => $this->transformBundle($bundle)
            ),
        ]);
    }

    /**
     * Display a single publicly available bundle.
     */
    public function show(Bundle $bundle): JsonResponse
    {
        /*
         * Do not expose drafts, inactive bundles,
         * or bundles scheduled for future publication.
         */
        if (! $bundle->isActive()) {
            abort(404);
        }

        $bundle->load([
            'items.book:id,uuid,title,slug,cover_image,price,currency',
            'items.audiobook:id,uuid,book_id,title,price,currency',
            'items.course:id,uuid,title,slug,cover_image,price,currency',
            'items.video:id,uuid,title,price,currency',
        ]);

        return response()->json([
            'data' => $this->transformBundle($bundle),
        ]);
    }

    /**
     * Transform a bundle into frontend-safe public data.
     *
     * Private storage paths and internal database
     * information are intentionally excluded.
     */
    protected function transformBundle(Bundle $bundle): array
    {
        return [
            'uuid' => $bundle->uuid,
            'name' => $bundle->name,
            'slug' => $bundle->slug,
            'description' => $bundle->description,
            'cover_image' => $bundle->cover_image,
            'price' => $bundle->price,
            'formatted_price' => $bundle->formattedPrice(),
            'currency' => strtoupper($bundle->currency),
            'is_free' => $bundle->isFree(),
            'is_active' => $bundle->isActive(),
            'is_purchasable' => $bundle->isPurchasable(),
            'published_at' => $bundle->published_at,

            'items_count' => $bundle->itemsCount(),

            'items' => $bundle->items
                ->map(function ($item) {
                    $product = $item->product();

                    if (! $product) {
                        return null;
                    }

                    return match ($item->item_type) {
                        'book' => [
                            'uuid' => $product->uuid,
                            'type' => 'book',
                            'title' => $product->title,
                            'slug' => $product->slug,
                            'cover_image' => $product->cover_image,
                            'price' => $product->price,
                            'currency' => strtoupper(
                                $product->currency
                            ),
                        ],

                        'audiobook' => [
                            'uuid' => $product->uuid,
                            'type' => 'audiobook',
                            'title' => $product->title,
                            'price' => $product->price,
                            'currency' => strtoupper(
                                $product->currency
                            ),
                        ],

                        'course' => [
                            'uuid' => $product->uuid,
                            'type' => 'course',
                            'title' => $product->title,
                            'slug' => $product->slug,
                            'cover_image' => $product->cover_image,
                            'price' => $product->price,
                            'currency' => strtoupper(
                                $product->currency
                            ),
                        ],

                        'video' => [
                            'uuid' => $product->uuid,
                            'type' => 'video',
                            'title' => $product->title,
                            'price' => $product->price,
                            'currency' => strtoupper(
                                $product->currency
                            ),
                        ],

                        default => null,
                    };
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }
}