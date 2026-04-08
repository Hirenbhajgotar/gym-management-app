<div class="bg-[#0e0e0e] min-h-screen">
    <!-- Main Container -->
    <div class="max-w-5xl mx-auto px-6 md:px-12 py-10 md:py-12">
        
        <!-- Back Link -->
        <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-[#adaaaa] hover:text-[#f3ffca] mb-10 md:mb-14 transition-all duration-300 group">
            <span class="material-symbols-outlined text-xl group-hover:-translate-x-1 transition-transform" data-icon="arrow_back">arrow_back</span>
            <span class="font-['Lexend'] font-medium text-sm uppercase tracking-wider">Back to Blog</span>
        </a>

        <!-- Article Header -->
        <article>
            <!-- Category Badge -->
            <span class="inline-block px-4 py-1.5 rounded-full bg-[#cafd00] text-[#4a5e00] text-xs font-['Lexend'] font-bold uppercase tracking-[0.15em] mb-6 md:mb-8">
                {{ $blog->category->getLabel() }}
            </span>

            <!-- Title - Kinetic Pulse: Large scale, tight tracking, editorial feel -->
            <h1 class="font-['Lexend'] font-black text-4xl md:text-5xl lg:text-6xl tracking-tight leading-[0.92] text-white mb-6 md:mb-8">
                {{ $blog->title }}
            </h1>

            <!-- Excerpt - Inter font, high readability on dark -->
            <p class="text-[#adaaaa] text-lg md:text-xl leading-relaxed max-w-3xl mb-10 md:mb-12">
                {{ $blog->excerpt }}
            </p>

            <!-- Author Info - No hard borders, tonal shift -->
            <div class="flex items-center gap-5 mb-12 pb-10 border-b border-[#484847]/30">
                <div>
                    <p class="font-['Lexend'] font-bold text-white text-lg">{{ $blog->author }}</p>
                    <p class="text-xs text-[#767575] uppercase tracking-[0.1em] font-['Inter']">
                        {{ $blog->author_role ?? 'Author' }} &bull; {{ $blog->published_at->format('M d, Y') }} &bull; {{ $blog->read_time ?? '5 min read' }}
                    </p>
                </div>
            </div>

            <!-- Featured Image -->
            @if($blog->image_url)
            <div class="relative aspect-video rounded-2xl overflow-hidden mb-14 md:mb-16 bg-[#1a1919]">
                <img 
                    src="{{ $blog->full_image_url }}" 
                    alt="{{ $blog->title }}"
                    class="w-full h-full object-cover"
                >
            </div>
            @endif

            <!-- Content - High-end editorial prose styling -->
            <div class="rich-content">
                {!! str($blog->content)->sanitizeHtml() !!}
            </div>
        </article>

        <!-- Share Section - Glassmorphism style -->
        <div class="mt-16 pt-10 border-t border-[#484847]/30">
            <h3 class="font-['Lexend'] font-bold text-xl text-white mb-6">Share this article</h3>
            <div class="flex gap-4">
                <button 
                    type="button"
                    x-data
                    @click="
                        if (navigator.share) {
                            navigator.share({
                                title: '{{ $blog->title }}',
                                url: window.location.href
                            }).catch(() => {
                                navigator.clipboard.writeText(window.location.href).then(() => showToast('Link copied!'));
                            });
                        } else {
                            navigator.clipboard.writeText(window.location.href).then(() => showToast('Link copied!'));
                        }
                    "
                    class="cursor-pointer px-6 py-3 bg-[#1a1919] rounded-xl hover:bg-[#262626] hover:text-[#f3ffca] transition-all duration-300 group border border-[#484847]/30">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-[#f3ffca] transition-colors" data-icon="share">share</span>
                </button>
                <button 
                    type="button"
                    x-data
                    @click="
                        navigator.clipboard.writeText(window.location.href).then(() => showToast('Link copied!'));
                    "
                    class="cursor-pointer px-6 py-3 bg-[#1a1919] rounded-xl hover:bg-[#262626] hover:text-[#f3ffca] transition-all duration-300 group border border-[#484847]/30">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-[#f3ffca] transition-colors" data-icon="link">link</span>
                </button>
            </div>
        </div>
        
        <!-- Related Articles - Grid with tonal layering -->
        @if(count($relatedArticles) > 0)
        <section class="mt-20">
            <h2 class="font-['Lexend'] font-bold text-2xl md:text-3xl text-white mb-8 md:mb-10">Related Articles</h2>
            <div class="grid md:grid-cols-2 gap-6 md:gap-8">
                @foreach($relatedArticles as $article)
                <a href="{{ route('blog.show', $article['slug']) }}" class="group bg-[#131313] rounded-xl overflow-hidden hover:bg-[#1a1919] transition-all duration-300 border border-transparent hover:border-[#484847]/30">
                    <div class="relative aspect-video overflow-hidden">
                        <img 
                            src="{{ $article['image'] }}" 
                            alt="{{ $article['title'] }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        >
                    </div>
                    <div class="p-5 md:p-6">
                        <span class="text-[10px] font-['Lexend'] font-bold uppercase tracking-[0.2em] text-[#f3ffca] mb-3 block">{{ $article['category'] }}</span>
                        <h3 class="text-base font-['Lexend'] font-bold mb-3 leading-snug group-hover:text-[#f3ffca] transition-colors line-clamp-2 text-white">
                            {{ $article['title'] }}
                        </h3>
                        <p class="text-[#767575] text-sm line-clamp-2 font-['Inter']">
                            {{ $article['excerpt'] }}
                        </p>
                    </div>
                </a>
                @endforeach
            </div>
        </section>
        @endif
    </div>
</div>

@push('scripts')
<script>
function showToast(message) {
    // Remove existing toast if any
    const existingToast = document.getElementById('share-toast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.id = 'share-toast';
    toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 bg-[#262626] text-white rounded-xl shadow-2xl border border-[#484847]/50 font-[\'Lexend\'] text-sm font-medium transform transition-all duration-300 translate-y-4 opacity-0';
    toast.textContent = message;

    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    });

    // Auto remove after 2 seconds
    setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
</script>
@endpush
