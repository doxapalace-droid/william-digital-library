<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Christian Living',
                'slug' => 'christian-living',
                'description' => 'Books designed to help believers grow and live effectively in Christ.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Prayer & Spiritual Warfare',
                'slug' => 'prayer-spiritual-warfare',
                'description' => 'Books on prayer, spiritual authority, warfare, and victorious Christian living.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Faith & Spiritual Growth',
                'slug' => 'faith-spiritual-growth',
                'description' => 'Resources for developing faith, spiritual maturity, and a deeper walk with God.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Kingdom & Dominion',
                'slug' => 'kingdom-dominion',
                'description' => 'Books on Kingdom authority, dominion, influence, and the believer’s assignment.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Leadership & Success',
                'slug' => 'leadership-success',
                'description' => 'Books on leadership, purpose, excellence, influence, wisdom, and success.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Prosperity & Finance',
                'slug' => 'prosperity-finance',
                'description' => 'Kingdom principles concerning prosperity, wealth, stewardship, and financial advancement.',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Healing & Divine Health',
                'slug' => 'healing-divine-health',
                'description' => 'Books addressing divine healing, health, wholeness, and the healing ministry.',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'New Creation Realities',
                'slug' => 'new-creation-realities',
                'description' => 'Books exploring identity in Christ, resurrection life, righteousness, and the new creation.',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Wisdom & Purpose',
                'slug' => 'wisdom-purpose',
                'description' => 'Books on discovering purpose, applying divine wisdom, and fulfilling God-given assignments.',
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}