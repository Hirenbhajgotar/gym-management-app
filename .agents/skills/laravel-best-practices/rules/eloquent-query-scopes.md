---
title: Query Scopes for Reusable Queries
impact: HIGH
impactDescription: DRY principle for database queries
tags: eloquent, scopes, queries, reusability
---

## Query Scopes for Reusable Queries

**Impact: HIGH (DRY principle for database queries)**

Encapsulate reusable query constraints in model scopes for cleaner, more maintainable code.

## Bad Example

```php
// Repeated query logic scattered across controllers
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::where('published', true)
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->get();

        return view('posts.index', compact('posts'));
    }

    public function featured()
    {
        // Same logic duplicated
        $posts = Post::where('published', true)
            ->where('published_at', '<=', now())
            ->where('is_featured', true)
            ->orderBy('published_at', 'desc')
            ->get();

        return view('posts.featured', compact('posts'));
    }
}

class ApiPostController extends Controller
{
    public function index()
    {
        // Again, duplicated logic
        $posts = Post::where('published', true)
            ->where('published_at', '<=', now())
            ->paginate(20);

        return PostResource::collection($posts);
    }
}
```

## Good Example

```php
// Model with reusable scopes
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;

class Post extends Model
{
    // Local scope - called as scopePublished, used as published()
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('published', true)
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    #[Scope]
    protected function recent(Builder $query): void
    {
        $query->orderBy('published_at', 'desc');
    }

    #[Scope]
    protected function byAuthor(Builder $query, User $author): void
    {
        $query->where('author_id', $author->id);
    }

    #[Scope]
    protected function inCategory(Builder $query, int|Category $category): void
    {
        $categoryId = $category instanceof Category ? $category->id : $category;
        return $query->where('category_id', $categoryId);
    }

    // Dynamic scope with parameters
    #[Scope]
    protected function createdBetween(Builder $query, $start, $end): Buildevoid
    {
        $query->whereBetween('created_at', [$start, $end]);
    }

    // Scope with optional parameter
    #[Scope]
    protected function popular(Builder $query, int $minViews = 1000): void
    {
        $query->where('views', '>=', $minViews);
    }
}
```

```php
// Clean controller using scopes
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::published()->recent()->get();

        return view('posts.index', compact('posts'));
    }

    public function featured()
    {
        $posts = Post::published()
            ->featured()
            ->recent()
            ->get();

        return view('posts.featured', compact('posts'));
    }

    public function byAuthor(User $author)
    {
        $posts = Post::published()
            ->byAuthor($author)
            ->recent()
            ->paginate(20);

        return view('posts.by-author', compact('posts', 'author'));
    }
}
```

```php
// Global scope - automatically applied to all queries
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('published', true);
    }
}
```

```php
// Apply global scope in model
class Post extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new PublishedScope);

        // Or inline
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('deleted_at', null);
        });
    }
}

// Remove global scope when needed
Post::withoutGlobalScope(PublishedScope::class)->get();
Post::withoutGlobalScope('active')->get();
Post::withoutGlobalScopes()->get(); // Remove all
```

## Why

- **DRY**: Query logic defined once, used everywhere
- **Readable**: Expressive, chainable method names
- **Maintainable**: Changes to query logic happen in one place
- **Testable**: Scopes can be unit tested
- **Encapsulation**: Query details hidden in the model
- **Chainable**: Scopes can be combined fluently