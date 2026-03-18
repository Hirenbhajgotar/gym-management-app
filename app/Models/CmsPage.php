<?php

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

    protected $casts = [
        'is_published' => 'boolean',
    ];

    #[Scope]
    protected function published(Builder $query)
    {
        $query->where('is_published', true);
    }

    public function toSitemapTag(): Url|string|array
    {
        return Url::create(route('cms.page', $this->slug))
            ->setLastModificationDate($this->updated_at);
    }
}
