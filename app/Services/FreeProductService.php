<?php

namespace App\Services;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FreeProductService
{
    /**
     * Claim a free book for a customer.
     *
     * The book must be published and explicitly marked
     * as free.
     */
    public function claimBook(
        User $user,
        Book $book
    ): BookEntitlement {
        if (! $this->isFreeBookAvailable($book)) {
            throw new RuntimeException(
                'This book is not available as a free product.'
            );
        }

        return DB::transaction(function () use ($user, $book) {
            $entitlement = BookEntitlement::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                ],
                [
                    'source' => 'free',
                    'can_read' => true,
                    'can_download' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]
            );

            /*
             * If an existing entitlement was previously inactive,
             * revoked, or expired, restore access when the customer
             * claims the free product again.
             */
            if (! $entitlement->isActive()) {
                $entitlement->update([
                    'source' => 'free',
                    'can_read' => true,
                    'can_download' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);
            }

            return $entitlement->fresh();
        });
    }

    /**
     * Claim a free audiobook for a customer.
     *
     * The audiobook must be active, published,
     * and explicitly marked as free.
     */
    public function claimAudiobook(
        User $user,
        Audiobook $audiobook
    ): AudiobookEntitlement {
        if (! $this->isFreeAudiobookAvailable($audiobook)) {
            throw new RuntimeException(
                'This audiobook is not available as a free product.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $audiobook
        ) {
            $entitlement = AudiobookEntitlement::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'audiobook_id' => $audiobook->id,
                ],
                [
                    'source' => 'free',
                    'can_stream' => true,
                    'can_download' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]
            );

            if (! $entitlement->isActive()) {
                $entitlement->update([
                    'source' => 'free',
                    'can_stream' => true,
                    'can_download' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);
            }

            return $entitlement->fresh();
        });
    }

    /**
     * Claim a free course for a customer.
     *
     * The course must be active, published,
     * and explicitly marked as free.
     */
    public function claimCourse(
        User $user,
        Course $course
    ): CourseEntitlement {
        if (! $this->isFreeCourseAvailable($course)) {
            throw new RuntimeException(
                'This course is not available as a free product.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $course
        ) {
            $entitlement = CourseEntitlement::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ],
                [
                    'source' => 'free',
                    'can_access' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]
            );

            if (! $entitlement->isActive()) {
                $entitlement->update([
                    'source' => 'free',
                    'can_access' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);
            }

            return $entitlement->fresh();
        });
    }

    /**
     * Determine whether a book is currently available
     * as a free product.
     */
    private function isFreeBookAvailable(Book $book): bool
    {
        return $book->is_free === true
            && $book->is_published
            && (
                $book->published_at === null
                || $book->published_at->isPast()
            );
    }

    /**
     * Determine whether an audiobook is currently
     * available as a free product.
     */
    private function isFreeAudiobookAvailable(
        Audiobook $audiobook
    ): bool {
        return $audiobook->is_free === true
            && $audiobook->isActive();
    }

    /**
     * Determine whether a course is currently available
     * as a free product.
     */
    private function isFreeCourseAvailable(
        Course $course
    ): bool {
        return $course->is_free === true
            && $course->isActive();
    }
}