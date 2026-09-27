<div class="bg-section rounded-10 p-12 p-sm-20" lang="ar" dir="rtl">
    <div class="form-group mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label" for="ar_name">
                {{ translate('Product_Name') }} <span class="input-required-icon text-danger">*</span>
            </label>
            @if(getActiveAIProviderConfigCache())
                <button type="button" class="btn bg-white text-primary bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 auto_fill_title"
                        id="title-ar-action-btn" data-lang="ar" data-route="{{ route('admin.product.title-auto-fill') }}">
                    <div class="btn-svg-wrapper"><img width="18" height="18" src="{{ dynamicAsset(path: 'public/assets//back-end/img/ai/blink-right-small.svg') }}" alt=""></div>
                    <span class="ai-text-animation d-none" role="status">{{ translate('Just_a_second') }}</span>
                    <span class="btn-text">{{ translate('Generate') }}</span>
                </button>
            @endif
        </div>
        <div class="outline-wrapper" id="title-container-ar">
            <input type="text" required name="name[]" id="ar_name" data-required-msg="{{ translate('title_field_is_required') }}"
                   class="form-control product-title-default-language" placeholder="{{ translate('ex') }}: {{ translate('new_Product') }}">
        </div>
    </div>

    <input type="hidden" name="lang[]" value="ar">

    <div class="form-group mb-0">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label" for="description-ar">
                {{ translate('description') }} <span class="input-required-icon text-danger">*</span>
            </label>
            @if(getActiveAIProviderConfigCache())
                <button type="button" class="btn bg-white text-primary bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 auto_fill_description"
                        id="description-ar-action-btn" data-lang="ar" data-route="{{ route('admin.product.description-auto-fill') }}">
                    <div class="btn-svg-wrapper"><img width="18" height="18" src="{{ dynamicAsset(path: 'public/assets//back-end/img/ai/blink-right-small.svg') }}" alt=""></div>
                    <span class="ai-text-animation d-none" role="status">{{ translate('Just_a_second') }}</span>
                    <span class="btn-text">{{ translate('Generate') }}</span>
                </button>
            @endif
        </div>
        <div class="outline-wrapper" id="editor-container-ar">
            <div id="description-ar-editor" class="quill-editor editor-min-h-80"></div>
            <textarea name="description[]" id="description-ar" class="product-description-default-language" style="display:none;"></textarea>
        </div>
    </div>
</div>
