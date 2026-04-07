<?php

namespace Database\Factories;

use App\Enums\BlogCategoryEnum;
use App\Models\Blog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogFactory extends Factory
{
    protected $model = Blog::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);
        $slug = Str::slug($title);

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => fake()->paragraph(2),
            'content' => $this->generateContent(),
            'category' => fake()->randomElement(BlogCategoryEnum::class),
            'author' => fake()->name(),
            'author_role' => fake()->randomElement(['Founder', 'CEO', 'Manager', 'Trainer', 'Content Writer']),
            'read_time' => fake()->randomElement(['3 min read', '5 min read', '7 min read', '10 min read', '12 min read']),
            'image_url' => $this->generateImageUrl(),
            'is_featured' => false,
            'is_published' => true,
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    protected function generateContent(): string
    {
        $sections = [
            '<h2>Introduction</h2><p>' . fake()->paragraphs(3, true) . '</p>',
            '<h2>Main Content</h2><p>' . fake()->paragraphs(4, true) . '</p>',
            '<h2>Key Takeaways</h2><ul><li>' . fake()->sentence() . '</li><li>' . fake()->sentence() . '</li><li>' . fake()->sentence() . '</li></ul>',
            '<h2>Conclusion</h2><p>' . fake()->paragraphs(2, true) . '</p>',
        ];

        return implode("\n", $sections);
    }

    protected function generateImageUrl(): string
    {
        $imageIds = [
            'biometrics',
            'gym-training',
            'fitness-center',
            'workout',
            'health',
            'sports',
            'exercise',
            'training',
        ];

        return 'https://placehold.co/800x450/1a1a2e/cafd00?text=' . fake()->randomElement($imageIds);
    }
}
