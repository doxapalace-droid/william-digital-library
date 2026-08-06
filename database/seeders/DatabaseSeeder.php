<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Seed roles
        |--------------------------------------------------------------------------
        */
        $this->call([
            RoleSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Seed books
        |--------------------------------------------------------------------------
        */
        $this->call([
            BookSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. Find customer role
        |--------------------------------------------------------------------------
        */
        $customerRole = Role::where('slug', 'customer')
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | 4. Create / update development customer
        |--------------------------------------------------------------------------
        */
        $customer = User::where(
            'email',
            'customer@example.com'
        )->first();

        if ($customer) {

            $customer->update([
                'name' => 'Test Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'email_verified_at' => now(),
            ]);

        } else {

            $customer = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Test Customer',
                'email' => 'customer@example.com',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'email_verified_at' => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Find seeded book
        |--------------------------------------------------------------------------
        */
        $book = Book::where(
            'slug',
            'i-am-born-again'
        )->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | 6. Create / update book entitlement
        |--------------------------------------------------------------------------
        */
        $entitlement = BookEntitlement::where(
            'user_id',
            $customer->id
        )
            ->where(
                'book_id',
                $book->id
            )
            ->first();

        if ($entitlement) {

            $entitlement->update([
                'source' => 'purchase',
                'can_read' => true,
                'can_download' => true,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => null,
            ]);

        } else {

            BookEntitlement::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'book_id' => $book->id,
                'source' => 'purchase',
                'can_read' => true,
                'can_download' => true,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => null,
            ]);
        }
    }
}