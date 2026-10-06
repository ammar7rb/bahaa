<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon)
@case('home')<path d="m3 10 9-7 9 7v10H15v-6H9v6H3Z"/>@break
@case('products')<path d="m3 7 9-4 9 4v10l-9 4-9-4Z"/><path d="m3 7 9 5 9-5M12 12v9"/>@break
@case('orders')<path d="M5 7h14v14H5Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2M9 12h6M9 16h4"/>@break
@case('messages')<path d="M21 11a9 9 0 0 1-9 9H4l-2 2V11a9 9 0 1 1 19 0Z"/><path d="M7 9h9M7 13h6"/>@break
@case('notifications')<path d="M6 8a6 6 0 0 1 12 0c0 6 3 9 3 9H3s3-3 3-9M10 21h4"/>@break
@case('marketing')<path d="m3 9 17-5v16L3 15ZM8 16v5h4v-4"/>@break
@case('reports')<path d="M4 20h17M6 16v-5M12 16V4M18 16V8"/>@break
@case('settings')<path d="M4 7h16M4 17h16"/><circle cx="8" cy="7" r="3" fill="white"/><circle cx="16" cy="17" r="3" fill="white"/>@break
@case('add')<path d="M12 5v14M5 12h14"/>@break
@case('arrow')<path d="m9 5 7 7-7 7"/>@break
@default<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>
@endswitch
</svg>
