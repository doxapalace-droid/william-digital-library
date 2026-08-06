<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full access to the entire system.',
                'is_system' => true,
                'status' => true,
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrator access.',
                'is_system' => true,
                'status' => true,
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can manage and edit content.',
                'is_system' => true,
                'status' => true,
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Author access.',
                'is_system' => true,
                'status' => true,
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Digital library customer.',
                'is_system' => true,
                'status' => true,
            ],
        ];

        foreach ($roles as $role) {
            $existingRole = Role::where('slug', $role['slug'])->first();

            if ($existingRole) {
                $existingRole->update($role);

                continue;
            }

            Role::create([
                'uuid' => (string) Str::uuid(),
                ...$role,
            ]);
        }
    }
}