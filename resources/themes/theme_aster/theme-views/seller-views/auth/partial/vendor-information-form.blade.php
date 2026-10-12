<div class="second-el d--none">
     <div class="container">
         <div class="row justify-content-center">
             <div class="col-lg-8">
                 <div class="card">
                     <div class="card-body">
                         <h3 class="mb-4 text-capitalize">{{ translate('create_an_account') }}</h3>
                         <div class="border p-3 p-xl-4 rounded">
                             <h4 class="mb-3 text-capitalize">{{ translate('vendor_information') }}</h4>

                             <div class="row">
                                 <div class="col-sm-6">
                                     <div class="form-group mb-4">
                                         <label class="mb-2 text-capitalize"
                                             for="f_name">{{ translate('first_name') }}
                                             <span class="text-danger">*</span></label>
                                         <input class="form-control" type="text" name="f_name"
                                             placeholder="{{ translate('ex') . ': ' . translate('Jhone') }}" required>
                                     </div>
                                     <div class="form-group mb-4">
                                         <label class="mb-2 text-capitalize" for="l_name">
                                             {{ translate('last_name') }}
                                             <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                         </label>
                                         <input class="form-control" type="text" name="l_name"
                                             placeholder="{{ translate('ex') . ': ' . translate('Doe') }}">
                                     </div>
                                 </div>
                                 <div class="col-sm-6">
                                     <div class="">
                                         <div class="d-flex flex-column gap-3 align-items-center">
                                             <div class="upload-file">
                                                 <input type="file" class="upload-file__input" name="image"
                                                        data-max-size="2"
                                                     accept="{{getFileUploadFormats(skip: '.svg,.webp,.gif')}}" >
                                                 <div class="upload-file__img">
                                                     <div class="temp-img-box">
                                                         <div class="d-flex align-items-center flex-column gap-2">
                                                             <i class="bi bi-upload fs-30"></i>
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
                                                 <h6 class="text-uppercase mb-1">
                                                     {{ translate('vendor_image') }}
                                                     <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                                 </h6>
                                                 <div class="text-muted text-capitalize">
                                                     {{ translate('image_ratio') . ' ' . '1:1' }}
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>

                         <div class="border p-3 p-xl-4 rounded mt-4">
                             <h4 class="mb-3 text-capitalize">{{ translate('shop_information') }}</h4>
                             <div class="form-group mb-4">
                                 <label class="mb-2 text-capitalize" for="shop_name">
                                     {{ translate('Shop_Name') }}
                                     <span class="text-danger">*</span>
                                 </label>
                                 <input class="form-control" type="text" name="shop_name"
                                     placeholder="{{ translate('ex') . ': XYZ Shop' }}" required>
                             </div>
                             <div class="form-group mb-4">
                                 <label class="mb-2 text-capitalize" for="shop_address">
                                     {{ translate('Shop_Address') }}
                                     <span class="text-danger">*</span>
                                 </label>
                                 <textarea class="form-control" name="shop_address" rows="4" placeholder="{{ translate('Shop_Address') }}"
                                     required></textarea>
                             </div>

                             <div class="border p-3 p-xl-4 rounded mb-4">
                                 <div class="d-flex flex-column gap-3 align-items-center">
                                     <div class="upload-file">
                                         <input type="file" class="upload-file__input" name="logo"
                                                data-max-size="{{ getFileUploadMaxSize() }}"
                                             accept="{{getFileUploadFormats(skip: '.svg,.gif,.webp')}}">
                                         <div class="upload-file__img">
                                             <div class="temp-img-box">
                                                 <div class="d-flex align-items-center flex-column gap-2">
                                                     <i class="bi bi-upload fs-30"></i>
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
                                         <h6 class="text-uppercase mb-1">
                                             {{ translate('Shop_Image') }}
                                             <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                         </h6>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('image_ratio') . ' ' . '1:1' }}
                                         </div>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('Image Size : Max 2 MB') }}
                                         </div>
                                     </div>
                                 </div>
                             </div>

                             <div class="border p-3 p-xl-4 rounded mb-4">
                                 <div class="d-flex flex-column gap-3 align-items-center">
                                     <div class="upload-file">
                                         <input type="file" class="upload-file__input" name="banner"
                                                data-max-size="{{ getFileUploadMaxSize() }}"
                                             accept="{{getFileUploadFormats(skip: '.svg,.gif')}}" >
                                         <div class="upload-file__img style--two">
                                             <div class="temp-img-box">
                                                 <div class="d-flex align-items-center flex-column gap-2">
                                                     <i class="bi bi-upload fs-30"></i>
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
                                         <h6 class="text-uppercase mb-1">
                                             {{ translate('Shop_Banner') }}
                                             <span class="text-muted fs-12">({{ translate('optional') }})</span>
                                         </h6>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('image_ratio') . ' ' . '2:1' }}
                                         </div>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('Image Size : Max 2 MB') }}
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="border p-3 p-xl-4 rounded mb-4">
                                 <div class="d-flex flex-column gap-3 align-items-center">
                                     <div class="upload-file">
                                         <input type="file" class="upload-file__input" name="bottom_banner"
                                                data-max-size="{{ getFileUploadMaxSize() }}"
                                                accept="{{getFileUploadFormats(skip: '.svg,.gif')}}">
                                         <div class="upload-file__img style--two">
                                             <div class="temp-img-box">
                                                 <div class="d-flex align-items-center flex-column gap-2">
                                                     <i class="bi bi-upload fs-30"></i>
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
                                         <h6 class="text-uppercase mb-1">
                                             {{ translate('Shop_Secondary_Banner') }}
                                             <span class="text-danger">*</span>
                                         </h6>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('image_ratio') . ' ' . '2:1' }}
                                         </div>
                                         <div class="text-muted text-capitalize">
                                             {{ translate('Image Size : Max 2 MB') }}
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>

                         <div class="col-12">
                             <label class="custom-checkbox align-items-center">
                                 <input type="checkbox" class="form-check-input" id="terms-checkbox">
                                 <span class="form-check-label">
                                     {{ translate('i_agree_with_the') }}
                                     <a href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}"
                                         target="_blank" class="text-decoration-underline color-bs-primary-force">
                                         {{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الشروط والأحكام وسياسة الخصوصية' : 'Terms and Conditions and Privacy Policy' }}
                                     </a>
                                 </span>
                             </label>
                         </div>
                         <div class="d-flex justify-content-end mt-4 mb-2 gap-2">
                             <button type="button" class="btn btn-secondary back-to-main-page">
                                 {{ translate('back') }}
                             </button>
                             <button type="submit" class="btn btn-primary disabled" id="vendor-apply-submit">
                                 {{ translate('submit') }}
                             </button>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>
