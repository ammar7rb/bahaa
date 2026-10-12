@php
    $page = $businessPage ?? null;
    $savedTranslations = $page?->translations ?? [];
    $legacyTitle = $page?->getRawOriginal('title') ?? '';
    $legacyDescription = $page?->getRawOriginal('description') ?? '';
    // Existing text stays in its original language until the administrator adds the other version.
    $legacyLanguage = preg_match('/\p{Arabic}/u', strip_tags($legacyDescription . $legacyTitle)) ? 'ar' : 'en';
@endphp

@foreach(['ar' => ['العربية', 'أنت تعدّل المحتوى العربي هنا. أدخل العنوان وكل تفاصيل الصفحة باللغة العربية.', 'rtl'], 'en' => ['English', 'You are editing the English content. Enter the title and all page details in English.', 'ltr']] as $language => [$label, $hint, $direction])
    @php
        $titleValue = old("translations.$language.title", $savedTranslations[$language]['title'] ?? ($legacyLanguage === $language ? $legacyTitle : ''));
        $descriptionValue = old("translations.$language.description", $savedTranslations[$language]['description'] ?? ($legacyLanguage === $language ? $legacyDescription : ''));
    @endphp
    <section class="p-12 p-sm-20 bg-section rounded mb-3" lang="{{ $language }}" dir="{{ $direction }}" aria-labelledby="content-heading-{{ $language }}">
        <h3 id="content-heading-{{ $language }}" class="mb-2">{{ $language === 'ar' ? 'المحتوى العربي' : 'English content' }}</h3>
        <p class="text-body mb-3">{{ $hint }}</p>
        <div class="mb-3">
            <label for="page-title-{{ $language }}" class="form-label">{{ $language === 'ar' ? 'عنوان الصفحة بالعربي' : 'Page title in English' }} <span class="text-danger">*</span></label>
            <input id="page-title-{{ $language }}" class="form-control" name="translations[{{ $language }}][title]" value="{{ $titleValue }}" maxlength="255" required>
            @error("translations.$language.title") <span class="text-danger" role="alert">{{ $message }}</span> @enderror
        </div>
        <div>
            <label id="description-label-{{ $language }}" for="description-page-{{ $language }}" class="form-label">{{ $language === 'ar' ? 'وصف الصفحة بالعربي' : 'Page content in English' }} <span class="text-danger">*</span></label>
            <div id="description-page-{{ $language }}-editor" class="quill-editor" data-direction="{{ $direction }}" data-label="description-label-{{ $language }}">{!! $descriptionValue !!}</div>
            <textarea name="translations[{{ $language }}][description]" id="description-page-{{ $language }}" class="d-none" data-required-msg="{{ $language === 'ar' ? 'أدخل محتوى الصفحة بالعربي' : 'Enter the page content in English' }}" required>{{ $descriptionValue }}</textarea>
            @error("translations.$language.description") <span class="text-danger" role="alert">{{ $message }}</span> @enderror
        </div>
    </section>
@endforeach
