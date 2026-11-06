@php
    $homeSupportLinks = [];

    if ($web_config['business_pages']?->firstWhere('slug', 'about-us')) {
        $homeSupportLinks[] = [
            'title' => translate('about_us'),
            'description' => translate('Know_about_our_company_more.'),
            'url' => route('business-page.view', ['slug' => 'about-us']),
            'icon' => 'about-us.svg',
            'tone' => 'violet',
        ];
    }

    $homeSupportLinks[] = [
        'title' => translate('contact_Us'),
        'description' => translate('We_are_Here_to_Help'),
        'url' => route('contacts'),
        'icon' => 'contact-us.svg',
        'tone' => 'blue',
    ];
    $homeSupportLinks[] = [
        'title' => translate('FAQ'),
        'description' => translate('Get_all_Answers'),
        'url' => route('helpTopic'),
        'icon' => 'faq-icon.svg',
        'tone' => 'cyan',
    ];

    if (Route::has('frontend.blog.index') && getWebConfig(name: 'blog_feature_active_status')) {
        $homeSupportLinks[] = [
            'title' => translate('Blog'),
            'description' => translate('Check_Latest_Blogs'),
            'url' => route('frontend.blog.index'),
            'icon' => 'blog-icon.svg',
            'tone' => 'orange',
        ];
    }
@endphp

<section class="home-support-section pb-4" aria-label="{{ translate('support_and_information') }}">
    <div class="container max-w-1160px">
        <div class="home-support-grid" style="--support-columns: {{ count($homeSupportLinks) }}">
            @foreach($homeSupportLinks as $supportLink)
                <a class="home-support-card home-support-card--{{ $supportLink['tone'] }}" href="{{ $supportLink['url'] }}">
                    <span class="home-support-icon" aria-hidden="true">
                        @switch($supportLink['icon'])
                            @case('about-us.svg')
                                <svg viewBox="0 0 32 32" fill="none"><path d="M5 27V9l11-5 11 5v18M2.5 27h27"/><path d="M10 12h3v3h-3zM19 12h3v3h-3zM10 19h3v3h-3zM19 19h3v3h-3zM14 27v-5h4v5"/></svg>
                                @break
                            @case('contact-us.svg')
                                <svg viewBox="0 0 32 32" fill="none"><path d="M6 17v-2a10 10 0 0 1 20 0v2"/><path d="M6 15H4.5A2.5 2.5 0 0 0 2 17.5v4A2.5 2.5 0 0 0 4.5 24H7V15Zm20 0h1.5a2.5 2.5 0 0 1 2.5 2.5v4a2.5 2.5 0 0 1-2.5 2.5H25v-9ZM25 24c-1 3-3.5 4-7 4"/><path d="M18 26h-4a2 2 0 0 0 0 4h4v-4Z"/></svg>
                                @break
                            @case('faq-icon.svg')
                                <svg viewBox="0 0 32 32" fill="none"><path d="M5 5h18a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H12l-7 5v-5a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3Z"/><path d="M12 11a4 4 0 1 1 5 3.9V17M17 20h.01"/></svg>
                                @break
                            @default
                                <svg viewBox="0 0 32 32" fill="none"><path d="M6 3h14l6 6v20H6z"/><path d="M20 3v7h6M11 15h10M11 20h10M11 25h7"/></svg>
                        @endswitch
                    </span>
                    <span class="home-support-content">
                        <strong class="home-support-title">{{ $supportLink['title'] }}</strong>
                        <span class="home-support-description">{{ $supportLink['description'] }}</span>
                    </span>
                    <span class="home-support-arrow" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="m12.5 4-6 6 6 6"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
