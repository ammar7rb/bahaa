@if(count($companyReliability) > 0)
    <section class="container rtl pb-4 px-3 home-trust-section" aria-label="{{ translate('company_reliability') }}">
        <div class="home-trust-grid">
            @foreach ($companyReliability as $value)
                @if ($value['status'] == 1 && !empty($value['title']))
                    <article class="home-trust-card" data-trust-item="{{ $value['item'] }}">
                        <span class="home-trust-icon" aria-hidden="true">
                            @switch($value['item'])
                                @case('delivery_info')
                                    <svg viewBox="0 0 32 32" fill="none"><path d="M3.5 8.5h16v14h-16zM19.5 13h5l4 4.5v5h-9z"/><path d="M7 25.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm16.5 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 12H.8M2 16H.8"/></svg>
                                    @break
                                @case('safe_payment')
                                    <svg viewBox="0 0 32 32" fill="none"><rect x="3" y="5" width="22" height="17" rx="3"/><path d="M3 10h22M7 17h6M24 15.5l5-2v5.2c0 3.2-2 5.8-5 7.3-3-1.5-5-4.1-5-7.3v-5.2l5 2Z"/><path d="m21.8 19 1.5 1.5 3-3"/></svg>
                                    @break
                                @case('return_policy')
                                    <svg viewBox="0 0 32 32" fill="none"><path d="m7 10 9-5 9 5-9 5-9-5Z"/><path d="M7 10v11l9 5 9-5V10M16 15v11"/><path d="M4 19a7 7 0 0 0 7 7M4 19v5M4 19h5"/></svg>
                                    @break
                                @case('authentic_product')
                                    <svg viewBox="0 0 32 32" fill="none"><path d="m16 3 3 2 3.6-.2 1.5 3.3 3 2-.7 3.6 1.4 3.3-2.6 2.6-.4 3.6-3.5.9-2.3 2.8-3-1.6-3 1.6-2.3-2.8-3.5-.9-.4-3.6L4.2 17l1.4-3.3-.7-3.6 3-2 1.5-3.3L13 5l3-2Z"/><path d="m11.5 15.5 3 3 6-6"/></svg>
                                    @break
                                @default
                                    <img loading="lazy" alt="" class="object-contain" width="44" height="44"
                                         src="{{ getStorageImages(path: imagePathProcessing(imageData: $value['image'],path: 'company-reliability'), type: 'source', source: 'public/assets/front-end/img'.'/'.$value['item'].'.png') }}">
                            @endswitch
                        </span>
                        <h2 class="home-trust-title">{{ $value['title'] }}</h2>
                    </article>
                @endif
            @endforeach
        </div>
    </section>
@endif
