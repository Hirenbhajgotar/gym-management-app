<?php

namespace App\Models;

use App\Observers\CmsPageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([CmsPageObserver::class])]
class CmsPage extends Model
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
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];
}
