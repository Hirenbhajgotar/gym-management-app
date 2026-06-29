<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CmsPageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

#[ObservedBy([CmsPageObserver::class])]
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property bool $is_published
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\CmsPageFactory factory($count = null, $state = [])
 * @method static Builder<static>|CmsPage newModelQuery()
 * @method static Builder<static>|CmsPage newQuery()
 * @method static Builder<static>|CmsPage published()
 * @method static Builder<static>|CmsPage query()
 * @method static Builder<static>|CmsPage whereContent($value)
 * @method static Builder<static>|CmsPage whereCreatedAt($value)
 * @method static Builder<static>|CmsPage whereId($value)
 * @method static Builder<static>|CmsPage whereIsPublished($value)
 * @method static Builder<static>|CmsPage whereMetaDescription($value)
 * @method static Builder<static>|CmsPage whereMetaKeywords($value)
 * @method static Builder<static>|CmsPage whereMetaTitle($value)
 * @method static Builder<static>|CmsPage whereSlug($value)
 * @method static Builder<static>|CmsPage whereTitle($value)
 * @method static Builder<static>|CmsPage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CmsPage extends Model implements Sitemapable
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'cms_page_navigation_badge_count';

    public const CMS_PAGE_CACHE_KEY = 'cms_page_';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_published',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function toSitemapTag(): Url|string|array
    {
        return Url::create(route('cms.page', $this->slug))
            ->setLastModificationDate($this->updated_at);
    }
}
