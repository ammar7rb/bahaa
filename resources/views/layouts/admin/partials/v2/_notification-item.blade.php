<a class="admin-notice {{ !empty($item['unread']) ? 'is-unread' : '' }}" data-notice-kind="{{ $kind }}" href="{{ $item['url'] }}">
    <span class="admin-notice-media">
        @if(!empty($item['images']))
            @foreach(array_slice($item['images'], 0, 3) as $image)
                <img class="admin-notice-thumb" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" width="56" height="56">
            @endforeach
        @else
            <i class="fi fi-rr-{{ $item['icon'] ?? 'bell' }}" aria-hidden="true"></i>
        @endif
    </span>
    <span class="admin-notice-content">
        <strong>{{ $item['title'] }}</strong>
        <span class="admin-notice-identity">{{ $item['detail'] }}</span>
        @if(!empty($item['preview']))<span class="admin-notice-preview">{{ $item['preview'] }}</span>@endif
        @if(!empty($item['meta']))<span class="admin-notice-meta">{{ $item['meta'] }}</span>@endif
        <span class="admin-notice-footer">
            @if(!empty($item['badge']))<span class="admin-notice-status">{{ $item['badge'] }}</span>@endif
            @if(!empty($item['time']))<time datetime="{{ $item['time'] }}">{{ $item['time_label'] ?? '' }}</time>@endif
        </span>
    </span>
    <i class="fi fi-rr-angle-small-left admin-notice-arrow" aria-hidden="true"></i>
</a>
