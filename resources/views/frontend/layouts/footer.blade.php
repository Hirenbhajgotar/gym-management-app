<footer class="bg-surface-container-lowest border-t border-outline-variant/20 pt-16 pb-12 relative z-10">
    <div class="container mx-auto px-6 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8 mb-16">
            <!-- Col 1: Brand & Bio -->
            <div class="lg:col-span-2 space-y-5">
                <a href="{{ route('home') }}" class="group inline-flex items-center gap-2.5">
                    <div class="flex text-2xl font-black italic text-primary tracking-tighter font-headline items-center gap-2 uppercase">
                        <span class="material-symbols-outlined text-3xl text-primary" style="font-variation-settings: 'FILL' 1;">all_inclusive</span>
                        <span class="text-on-surface">Flux<span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">Fitness</span></span>
                    </div>
                </a>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed max-w-sm">
                    The modern, high-performance Gym Operating System engineered specifically for Indian fitness clubs, gyms, and studios.
                </p>
                <div class="flex items-center gap-4 text-xs font-semibold uppercase tracking-widest text-on-surface-variant">
                    <div class="flex items-center gap-1.5 text-primary">
                        <span class="material-symbols-outlined text-base">verified</span>
                        <span>Made for India</span>
                    </div>
                    <span>•</span>
                    <div class="flex items-center gap-1.5 text-primary">
                        <span class="material-symbols-outlined text-base">lock</span>
                        <span>256-Bit Encrypted</span>
                    </div>
                </div>
            </div>

            <!-- Col 2: Core Features -->
            <div>
                <h4 class="font-headline font-bold text-sm uppercase tracking-widest text-on-surface mb-5">Product</h4>
                <ul class="space-y-3 text-sm text-on-surface-variant">
                    <li>
                        <a href="{{ route('home') }}#crm-integration" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">filter_alt</span>
                            Social CRM Leads
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#user-management" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">badge</span>
                            Staff & Member Access
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#attendance-tracking" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">how_to_reg</span>
                            Smart Attendance
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#multi-location" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">store</span>
                            Multi-Branch Chains
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#pricing" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">payments</span>
                            Automated Billing
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Col 3: Solutions -->
            <div>
                <h4 class="font-headline font-bold text-sm uppercase tracking-widest text-on-surface mb-5">Solutions</h4>
                <ul class="space-y-3 text-sm text-on-surface-variant">
                    <li><a href="{{ route('home') }}#solutions" class="hover:text-primary transition-colors">Single Gyms & Studios</a></li>
                    <li><a href="{{ route('home') }}#solutions" class="hover:text-primary transition-colors">Multi-Location Chains</a></li>
                    <li><a href="{{ route('home') }}#solutions" class="hover:text-primary transition-colors">CrossFit & MMA Centers</a></li>
                    <li><a href="{{ route('home') }}#pricing" class="hover:text-primary transition-colors">Enterprise Gyms</a></li>
                    <li><a href="{{ route('request-demo') }}" class="hover:text-primary transition-colors">Personalized Demo</a></li>
                </ul>
            </div>

            <!-- Col 4: Company & Legal -->
            <div>
                <h4 class="font-headline font-bold text-sm uppercase tracking-widest text-on-surface mb-5">Company</h4>
                <ul class="space-y-3 text-sm text-on-surface-variant">
                    <li>
                        <a href="tel:{{ config('app.contact_phone') }}" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">call</span>
                            {{ config('app.contact_phone') }}
                        </a>
                    </li>
                    <li>
                        <a href="mailto:{{ config('app.contact_email') }}" class="hover:text-primary transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">mail</span>
                            {{ config('app.contact_email') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('cms.page', 'privacy-policy') }}" class="hover:text-primary transition-colors">
                            Privacy Policy
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('cms.page', 'terms-of-service') }}" class="hover:text-primary transition-colors">
                            Terms of Service
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Copyright -->
        <div class="border-t border-outline-variant/15 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-on-surface-variant">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p class="flex items-center gap-1">
                Engineered with high performance for Indian Fitness Entrepreneurs
            </p>
        </div>
    </div>
</footer>

<!-- Scroll to top button -->
<button id="scrollTop" aria-label="Scroll to top"
    class="cursor-pointer fixed bottom-6 right-6 w-12 h-12 bg-surface-container-high border border-outline-variant/50 text-primary rounded-xl flex items-center justify-center opacity-0 transition-all duration-300 hover:scale-110 hover:border-primary hover:bg-surface-container-highest shadow-xl z-50">
    <span class="material-symbols-outlined text-2xl">arrow_upward</span>
</button>

<script defer>
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Scroll to top button functionality
    const scrollTopButton = document.getElementById('scrollTop');
    if (scrollTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopButton.style.opacity = '1';
                scrollTopButton.style.pointerEvents = 'auto';
            } else {
                scrollTopButton.style.opacity = '0';
                scrollTopButton.style.pointerEvents = 'none';
            }
        });

        scrollTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
</script>
