<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\CmsPage;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('frontend.layouts.app_new')]
class CmsPageController extends Component
{
    #[Locked]
    public string $slug;

    public function mount(string $slug)
    {
        $this->slug = $slug;
    }

    public function render()
    {
        $slug = $this->slug;

        $page = Cache::flexible(CmsPage::CMS_PAGE_CACHE_KEY . $slug, [86400, 172800], function () use ($slug) {
            return CmsPage::query()->where('slug', $slug)
                ->where('is_published', true)
                ->firstOrFail();
        });

        return view('livewire.frontend.cms-page-controller', compact('page'))
            ->title($page->meta_title ?? $page->title)
            ->layout('frontend.layouts.app_new', [
                'seo' => [
                    'description' => $page->meta_description,
                    'keywords' => $page->meta_keywords,
                ],
            ]);
    }
}
