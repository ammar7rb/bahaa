@extends('layouts.admin.app')
@section('title', 'المحتوى القانوني')
@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-4"><h1 class="page-header-title">المحتوى القانوني</h1><p class="text-muted">حرّر النسختين العربية والإنجليزية عبر صفحات المحتوى الحالية، ثم عاين النسخة المنشورة.</p></div>
    <div class="row g-3">
        @foreach(['terms-and-conditions' => 'الشروط والأحكام', 'privacy-policy' => 'سياسة الخصوصية'] as $slug => $label)
            @php($page = $pages->get($slug))
            <section class="col-12 col-md-6"><div class="card h-100"><div class="card-body">
                <h2 class="h5">{{ $label }}</h2>
                <p class="text-muted">{{ $page ? 'آخر حفظ: '.$page->updated_at?->format('Y/m/d H:i') : 'الصفحة غير منشأة بعد' }}</p>
                <div class="d-flex flex-wrap gap-2">
                    @if($page)
                        <a class="btn btn-primary" href="{{ route('admin.pages-and-media.update', ['slug' => $slug]) }}">تحرير</a>
                        <a class="btn btn-outline-primary" href="{{ route('business-page.view', ['slug' => $slug, 'source' => 'admin']) }}" target="_blank" rel="noopener">معاينة</a>
                    @else<a class="btn btn-outline-primary" href="{{ route('admin.pages-and-media.list') }}">تهيئة صفحات المحتوى</a>@endif
                </div>
            </div></div></section>
        @endforeach
    </div>
</main>
@endsection
