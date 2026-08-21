<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A bundle item belongs to a bundle.
     */
    public function test_bundle_item_belongs_to_bundle(): void
    {
        $bundle = Bundle::factory()->create();
        $book = Book::factory()->create();

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        $this->assertTrue(
            $item->bundle->is($bundle)
        );
    }

    /**
     * A bundle item can reference a book.
     */
    public function test_bundle_item_can_reference_a_book(): void
    {
        $bundle = Bundle::factory()->create();
        $book = Book::factory()->create();

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        $this->assertTrue(
            $item->book->is($book)
        );

        $this->assertTrue($item->isBook());
        $this->assertFalse($item->isAudiobook());
        $this->assertFalse($item->isCourse());
        $this->assertFalse($item->isVideo());
    }

    /**
     * A bundle item can reference an audiobook.
     */
    public function test_bundle_item_can_reference_an_audiobook(): void
    {
        $bundle = Bundle::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
        ]);

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_AUDIOBOOK,
            'audiobook_id' => $audiobook->id,
        ]);

        $this->assertTrue(
            $item->audiobook->is($audiobook)
        );

        $this->assertFalse($item->isBook());
        $this->assertTrue($item->isAudiobook());
        $this->assertFalse($item->isCourse());
        $this->assertFalse($item->isVideo());
    }

    /**
     * A bundle item can reference a course.
     */
    public function test_bundle_item_can_reference_a_course(): void
    {
        $bundle = Bundle::factory()->create();
        $course = Course::factory()->create();

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_COURSE,
            'course_id' => $course->id,
        ]);

        $this->assertTrue(
            $item->course->is($course)
        );

        $this->assertFalse($item->isBook());
        $this->assertFalse($item->isAudiobook());
        $this->assertTrue($item->isCourse());
        $this->assertFalse($item->isVideo());
    }

    /**
     * A bundle item can reference a video.
     */
    public function test_bundle_item_can_reference_a_video(): void
    {
        $bundle = Bundle::factory()->create();
        $video = Video::factory()->create();

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_VIDEO,
            'video_id' => $video->id,
        ]);

        $this->assertTrue(
            $item->video->is($video)
        );

        $this->assertFalse($item->isBook());
        $this->assertFalse($item->isAudiobook());
        $this->assertFalse($item->isCourse());
        $this->assertTrue($item->isVideo());
    }

    /**
     * A bundle can have multiple items.
     */
    public function test_bundle_has_many_items(): void
    {
        $bundle = Bundle::factory()->create();

        $book = Book::factory()->create();
        $course = Course::factory()->create();

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_COURSE,
            'course_id' => $course->id,
        ]);

        $this->assertCount(
            2,
            $bundle->fresh()->items
        );
    }

    /**
     * A bundle item uses UUID for route binding.
     */
    public function test_bundle_item_uses_uuid_for_route_binding(): void
    {
        $item = new BundleItem();

        $this->assertSame(
            'uuid',
            $item->getRouteKeyName()
        );
    }

    /**
     * A bundle item receives a UUID.
     */
    public function test_bundle_item_has_uuid(): void
    {
        $bundle = Bundle::factory()->create();
        $book = Book::factory()->create();

        $item = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        $this->assertNotNull($item->uuid);
        $this->assertNotEmpty($item->uuid);
    }
}