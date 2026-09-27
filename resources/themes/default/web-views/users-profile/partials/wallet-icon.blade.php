<svg class="wallet-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('insurance')
            <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('history')
            <path d="M3 10a9 9 0 1 1 2 8M3 4v6h6M12 7v5l3 2"/>
            @break
        @default
            <path d="M20 8H5a2 2 0 0 1 0-4h13v4M3 6v13a2 2 0 0 0 2 2h15V8M20 12h-5v5h5"/><path d="M17 14.5h.01"/>
    @endswitch
</svg>
