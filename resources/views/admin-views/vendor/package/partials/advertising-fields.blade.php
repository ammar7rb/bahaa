<div class="row g-3">
    <div class="col-md-6">
        <label for="package-name" class="form-label">اسم الباقة</label>
        <input id="package-name" name="name" class="form-control" value="{{ old('name', $package?->name) }}" maxlength="191" required>
    </div>
    <div class="col-md-3">
        <label for="package-price" class="form-label">السعر النهائي بالجنيه</label>
        <input id="package-price" name="package_price" type="number" class="form-control" min="0.01" step="0.01" value="{{ old('package_price', $package?->package_price) }}" required>
    </div>
    <div class="col-md-3">
        <label for="package-slots" class="form-label">عدد المساحات</label>
        <input id="package-slots" name="product_limit" type="number" class="form-control" min="1" step="1" value="{{ old('product_limit', $package?->product_limit) }}" required>
    </div>
    <div class="col-md-8">
        <label for="package-description" class="form-label">وصف الباقة</label>
        <textarea id="package-description" name="description" class="form-control" maxlength="2000">{{ old('description', $package?->description) }}</textarea>
    </div>
    <div class="col-md-2">
        <label for="package-sort" class="form-label">ترتيب العرض</label>
        <input id="package-sort" name="sort_order" type="number" class="form-control" min="0" value="{{ old('sort_order', $package?->sort_order ?? 0) }}">
    </div>
    <div class="col-md-2">
        <label for="package-status" class="form-label">إتاحة الشراء</label>
        <select id="package-status" name="status" class="form-select"><option value="1" @selected(old('status', $package?->status ?? true))>متاحة</option><option value="0" @selected(!old('status', $package?->status ?? true))>معطلة</option></select>
    </div>
</div>
<p class="text-muted mt-3">مدة جميع المساحات 30 يومًا من بداية الاشتراك. تعديل الباقة يؤثر على المشتريات الجديدة فقط، ويحافظ على شروط الاشتراكات وطلبات الدفع السابقة.</p>
