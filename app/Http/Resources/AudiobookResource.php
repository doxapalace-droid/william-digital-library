<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudiobookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Only public audiobook catalogue information
     * is exposed.
     */
    public function toArray(
        Request $request
    ): array {
        $book = $this->whenLoaded('book');

        $authors = $book
            ? $book->whenLoaded('authors')
            : collect();

        return [
            'uuid' => $this->uuid,

            'book' => $book
                ? [
                    'uuid' => $book->uuid,
                    'title' => $book->title,
                    'slug' => $book->slug,
                    'subtitle' => $book->subtitle,
                    'cover_image' => $book->cover_image,
                ]
                : null,

            'authors' => $authors
                ->map(function ($author) {
                    return [
                        'uuid' => $author->uuid,
                        'name' => $author->name,
                        'slug' => $author->slug,
                        'photo' => $author->photo,
                    ];
                })
                ->values()
                ->all(),

            'description' => $this->description
                ?? $book?->description,

            'cover_image' => $this->cover_image
                ?? $book?->cover_image,

            'price' => $this->price,

            'currency' => $this->currency,

            'duration_seconds' => $this->duration_seconds,

            'duration_minutes' => $this->durationInMinutes(),

            'chapters_count' => $this->whenCounted(
                'chapters'
            ),

            'is_purchasable' => $this->isPurchasable(),

            'published_at' => $this->published_at,
        ];
    }
}