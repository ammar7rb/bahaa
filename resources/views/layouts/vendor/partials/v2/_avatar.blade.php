{{-- Interactive vendor avatar: click to choose a new photo (handled by vendor-avatar.js).
     Falls back to the first letter when there is no photo or the image fails to load. --}}
@php
    $svSeller = $svSeller ?? auth('seller')->user();
    $svSize = $svSize ?? 'md';
    $svHasPhoto = $svSeller && $svSeller->image && $svSeller->image !== 'def.png';
    $svInitial = $svSeller ? mb_strtoupper(mb_substr(trim((string) $svSeller->f_name) ?: 'S', 0, 1)) : 'S';
@endphp
@if($svSeller)
<button type="button" class="sv-avatar sv-avatar--{{ $svSize }} {{ $svHasPhoto ? '' : 'is-empty' }}" data-sv-avatar
        aria-label="{{ translate('change_profile_photo') }}" title="{{ translate('change_profile_photo') }}">
    <span class="sv-avatar__initial" aria-hidden="true">{{ $svInitial }}</span>
    @if($svHasPhoto)
        <img data-sv-avatar-img src="{{ route('account-photos.show', ['seller', $svSeller->id]) }}?v={{ substr(md5((string) $svSeller->image), 0, 8) }}" alt="" loading="lazy">
    @endif
    <span class="sv-avatar__cam" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
    <span class="sv-avatar__busy" aria-hidden="true"></span>
</button>
@endif
