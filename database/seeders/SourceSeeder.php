<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            ['name' => 'TikTok', 'slug' => 'tiktok', 'is_custom' => false],
            ['name' => 'Snapchat', 'slug' => 'snapchat', 'is_custom' => false],
            ['name' => 'X (Twitter)', 'slug' => 'x', 'is_custom' => false],
            ['name' => 'Instagram', 'slug' => 'instagram', 'is_custom' => false],
            ['name' => 'Facebook Ads', 'slug' => 'facebook_ads', 'is_custom' => false],
            ['name' => 'Google Ads', 'slug' => 'google_ads', 'is_custom' => false],
            ['name' => 'Phone Call', 'slug' => 'phone', 'is_custom' => false],
            ['name' => 'Referral', 'slug' => 'referral', 'is_custom' => false],
            ['name' => 'Walk-in', 'slug' => 'walk_in', 'is_custom' => false],
            ['name' => 'Website', 'slug' => 'website', 'is_custom' => false],
            ['name' => 'Others', 'slug' => 'others', 'is_custom' => false],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(
                ['slug' => $source['slug']],
                $source
            );
        }
    }
}
