<div class="vendor-product-extra-images" id="additional_Image_Section">
    <div class="mb-3">
        <h3 class="mb-1">{{ translate('Product_Additional_Images') }} <span class="text-muted fs-12">({{ translate('optional') }})</span></h3>
        <p class="fs-12 mb-0">{{ translate('seller_product_images_count') }}: <span data-vendor-image-count>0</span>/5</p>
    </div>
    <div class="vendor-product-image-grid">
        @for($index = 1; $index <= 5; $index++)
            <label class="vendor-product-image-slot">
                <input type="file" name="images[]" accept="{{ getFileUploadFormats(skip: '.svg,.gif') }}" data-vendor-extra-image aria-label="{{ translate('Product_Additional_Images') }} {{ $index }}">
                <span data-vendor-image-preview hidden></span>
                <span data-vendor-image-placeholder aria-hidden="true"><i class="fi fi-rr-picture"></i><b>+</b></span>
                <span class="sr-only">{{ translate('Product_Additional_Images') }} {{ $index }}</span>
            </label>
        @endfor
    </div>
</div>
