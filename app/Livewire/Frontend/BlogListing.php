<?php

namespace App\Livewire\Frontend;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('FluxFitness Blog | Continuous Fitness Evolution')]
#[Layout('frontend.layouts.blog')]
class BlogListing extends Component
{
    public string $search = '';

    public string $selectedCategory = 'All';

    public array $categories = [
        'All',
        'Gym Management Software',
        'Tips',
        'Fitness Trends',
        'Business Growth',
        'Case Studies',
    ];

    public array $featuredArticle = [
        'title' => 'The Evolution of Gym Management: A Roadmap for 2024',
        'excerpt' => 'Discover how AI and automation are redefining the member experience, optimizing operational efficiency, and driving unprecedented growth in the modern fitness landscape.',
        'category' => 'Featured',
        'author' => 'Sarah Jenkins',
        'authorRole' => 'Founder',
        'date' => 'Jan 10, 2024',
        'readTime' => '12 min read',
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBIGfpuqq_-xtoIqHvrXAP1FWIofTXKHTRaRrWiJWU5JQYwzNv03qpEmVlX0VUfLG_UuLzP77wBUz7QEXzxQtSmAy6OdvM2cX7lNM1d5bKe_UhvbNwvcoKQd2Pt_qhNIuQUbvbqhW-hr3IXt0OF-ABTaHJvnrFBGmQDh09mj87jk9i0wLA5aQ4gX1sXBkM7n6T5IHp9NE1O6ARXz917QwCzH7W8FH6dIzL0ujhkZAHnoFVQk9vA56CNYESWfAo7PLSHmIU0bdx-ehU',
    ];

    public array $articles = [];

    public function mount(): void
    {
        $this->articles = $this->getArticles();
    }

    public function getArticles(): array
    {
        return [
            [
                'title' => 'How to Reduce Member Churn at Your Gym',
                'excerpt' => 'Combat the seasonal drop-offs with data-driven engagement strategies that keep members coming back.',
                'category' => 'Retention',
                'author' => 'David Chen',
                'readTime' => '6 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDX9vV-lryK01v_V8mRLRUb3waTBcD1s6qTfkFjUXHQjtrVkx56aRgpoiQ95cRK0hphPgb37eSm6MxmzkpVJYpjTKX4Cce8edXxTSWFcsT0h04zWZybWNGwDBd1cMz4iVz1CMnBSkotaWd8hR6cx8rg7bZ2Vh3RUIrzA0jfdsBL8pMkHV2gahBlqLAtqv38nRTlnbAqmTu6PsjhejrLwGnLkH2gQquak67StaPA_-uOLXOL_vWl-NK-XzmWmWdhZJ9ezsNsIEpADKk',
            ],
            [
                'title' => 'Top 5 Features Every Gym Management Software Should Have',
                'excerpt' => "Don't settle for less. Ensure your infrastructure supports your growth goals with these essentials.",
                'category' => 'Tech Stack',
                'author' => 'Admin Team',
                'readTime' => '4 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuA8Lh46YkP7EZqcHUk3XY50nHukjbakTjo6Vdv_zZWDADhcMMvjwXc_OJN8tOsfe7246TEneOh7ks8YagG3PClBDCO54VwuHy3SIzxxbBpuHcN9EwWfsxRVF56wPMFs7UkQdwUXQmArtOxwv8icvSbcNxixJpShzO6H-zuAEcwzpdv-wq3xuUOKTSRgO9Zp7E2UOzR9CddOQPa4DhQKGCN7cUupzewzYESINewFgv-VyUnAhA9UVeHKpJlY9eLJTRBUyAM4Y750hrI',
            ],
            [
                'title' => 'WhatsApp Marketing for Gyms: A Complete Guide',
                'excerpt' => 'Master the art of direct communication and skyrocket your class bookings with automated messaging.',
                'category' => 'Marketing',
                'author' => 'Lisa M.',
                'readTime' => '8 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuC3NyfNktZa_LXXDwUU9Bet_4PNCllOKFFpIdYtT47P7o6kOheZKUugibvG6jKhRQgtNoxR_mkV42vzNE3b9mIWc2Hrvi1TK34fxSSv2rErEev4N--BMJuCLFBQhKvjcruteUJE7-3V1erhZ7aRrY6UzyR-nmHt_1ZFeg_a6cWcE15jojiTe1rbHZbdVcrrcVni5M6xW8NEUhO96AefQLEAfXI855THfOUpwR9I7mKbAi40rAdqDv2UmAoeAaIpBjQGngAsyH9GYME',
            ],
            [
                'title' => 'How to Track Attendance Without Frustrating Your Members',
                'excerpt' => 'Frictionless check-ins are the key to a premium member experience. Learn the best workflows.',
                'category' => 'Operations',
                'author' => 'Sam Rivera',
                'readTime' => '5 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCjvOMvzzxQ15pRr__SiegnDd7xWalR_mSLJOHj0A17K4DJU6ZuIA8GcA6h7fBFiz-tgSt8ZlnoHd25IS5LoYgNlxaQ9d03TJTSNnSeL7t13uZq_lQpPn_6vehe_tNtFzbsa1ttggjiiX0u46kiWeHbMrayzHwccad5Te_wvbWy4C1Jy5gBXaMNNUTFD1p3mPW6tRcafNsXdX088CnilT-_mZ2EcJ6LBx0TY7fgrIZDXqymN0nhOz5GdIwF1m44uUwFUPlHNSKEesQ',
            ],
            [
                'title' => 'From Spreadsheets to SaaS: One Gym Owner\'s Story',
                'excerpt' => 'How transitioning to FluxFitness saved 20 hours of admin work per week for a boutique studio.',
                'category' => 'Case Study',
                'author' => 'Jessica Hunt',
                'readTime' => '10 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCabk7mu2K5MwfawX0tU-w8EgCui_cXxxUZWq_B89WJKyHjgcnb6u9y51ZYvyUNQ1cFL9xE3RLMxk2a4PhM9fBs5nmd0rnPxTb-GjqgbYhvbBz5NIVmrVCukRGcKNGKznZR3X6yg0D75gp0hUYn79xujPzkcaV_NaHds0AXo63gjQIPRrYC9e-Sreb7P5OkJwMUxEXZAiGShqqs6URdtH50pTvzk3V36ncBGtgCBhAxla3720rJ7WJLqQxU-wZaypi16Csbb_e0FDM',
            ],
            [
                'title' => 'Setting Up Automated Billing for Your Fitness Studio',
                'excerpt' => 'Stop chasing payments. Learn how to stabilize your cash flow with recurring billing systems.',
                'category' => 'Finance',
                'author' => 'Marcus T.',
                'readTime' => '7 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBRcYN_ANFHJYp0J_25H90chOjl72Xfl9-XDnww_NIAR5p2YIx-iM1iizMIGGWuJpzaOr1Cq-xXtwA5m7bzVQH8LMKFM9-8Eq2vbhmkNIG8f1UMtohFGHgULExJ_gCce2Xf9gU6KWWYcBrJhEqs-PcpWPU7KMu6dLhPkkO3nBqosixeOpromu5Xs_0NYzEPkp7mNUmrRVmHxae1pZNWNjzElERIHtl38cNay_6Cydlfy_cqQgkZSVvU1ibyZtWQNNaBijRprP2p29Q',
            ],
            [
                'title' => 'Lead Management Tips to Fill Your Gym This Quarter',
                'excerpt' => 'Convert lookers into bookers with these proven sales pipeline strategies for fitness businesses.',
                'category' => 'Growth',
                'author' => 'Sarah Jenkins',
                'readTime' => '5 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCmdtBAkIRctWLq-cqIT6hmyU7kAc90F6nB8ENIPoChW0FqDd5txrSXkk30BvEWdNqgZVDq6D7EHygausqpPHN1AxBUCzgAVS_sPVxVvTDobZ8gCZYDwuZx23ltgB0dWcHW377E1aD-_GVTQEhqlcfEG_8s_JD0gQGLKY-fDEYpFLEcRdugwiPw7T6C8EhAHyZ7REWvoAEM5hFMJr3u3Bv3PN84tQo5FFc-GUzKkaYtufEXnMF1MkBGw2F4eg-Q4w3shjdqxuXrL0g',
            ],
            [
                'title' => 'Why Real-Time Reporting Matters for Gym Growth',
                'excerpt' => "You can't manage what you can't measure. Use live dashboards to make smarter business decisions.",
                'category' => 'Strategy',
                'author' => 'David Chen',
                'readTime' => '9 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAKbqdvjdAa-NK3osL22jdJvkmE7pTsLf3M3wLM_A3PyT6zmX2kf-JHzuQAuWAKB00_jHgIPdpn_M9z9uDVeScgwdpKU5ONtqXI-Lk7cE2lp_tnXU75iOPceS6Nm-_tPd6AWyJmRXRJjcCz4SG5tE7iTDpqnZfReSJULc6MBvurkLw3-NSJKEHbKA7EPMSr8dVWjHyifsyJGaIF44pBzXHeRIHtqqfeUYas2uUANCe0AkCiLvWrwopep0Vc29l5z-pOZmMiYAfqRdE',
            ],
            [
                'title' => 'Biometric Check-In: Is It Right for Your Gym?',
                'excerpt' => 'Analyzing the pros, cons, and privacy implications of facial and fingerprint recognition technology.',
                'category' => 'Future Tech',
                'author' => 'Tech Desk',
                'readTime' => '12 min read',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuB6qb1olNbsw5i2VToA4LIQTRd1axmEU2MQN_VVOieMKSNXbCb8CiCltBdY3wWxbfexl1TkVmOezAJ5WMQUhMjmynDEq9vRGZ8i98dCwoP3GyR5ThPGKLk48mUg3a98fHocQINhw_YY5M9ETM0ti60bOWu_9_nCB4SU4B1gWFDCuNtpuA06AI5qxLpKxIyNgNQBECkaz-MfFekEjup7bkl8BmMFiJa7Q7i5_VCrqZD9-0zNYB7qVNOsp8Nfl3YDCSkeWFIzslM9ySk',
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->filterArticles();
    }

    public function updatedSelectedCategory(): void
    {
        $this->filterArticles();
    }

    public function filterArticles(): void
    {
        $articles = $this->getArticles();

        if ($this->selectedCategory !== 'All') {
            $articles = array_filter($articles, fn ($article) => $article['category'] === $this->selectedCategory);
        }

        if (! empty($this->search)) {
            $search = strtolower($this->search);
            $articles = array_filter($articles, fn ($article) => str_contains(strtolower($article['title']), $search) ||
                str_contains(strtolower($article['excerpt']), $search) ||
                str_contains(strtolower($article['category']), $search)
            );
        }

        $this->articles = array_values($articles);
    }

    public function subscribeNewsletter(): void
    {
        // For demo, just flash a success message
        session()->flash('newsletter_success', 'Thanks for subscribing! You\'ll receive gym growth tips in your inbox.');
    }

    public function render()
    {
        return view('livewire.frontend.blog-listing');
    }
}
