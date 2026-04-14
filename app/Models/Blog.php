<?php

namespace App\Models;

use App\Enums\BlogCategoryEnum;
use App\Observers\BlogObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([BlogObserver::class])]
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property BlogCategoryEnum $category
 * @property string $author
 * @property string|null $author_role
 * @property string|null $read_time
 * @property string|null $image_url
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property bool $is_featured
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $full_image_url
 *
 * @method static Builder<static>|Blog category(string $category)
 * @method static \Database\Factories\BlogFactory factory($count = null, $state = [])
 * @method static Builder<static>|Blog featured()
 * @method static Builder<static>|Blog newModelQuery()
 * @method static Builder<static>|Blog newQuery()
 * @method static Builder<static>|Blog published()
 * @method static Builder<static>|Blog query()
 * @method static Builder<static>|Blog whereAuthor($value)
 * @method static Builder<static>|Blog whereAuthorRole($value)
 * @method static Builder<static>|Blog whereCategory($value)
 * @method static Builder<static>|Blog whereContent($value)
 * @method static Builder<static>|Blog whereCreatedAt($value)
 * @method static Builder<static>|Blog whereExcerpt($value)
 * @method static Builder<static>|Blog whereId($value)
 * @method static Builder<static>|Blog whereImageUrl($value)
 * @method static Builder<static>|Blog whereIsFeatured($value)
 * @method static Builder<static>|Blog whereIsPublished($value)
 * @method static Builder<static>|Blog whereMetaDescription($value)
 * @method static Builder<static>|Blog whereMetaKeywords($value)
 * @method static Builder<static>|Blog whereMetaTitle($value)
 * @method static Builder<static>|Blog whereOgDescription($value)
 * @method static Builder<static>|Blog whereOgImage($value)
 * @method static Builder<static>|Blog whereOgTitle($value)
 * @method static Builder<static>|Blog wherePublishedAt($value)
 * @method static Builder<static>|Blog whereReadTime($value)
 * @method static Builder<static>|Blog whereSlug($value)
 * @method static Builder<static>|Blog whereTitle($value)
 * @method static Builder<static>|Blog whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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

    protected function fullImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->image_url) {
                    return null;
                }

                if (str_starts_with($this->image_url, 'http') || str_starts_with($this->image_url, 'https')) {
                    return $this->image_url;
                }

                return Storage::disk('public')->url($this->image_url);
            },
        );
    }
}
