<?php

namespace App\Livewire\Frontend;

use App\Enums\BlogCategoryEnum;
use App\Models\Blog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('FluxFitness Blog | Continuous Fitness Evolution')]
#[Layout('frontend.layouts.blog')]
class BlogListing extends Component
{
    public string $search = '';

    public string $selectedCategory = '';

    public array $categories = [];

    public array $featuredArticle = [];

    public array $articles = [];

    public int $page = 1;

    public int $perPage = 9;

    public bool $hasMore = true;

    public function mount(): void
    {
        $this->loadFeaturedArticle();
        $this->articles = $this->getArticles();
        $this->categories = BlogCategoryEnum::toSelectArray();
    }

    protected function loadFeaturedArticle(): void
    {
        $featured = Blog::query()->published()
            ->featured()
            ->latest('published_at')
            ->first();

        if (! $featured) {
            $featured = Blog::query()->published()
                ->latest('published_at')
                ->first();
        }

        if ($featured) {
            $this->featuredArticle = [
                'title' => $featured->title,
                'excerpt' => $featured->excerpt,
                'category' => $featured->category->getLabel(),
                'author' => $featured->author,
                'authorRole' => $featured->author_role,
                'date' => $featured->published_at ? $featured->published_at->format('M d, Y') : $featured->created_at->format('M d, Y'),
                'readTime' => $featured->read_time ?? '5 min read',
                'image' => $featured->full_image_url,
                'slug' => $featured->slug,
            ];
        }
    }

    public function getArticles(): array
    {
        $query = Blog::query()->published();

        if ($this->selectedCategory !== '' && $this->selectedCategory !== 'All') {
            $query->where('category', $this->selectedCategory);
        }

        if (! empty($this->search)) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('excerpt', 'like', $search)
                    ->orWhere('category', 'like', $search);
            });
        }

        // Exclude featured from main list if there's a featured article
        if (! empty($this->featuredArticle)) {
            $query->where('slug', '!=', $this->featuredArticle['slug']);
        }

        $total = $query->count();
        $this->hasMore = ($this->page * $this->perPage) < $total;

        $blogs = $query->latest('published_at')
            ->skip(($this->page - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();

        return $blogs->map(fn (Blog $blog) => [
            'title' => $blog->title,
            'excerpt' => $blog->excerpt,
            'category' => $blog->category->getLabel(),
            'author' => $blog->author,
            'readTime' => $blog->read_time ?? '5 min read',
            'image' => $blog->full_image_url,
            'slug' => $blog->slug,
        ])->toArray();
    }

    public function loadMore(): void
    {
        $this->page++;
        $newArticles = $this->getArticles();
        $this->articles = array_merge($this->articles, $newArticles);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->articles = $this->getArticles();
    }

    public function updatedSelectedCategory(): void
    {
        $this->page = 1;
        $this->articles = $this->getArticles();
    }

    public function subscribeNewsletter(): void
    {
        session()->flash('newsletter_success', 'Thanks for subscribing! You\'ll receive gym growth tips in your inbox.');
    }

    public function render()
    {
        return view('livewire.frontend.blog-listing');
    }
}
