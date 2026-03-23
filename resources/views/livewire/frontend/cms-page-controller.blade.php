<div>
    <div class="max-w-4xl mx-auto px-4 pt-28 pb-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
            {{ $page->title }}
        </h1>

        <div class="prose dark:prose-invert max-w-none">
            {!! str($page->content)->sanitizeHtml() !!}
        </div>
    </div>
</div>
