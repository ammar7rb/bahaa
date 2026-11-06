@include('admin-views.vendor.profile.modal-start',['modalId'=>'vendor-info-modal','modalTitle'=>'معلومات الحساب والمتجر'])
<dl class="customer-profile-data"><div><dt>العنوان</dt><dd>{{ $seller->shop?->address ?: ($seller->shop_address ?: '—') }}</dd></div><div><dt>آخر تحديث للحساب</dt><dd>{{ $seller->updated_at?->format('Y/m/d H:i') ?: '—' }}</dd></div></dl>
@include('admin-views.vendor.profile.modal-end')
@include('admin-views.vendor.profile.modal-start',['modalId'=>'vendor-history-modal','modalTitle'=>'سجل التحديثات والإجراءات'])
<p>سجل الاشتراكات محفوظ في قسم الباقة؛ المعاملات والتسويات في القسم المالي.</p>
@include('admin-views.vendor.profile.modal-end')
