<?php

namespace Database\Factories;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * By default, create a book bundle item.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bundle_id' => Bundle::factory(),
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => Book::factory(),
            'audiobook_id' => null,
            'course_id' => null,
            'video_id' => null,
        ];
    }

    /**
     * Create a bundle item containing a book.
     */
    public function book(?Book $book = null): static
    {
        return $this->state(function () use ($book) {
            return [
                'item_type' => BundleItem::TYPE_BOOK,
                'book_id' => $book?->id ?? Book::factory(),
                'audiobook_id' => null,
                'course_id' => null,
                'video_id' => null,
            ];
        });
    }

    /**
     * Create a bundle item containing an audiobook.
     */
    public function audiobook(?Audiobook $audiobook = null): static
    {
        return $this->state(function () use ($audiobook) {
            return [
                'item_type' => BundleItem::TYPE_AUDIOBOOK,
                'book_id' => null,
                'audiobook_id' =>
                    $audiobook?->id ?? Audiobook::factory(),
                'course_id' => null,
                'video_id' => null,
            ];
        });
    }

    /**
     * Create a bundle item containing a course.
     */
    public function course(?Course $course = null): static
    {
        return $this->state(function () use ($course) {
            return [
                'item_type' => BundleItem::TYPE_COURSE,
                'book_id' => null,
                'audiobook_id' => null,
                'course_id' =>
                    $course?->id ?? Course::factory(),
                'video_id' => null,
            ];
        });
    }

    /**
     * Create a bundle item containing a video.
     */
    public function video(?Video $video = null): static
    {
        return $this->state(function () use ($video) {
            return [
                'item_type' => BundleItem::TYPE_VIDEO,
                'book_id' => null,
                'audiobook_id' => null,
                'course_id' => null,
                'video_id' =>
                    $video?->id ?? Video::factory(),
            ];
        });
    }
}