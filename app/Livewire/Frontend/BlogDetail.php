<?php

namespace App\Livewire\Frontend;

use App\Models\Blog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('FluxFitness Blog | Continuous Fitness Evolution')]
#[Layout('frontend.layouts.blog')]
class BlogDetail extends Component
{
    public string $slug;

    public ?Blog $blog = null;

    public array $relatedArticles = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->loadBlog();
    }

    protected function loadBlog(): void
    {
        $this->blog = Blog::where('slug', $this->slug)
            ->where('is_published', true)
            ->firstOrFail();

        // Load related articles (same category, excluding current)
        $this->relatedArticles = Blog::where('is_published', true)
            ->where('id', '!=', $this->blog->id)
            ->where('category', $this->blog->category)
            ->limit(3)
            ->get()
            ->map(fn (Blog $blog) => [
                'title' => $blog->title,
                'excerpt' => $blog->excerpt,
                'category' => $blog->category->getLabel(),
                'author' => $blog->author,
                'readTime' => $blog->read_time ?? '5 min read',
                'image' => $blog->full_image_url,
                'slug' => $blog->slug,
            ])->toArray();
    }

    /**
     * Get SEO meta title - fallback to blog title if empty.
     */
    public function getMetaTitle(): string
    {
        return $this->blog->meta_title ?: $this->blog->title ?: 'FluxFitness Blog';
    }

    /**
     * Get SEO meta description - fallback to excerpt if empty.
     */
    public function getMetaDescription(): string
    {
        return $this->blog->meta_description ?: ($this->blog->excerpt ?: 'Insights for gym owners, fitness coaches, and wellness entrepreneurs.');
    }

    /**
     * Get SEO meta keywords.
     */
    public function getMetaKeywords(): string
    {
        return $this->blog->meta_keywords ?: '';
    }

    /**
     * Get OG title - fallback chain: og_title -> meta_title -> title.
     */
    public function getOgTitle(): string
    {
        return $this->blog->og_title ?: $this->getMetaTitle();
    }

    /**
     * Get OG description - fallback chain: og_description -> meta_description -> excerpt.
     */
    public function getOgDescription(): string
    {
        return $this->blog->og_description ?: $this->getMetaDescription();
    }

    /**
     * Get OG image - fallback to featured image.
     */
    public function getOgImage(): ?string
    {
        return $this->blog->og_image ?: $this->blog->full_image_url;
    }

    public function render()
    {
        return view('livewire.frontend.blog-detail')
            ->title($this->blog->meta_title ?? $this->blog->title)
            ->layout('frontend.layouts.blog', [
                'seo' => [
                    'description' => $this->blog->meta_description ?? $this->blog->excerpt,
                    'keywords' => $this->blog->meta_keywords,
                ],
            ]);
    }
}
