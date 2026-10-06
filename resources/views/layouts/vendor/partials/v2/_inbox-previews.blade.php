                @forelse(array_slice($vendorInbox[$channel === 'messages' ? 'threads' : 'notifications'], 0, 5) as $preview)
                    <a class="vendor-preview-row {{ ($preview['unread_count'] ?? ($preview['unread'] ?? false)) ? 'is-unread' : '' }}" href="{{ $preview['action_url'] ?? route('vendor.notifications') }}">
                        <span class="vendor-preview-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>$channel === 'messages' ? 'messages' : (($preview['kind'] ?? '') === 'order_alert' ? 'orders' : 'notifications')])</span>
                        <span class="vendor-preview-copy"><strong>{{ $preview['title'] }}</strong><span>{{ \Illuminate\Support\Str::limit($preview['body'], 85) }}</span><time>{{ $preview['created_at'] ? \Carbon\Carbon::parse($preview['created_at'])->diffForHumans() : '' }}</time></span>
                        @if(($preview['unread_count'] ?? 0) > 0)<span class="vendor-count is-green">+{{ $preview['unread_count'] }}</span>@endif
                    </a>
                @empty
                    <div class="vendor-popover-empty">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>$channel])<p>{{ translate($channel === 'messages' ? 'vendor_no_conversations' : 'vendor_no_notifications') }}</p></div>
                @endforelse
