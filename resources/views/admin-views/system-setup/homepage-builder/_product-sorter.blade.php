<div class="homepage-product-sorter" data-section="{{ $key }}">
    <input type="hidden" name="sections[{{ $key }}][product_ids]" value="{{ implode(',', $section?->product_ids ?? []) }}" data-product-order>
    <p class="fs-12 text-muted mb-2">{{ getDefaultLanguage() === 'ar' ? 'اسحب المنتجات لترتيبها، أو استخدم أزرار التحريك.' : 'Drag products to arrange them, or use the move buttons.' }}</p>
    <ul class="list-unstyled mb-2" data-product-sort-list>
    @foreach($sortProducts[$key] ?? [] as $sortProduct)
        <li draggable="true" data-product-id="{{ $sortProduct->id }}" class="d-flex gap-2 align-items-center border rounded p-2 mb-2 bg-white">
            <i class="tio-drag" aria-hidden="true"></i><span class="flex-grow-1">{{ $sortProduct->name }}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="{{ translate('move_up') }}">↑</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="{{ translate('move_down') }}">↓</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove aria-label="{{ translate('remove') }}">×</button>
        </li>
    @endforeach
    </ul>
    <select class="form-control" data-add-product aria-label="{{ translate('add_product') }}"><option value="">{{ translate('add_product') }}</option>@foreach($products as $optionProduct)<option value="{{ $optionProduct->id }}">{{ $optionProduct->name }}</option>@endforeach</select>
</div>
