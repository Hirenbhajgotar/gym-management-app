<?php

namespace App\Livewire\Frontend;

use App\Models\CmsPage;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('frontend.layouts.app_new')]
class CmsPageController extends Component
{
    public $slug;

    public function mount($slug)
    {
        $this->slug = $slug;
    }

    public function render()
    {
        $slug = $this->slug;

        $page = Cache::flexible(CmsPage::CMS_PAGE_CACHE_KEY . $slug, [86400, 172800], function () use ($slug) {
            return CmsPage::where('slug', $slug)
                ->where('is_published', true)
                ->firstOrFail();
        });

        return view('livewire.frontend.cms-page-controller', compact('page'))
            ->title($page->meta_title ?? $page->title);
    }
}
