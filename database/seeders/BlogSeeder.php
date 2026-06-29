<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Create 1 featured blog
        Blog::factory()
            ->count(1)
            ->featured()
            ->create();

        // Create 14 regular published blogs
        Blog::factory()
            ->count(14)
            ->create();

        // Create 5 unpublished blogs (drafts)
        Blog::factory()
            ->count(5)
            ->unpublished()
            ->create();
    }
}
