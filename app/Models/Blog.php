<?php

namespace App\Models;

use App\Enums\BlogCategoryEnum;
use App\Observers\BlogObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([BlogObserver::class])]
class Blog extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'blog_navigation_badge_count';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'author',
        'author_role',
        'read_time',
        'image_url',
        'is_featured',
        'is_published',
        'published_at',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'category' => BlogCategoryEnum::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Scope a query to only include published blogs.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Scope a query to only include featured blogs.
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Scope a query to filter by category.
     */
    #[Scope]
    protected function category(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    public function getImage(): string
    {
        if (str_contains($this->image_url, 'http') || str_contains($this->image_url, 'https')) {
            return $this->image_url;
        }

        return asset('storage/' . $this->image_url);
    }
}
