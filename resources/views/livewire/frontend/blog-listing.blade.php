<div class="max-w-7xl-- container mx-auto px-6">
    <!-- Toast Messages -->
    @livewire('toast')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12 pt-12">
        <div class="max-w-2xl">
          <h1
            class="font-headline font-black text-6xl md:text-8xl tracking-tighter leading-[0.9] text-on-surface mb-6 uppercase">
            FluxFitness <span class="text-primary-container">Blog</span>
          </h1>
          <p class="text-on-surface-variant text-xl md:text-2xl leading-relaxed max-w-xl">
            Insights for gym owners, fitness coaches, and wellness entrepreneurs.
          </p>
        </div>
        <div class="relative w-full md:w-96 group">
            <!-- Search Bar -->
          <span
            class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary"
            data-icon="search">search</span>
          <input 
                type="text" 
                wire:model.live="search"
                placeholder="Search articles..." 
                class="w-full pl-12 pr-4 py-4 bg-surface-container-high border border-transparent rounded-xl text-white placeholder-on-surface-variant focus:outline-none focus:border-primary/50 transition-colors"
            >
        </div>
      </div>
      
    <!-- Search & Categories -->
    <div class="mb-12">
        <!-- Category Tabs -->
        <div class="flex flex-wrap justify-center gap-3">
            @foreach($categories as $category)
                <button 
                    wire:click="$set('selectedCategory', '{{ $category }}')"
                    class="px-5 py-2.5 rounded-full text-sm font-medium transition-all duration-300 cursor-pointer
                        {{ $selectedCategory === $category 
                            ? 'bg-primary-container text-on-primary-container' 
                            : 'bg-surface-container text-on-surface-variant hover:bg-surface-variant hover:text-white' }}"
                >
                    {{ $category }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Featured Article -->
    @if($selectedCategory === 'All' && empty($search) && $featuredArticle)
    <section class="max-w-7xl-- mx-auto px-6 mb-24">
    <div
        class="relative group cursor-pointer overflow-hidden rounded-2xl bg-surface-container-low grid md:grid-cols-12 items-stretch">
        <div class="md:col-span-7 relative h-[400px] md:h-auto overflow-hidden">
        <img src="{{ $featuredArticle['image'] }}" 
                alt="{{ $featuredArticle['title'] }}"
                class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105 opacity-80"
        />
        <div class="absolute inset-0 bg-gradient-to-r from-surface-container-low via-transparent to-transparent">
        </div>
        </div>
        <div class="md:col-span-5 p-8 md:p-12 flex flex-col justify-center relative z-10">
        <span
            class="inline-block px-3 py-1 rounded-full bg-primary-container text-on-primary-container text-xs font-black uppercase tracking-widest mb-6 w-fit">
            {{ $featuredArticle['category'] }}
            </span>
        <h2
            class="font-headline font-bold text-3xl md:text-5xl text-on-surface leading-tight mb-6 group-hover:text-primary transition-colors">
            {{ $featuredArticle['title'] }}
        </h2>
        <p class="text-on-surface-variant text-lg mb-8 leading-relaxed line-clamp-3">
            {{ $featuredArticle['excerpt'] }}
        </p>
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 rounded-full bg-surface-variant overflow-hidden">
            <img alt="Sarah Jenkins" data-alt="Portrait of a female professional founder"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDGiPdbmg-uM7SlirMRRkyRZFjUZ8diVAb_t-i-QUMaePcDkSTzzEf2rjglWJLJZ8v_RypF17DmJ8cQrGTTttBGWsvvdDay77BG4EXd_nWcWe1vt-IzOSqjMtwDqG9KDMehuPNb1PhTOhE4zLBl91K12funabcwesoVMJQngdX5xQjp0FzsqOaHkoHcGdpe6oU5CMK8XKZV4MQLHX5WfNCGgJAweBCknc_8WFN1bC6eIS9mM7jtcd6y9WWmaW0A1RPzwz08VA7HnIw" />
            </div>
            <div>
            <p class="font-bold text-on-surface">Sarah Jenkins</p>
            <p class="text-xs text-on-surface-variant uppercase tracking-tighter font-label">Founder • Jan 10, 2024
            </p>
            </div>
        </div>
        <button class="group/btn flex items-center gap-2 text-primary font-bold uppercase tracking-widest text-sm">
            Read Article
            <span class="material-symbols-outlined transition-transform group-hover/btn:translate-x-1"
            data-icon="arrow_forward">arrow_forward</span>
        </button>
        </div>
    </div>
    </section>
    @endif

    <!-- Articles Grid -->
    <section>
        @if(count($articles) > 0)
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($articles as $article)
            <article class="group bg-surface-container-low rounded-xl overflow-hidden hover:bg-surface-container transition-all kinetic-pulse-hover cursor-pointer">
                <!-- Image -->
                <div class="relative aspect-video overflow-hidden">
                    <img 
                        src="{{ $article['image'] }}" 
                        alt="{{ $article['title'] }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    >
                </div>
                
                <!-- Content -->
                <div class="p-6">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-primary mb-3 block">{{ $article['category'] }}</span>
                    <h3 class="text-xl font-bold mb-3 leading-snug group-hover:text-primary transition-colors line-clamp-2">
                        {{ $article['title'] }}
                    </h3>
                    <p class="text-on-surface-variant text-sm mb-4 line-clamp-2">
                        {{ $article['excerpt'] }}
                    </p>
                    <div class="flex justify-between items-center pt-6 border-t border-outline-variant/20">
                        <span class="text-xs font-medium text-on-surface-variant">{{ $article['author'] }}</span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ $article['readTime'] }}
                        </span>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-[#484847] mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-xl font-semibold text-on-surface-variant mb-2">No articles found</h3>
            <p class="text-[#484847]">Try adjusting your search or filter to find what you're looking for.</p>
        </div>
        @endif

        <!-- Load More -->
        @if(count($articles) > 0)
        <div class="text-center mt-12">
            <button class="cursor-pointer px-10 py-4 border-2 border-primary-container text-primary-container font-black uppercase tracking-widest text-sm rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-all active:scale-95">
                Load More Articles
            </button>
        </div>
        @endif
    </section>

    <!-- Newsletter Section -->
    <section class="mt-20 bg-surface-container-low rounded-2xl p-12 md:p-16 text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-4">
            Scale your gym <span class="text-primary-container">exponentially.</span>
        </h2>
        <p class="text-on-surface-variant text-lg mb-8 max-w-xl mx-auto">
            Get gym growth tips in your inbox. Join 3,000+ gym owners making data-driven decisions every day.
        </p>
        
        @if(session('newsletter_success'))
            <div class="bg-primary/10 border border-primary/30 text-primary px-6 py-3 rounded-xl inline-block">
                {{ session('newsletter_success') }}
            </div>
        @else
            <form wire:submit.prevent="subscribeNewsletter" class="max-w-md mx-auto">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input 
                        type="email" 
                        placeholder="Enter your email" 
                        required
                        class="flex-grow bg-surface-container-high border-none rounded-xl py-4 px-6 text-on-surface focus:ring-2 focus:ring-primary transition-all"
                    >
                    <button 
                        type="submit"
                        class="bg-primary-container text-on-primary-container px-8 py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:opacity-80 transition-all shadow-xl shadow-primary-container/10 active:scale-95"
                    >
                        Subscribe
                    </button>
                </div>
            </form>
        @endif
    </section>
</div>
