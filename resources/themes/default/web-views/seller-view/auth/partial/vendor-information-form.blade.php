<div class="second-el d--none">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4">{{ translate('create_an_account') }}</h3>
                        <div class="border p-3 p-xl-4 rounded">
                            <h4 class="mb-3">{{ translate('vendor_information') }}</h4>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group mb-4">
                                        <label for="f_name">{{ translate('first_name') }} <span
                                                class="text-danger">*</span></label>
                                        <input class="form-control" type="text" name="f_name"
                                            placeholder="{{ translate('ex') . ': ' . translate('Jhone') }}" required>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label for="l_name">{{ translate('last_name') }}</label>
                                        <input class="form-control" type="text" name="l_name"
                                            placeholder="{{ translate('ex') . ': ' . translate('Doe') }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex flex-column gap-3 align-items-center">
                                        <div class="upload-file">
                                            <input type="file" class="upload-file__input" name="image"
                                                   data-max-size="{{ getFileUploadMaxSize() }}"
                                                accept="{{ getFileUploadFormats(skip: '.svg') }}" >
                                            <div class="upload-file__img">
                                                <div class="temp-img-box">
                                                    <div class="d-flex align-items-center flex-column gap-2">
                                                        <i class="tio-upload fs-30"></i>
                                                        <div class="fs-12 text-muted text-capitalize">
                                                            {{ translate('upload_file') }}</div>
                                                    </div>
                                                </div>
                                                <img src="#" class="dark-support img-fit-contain border"
                                                    alt="" hidden>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-column gap-1 upload-img-content text-center">
                                            <h6 class="text-uppercase mb-1 fs-14">
                                                {{ translate('vendor_image') }}
                                                <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                            </h6>
                                            <div class="text-muted text-capitalize fs-12">
                                                {{ translate('image_ratio') . ' ' . '1:1' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border p-3 p-xl-4 rounded mt-4">
                            <h4 class="mb-3 text-capitalize">{{ translate('shop_information') }}</h4>

                            <div class="form-group mb-4">
                                <label for="store_name" class="text-capitalize">{{ translate('shop_Name') }} <span
                                        class="text-danger">*</span></label>
                                <input class="form-control" type="text" id="shop_name" name="shop_name"
                                    placeholder="{{ translate('Ex: XYZ store') }}" required>
                            </div>
                            <div class="form-group mb-4">
                                <label for="store_address" class="text-capitalize">{{ translate('shop_address') }}
                                    <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="shop_address" id="shop_address" rows="4"
                                    placeholder="{{ translate('shop_address') }}" required></textarea>
                            </div>

                            <div class="border p-3 p-xl-4 rounded mb-4">
                                <div class="d-flex flex-column gap-3 align-items-center">
                                    <div class="upload-file">
                                        <input type="file" class="upload-file__input" name="logo" data-max-size="{{ getFileUploadMaxSize() }}"  accept="{{getFileUploadFormats(skip: '.svg')}}"
                                            >
                                        <div class="upload-file__img">
                                            <div class="temp-img-box">
                                                <div class="d-flex align-items-center flex-column gap-2">
                                                    <i class="tio-upload fs-30"></i>
                                                    <div class="fs-12 text-muted text-capitalize">
                                                        {{ translate('upload_file') }}</div>
                                                </div>
                                            </div>
                                            <img src="#" class="dark-support img-fit-contain border"
                                                alt="" hidden>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-1 upload-img-content text-center">
                                        <h6 class="text-uppercase mb-1 fs-14">
                                            {{ translate('upload_logo') }}
                                            <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                        </h6>
                                        <div class="text-muted text-capitalize fs-12">
                                            {{ translate('image_ratio') . ' ' . '1:1' }}</div>
                                        <div class="text-muted text-capitalize fs-12">
                                            {{ translate('Image Size : Max 2 MB') }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="border p-3 p-xl-4 rounded">
                                <div class="d-flex flex-column gap-3 align-items-center">
                                    <div class="upload-file">
                                        <input type="file" class="upload-file__input" name="banner" data-max-size="{{ getFileUploadMaxSize() }}"   accept="{{getFileUploadFormats(skip: '.svg')}}"
                                            >
                                        <div class="upload-file__img style--two">
                                            <div class="temp-img-box">
                                                <div class="d-flex align-items-center flex-column gap-2">
                                                    <i class="tio-upload fs-30"></i>
                                                    <div class="fs-12 text-muted text-capitalize">
                                                        {{ translate('upload_file') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <img src="#" class="dark-support img-fit-contain border"
                                                alt="" hidden>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-1 upload-img-content text-center">
                                        <h6 class="text-uppercase mb-1 fs-14">
                                            {{ translate('upload_banner') }}
                                            <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                        </h6>
                                        <div class="text-muted text-capitalize fs-12">
                                            {{ translate('image_ratio') . ' ' . '2:1' }}</div>
                                        <div class="text-muted text-capitalize fs-12">
                                            {{ translate('Image Size : Max 2 MB') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="d-flex justify-content-start mt-2">
                            <label class="custom-checkbox align-items-center">
                                <input type="checkbox" class="" id="terms-checkbox">
                                <span class="form-check-label">
                                    {{ translate('i_agree_with_the') }}
                                    <a href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}"
                                        target="_blank" class="text-underline color-bs-primary-force">
                                        {{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الشروط والأحكام وسياسة الخصوصية' : 'Terms and Conditions and Privacy Policy' }}
                                    </a>
                                </span>
                            </label>
                        </div>
                        <div class="d-flex justify-content-end mb-2 gap-2">
                            <button type="button" class="btn btn-secondary back-to-main-page">
                                {{ translate('back') }} </button>
                            <button type="submit" class="btn btn--primary" id="vendor-apply-submit"
                                disabled="disabled"> {{ translate('submit') }} </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
