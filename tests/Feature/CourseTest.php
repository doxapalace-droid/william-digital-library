<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A course can be created.
     */
    public function test_course_can_be_created(): void
    {
        $course = Course::factory()->create();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'uuid' => $course->uuid,
            'title' => $course->title,
            'slug' => $course->slug,
        ]);
    }

    /**
     * A course has a UUID.
     */
    public function test_course_has_uuid(): void
    {
        $course = Course::factory()->create();

        $this->assertNotNull($course->uuid);
        $this->assertIsString($course->uuid);
    }

    /**
     * UUID is used for route model binding.
     */
    public function test_course_uses_uuid_for_route_binding(): void
    {
        $course = Course::factory()->create();

        $this->assertSame(
            'uuid',
            $course->getRouteKeyName()
        );
    }

    /**
     * Course price is cast to decimal.
     */
    public function test_course_price_is_cast_to_decimal(): void
    {
        $course = Course::factory()->create([
            'price' => 49.99,
        ]);

        $this->assertSame(
            '49.99',
            $course->price
        );
    }

    /**
     * Published date is cast to datetime.
     */
    public function test_course_published_at_is_cast_to_datetime(): void
    {
        $course = Course::factory()->create([
            'published_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $course->published_at
        );
    }

    /**
     * An active published course is active.
     */
    public function test_active_published_course_is_active(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->assertTrue(
            $course->isActive()
        );
    }

    /**
     * A draft course is not active.
     */
    public function test_draft_course_is_not_active(): void
    {
        $course = Course::factory()
            ->draft()
            ->create();

        $this->assertFalse(
            $course->isActive()
        );
    }

    /**
     * An inactive course is not active.
     */
    public function test_inactive_course_is_not_active(): void
    {
        $course = Course::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $course->isActive()
        );
    }

    /**
     * A future-published course is not active.
     */
    public function test_future_course_is_not_active(): void
    {
        $course = Course::factory()
            ->future()
            ->create();

        $this->assertFalse(
            $course->isActive()
        );
    }

    /**
     * An active course is purchasable.
     */
    public function test_active_course_is_purchasable(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
            'price' => 25.00,
        ]);

        $this->assertTrue(
            $course->isPurchasable()
        );
    }

    /**
     * A draft course is not purchasable.
     */
    public function test_draft_course_is_not_purchasable(): void
    {
        $course = Course::factory()
            ->draft()
            ->create();

        $this->assertFalse(
            $course->isPurchasable()
        );
    }

    /**
     * An inactive course is not purchasable.
     */
    public function test_inactive_course_is_not_purchasable(): void
    {
        $course = Course::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $course->isPurchasable()
        );
    }

    /**
     * A future course is not purchasable.
     */
    public function test_future_course_is_not_purchasable(): void
    {
        $course = Course::factory()
            ->future()
            ->create();

        $this->assertFalse(
            $course->isPurchasable()
        );
    }

    /**
     * A free active course is purchasable.
     */
    public function test_free_active_course_is_purchasable(): void
    {
        $course = Course::factory()
            ->free()
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $this->assertTrue(
            $course->isPurchasable()
        );

        $this->assertSame(
            '0.00',
            $course->formattedPrice()
        );
    }

    /**
     * Course price is formatted correctly.
     */
    public function test_course_price_is_formatted_correctly(): void
    {
        $course = Course::factory()->create([
            'price' => 125.50,
        ]);

        $this->assertSame(
            '125.50',
            $course->formattedPrice()
        );
    }

    /**
     * A course without a publication date can be active.
     */
    public function test_active_course_without_publication_date_is_active(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => null,
        ]);

        $this->assertTrue(
            $course->isActive()
        );
    }

    /**
     * A course initially has no lessons.
     */
    public function test_course_has_no_lessons_initially(): void
    {
        $course = Course::factory()->create();

        $this->assertSame(
            0,
            $course->lessonsCount()
        );

        $this->assertSame(
            0,
            $course->publishedLessonsCount()
        );
    }
}