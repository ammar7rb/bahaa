<?php

namespace App\Http\Requests\Admin\Concerns;

trait HasBusinessPageTranslations
{
    protected function prepareForValidation(): void
    {
        // Keep the legacy fields as the English source for old clients and stable slugs.
        $this->merge([
            'title' => $this->input('translations.en.title'),
            'description' => $this->input('translations.en.description'),
        ]);
        if (!$this->has('id')) {
            $this->merge(['slug' => \Illuminate\Support\Str::slug($this->input('title') ?? '')]);
        }
    }

    protected function translationRules(): array
    {
        return [
            'translations' => 'required|array:ar,en',
            'translations.ar' => 'required|array:title,description',
            'translations.en' => 'required|array:title,description',
            'translations.ar.title' => 'required|string|max:255',
            'translations.en.title' => 'required|string|max:255',
            'translations.ar.description' => 'required|string',
            'translations.en.description' => 'required|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'translations.ar.title' => 'عنوان الصفحة بالعربي',
            'translations.ar.description' => 'محتوى الصفحة بالعربي',
            'translations.en.title' => 'Page title in English',
            'translations.en.description' => 'Page content in English',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (['ar', 'en'] as $language) {
                $key = "translations.$language.description";
                $value = $this->input($key);
                if (is_string($value) && trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($value)))) === '') {
                    $validator->errors()->add($key, $language === 'ar'
                        ? 'أدخل محتوى الصفحة باللغة العربية.' : 'Enter the page content in English.');
                }
            }
        });
    }
}
