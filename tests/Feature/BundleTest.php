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

class BundleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A bundle can be created.
     */
    public function test_bundle_can_be_created(): void
    {
        $bundle = Bundle::create([
            'name' => 'Kingdom Success Bundle',
            'slug' => 'kingdom-success-bundle',
            'description' => 'A collection of Kingdom Success resources.',
            'cover_image' => 'bundles/kingdom-success.jpg',
            'price' => 49.99,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('bundles', [
            'id' => $bundle->id,
            'name' => 'Kingdom Success Bundle',
            'slug' => 'kingdom-success-bundle',
        ]);
    }

    /**
     * A bundle receives a UUID.
     */
    public function test_bundle_has_uuid(): void
    {
        $bundle = Bundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'price' => 20.00,
        ]);

        $this->assertNotNull($bundle->uuid);
        $this->assertNotEmpty($bundle->uuid);
    }

    /**
     * A bundle uses UUID for route model binding.
     */
    public function test_bundle_uses_uuid_for_route_binding(): void
    {
        $bundle = new Bundle();

        $this->assertSame(
            'uuid',
            $bundle->getRouteKeyName()
        );
    }

    /**
     * Bundle price is cast correctly.
     */
    public function test_bundle_price_is_cast_correctly(): void
    {
        $bundle = Bundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'price' => 29.99,
        ]);

        $this->assertSame(
            '29.99',
            (string) $bundle->fresh()->price
        );
    }

    /**
     * Bundle published_at is cast to datetime.
     */
    public function test_bundle_published_at_is_cast_correctly(): void
    {
        $bundle = Bundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'price' => 29.99,
            'published_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $bundle->fresh()->published_at
        );
    }

    /**
     * An active published bundle is active.
     */
    public function test_active_published_bundle_is_active(): void
    {
        $bundle = Bundle::create([
            'name' => 'Active Bundle',
            'slug' => 'active-bundle',
            'price' => 29.99,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue($bundle->isActive());
    }

    /**
     * A draft bundle is not active.
     */
    public function test_draft_bundle_is_not_active(): void
    {
        $bundle = Bundle::create([
            'name' => 'Draft Bundle',
            'slug' => 'draft-bundle',
            'price' => 29.99,
            'is_active' => true,
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->assertFalse($bundle->isActive());
    }

    /**
     * An inactive bundle is not active.
     */
    public function test_inactive_bundle_is_not_active(): void
    {
        $bundle = Bundle::create([
            'name' => 'Inactive Bundle',
            'slug' => 'inactive-bundle',
            'price' => 29.99,
            'is_active' => false,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse($bundle->isActive());
    }

    /**
     * A future-published bundle is not active.
     */
    public function test_future_published_bundle_is_not_active(): void
    {
        $bundle = Bundle::create([
            'name' => 'Future Bundle',
            'slug' => 'future-bundle',
            'price' => 29.99,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($bundle->isActive());
    }

    /**
     * An active bundle is purchasable.
     */
    public function test_active_bundle_is_purchasable(): void
    {
        $bundle = Bundle::create([
            'name' => 'Purchasable Bundle',
            'slug' => 'purchasable-bundle',
            'price' => 49.99,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue($bundle->isPurchasable());
    }

    /**
     * A draft bundle is not purchasable.
     */
    public function test_draft_bundle_is_not_purchasable(): void
    {
        $bundle = Bundle::create([
            'name' => 'Draft Bundle',
            'slug' => 'draft-bundle',
            'price' => 49.99,
            'is_active' => true,
            'is_published' => false,
        ]);

        $this->assertFalse($bundle->isPurchasable());
    }

    /**
     * A bundle without a publication date can be active
     * when it is already published and active.
     */
    public function test_active_bundle_without_publication_date_is_active(): void
    {
        $bundle = Bundle::create([
            'name' => 'Immediate Bundle',
            'slug' => 'immediate-bundle',
            'price' => 25.00,
            'is_active' => true,
            'is_published' => true,
            'published_at' => null,
        ]);

        $this->assertTrue($bundle->isActive());
    }

    /**
     * A free bundle is still purchasable.
     */
    public function test_free_bundle_is_purchasable(): void
    {
        $bundle = Bundle::create([
            'name' => 'Free Bundle',
            'slug' => 'free-bundle',
            'price' => 0,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue($bundle->isPurchasable());
    }

    /**
     * Bundle price is formatted correctly.
     */
    public function test_bundle_price_is_formatted_correctly(): void
    {
        $bundle = Bundle::create([
            'name' => 'Formatted Bundle',
            'slug' => 'formatted-bundle',
            'price' => 49.9,
        ]);

        $this->assertSame(
            '49.90',
            $bundle->formattedPrice()
        );
    }

    /**
     * A bundle can contain multiple products.
     */
    public function test_bundle_can_contain_multiple_products(): void
    {
        $bundle = Bundle::create([
            'name' => 'Complete Bundle',
            'slug' => 'complete-bundle',
            'price' => 99.99,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book = Book::factory()->create();
        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
        ]);
        $course = Course::factory()->create();
        $video = Video::factory()->create();

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_AUDIOBOOK,
            'audiobook_id' => $audiobook->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_COURSE,
            'course_id' => $course->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_VIDEO,
            'video_id' => $video->id,
        ]);

        $this->assertCount(
            4,
            $bundle->fresh()->items
        );
    }

    /**
     * Bundle item count works.
     */
    public function test_bundle_can_count_items(): void
    {
        $bundle = Bundle::create([
            'name' => 'Count Bundle',
            'slug' => 'count-bundle',
            'price' => 50.00,
        ]);

        $book = Book::factory()->create();

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
        ]);

        $this->assertSame(
            1,
            $bundle->itemsCount()
        );
    }
}