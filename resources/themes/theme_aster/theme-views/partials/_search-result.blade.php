<ul class="list-group list-group-flush">
    @foreach($products as $product)
        <li class="list-group-item bg--light">
            <a href="{{route('product',$product->slug)}}" >
                {{ $product['name'] }}
                @if($product->is_search_promoted ?? false)
                    <span class="badge bg-warning text-dark">{{ translate('Featured_Ad') }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
