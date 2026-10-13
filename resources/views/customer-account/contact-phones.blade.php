@if(auth('customer')->check() && request()->is('user-profile','user-account'))
@php($contactPhones = app(\App\Services\CustomerContactPhoneService::class)->list((int)auth('customer')->id()))
<section class="container py-3" aria-label="أرقام التواصل الإضافية">
    <div class="card card-body">
        <h2 class="h5">أرقام التواصل الإضافية</h2><p>الرقم الأساسي لتسجيل الدخول: <bdi>{{ auth('customer')->user()->phone }}</bdi>. الأرقام التالية للتواصل.</p>
        @if(session('contact_phones_message'))<p role="status">{{ session('contact_phones_message') }}</p>@endif
        @error('phones')<p role="alert" class="text-danger">{{ $message }}</p>@enderror
        @foreach($errors->get('phones.*') as $phoneErrors)
            @foreach($phoneErrors as $message)
                <p role="alert" class="text-danger">{{ $message }}</p>
            @endforeach
        @endforeach
        <form method="post" action="{{ route('customer.contact-phones.update') }}" data-contact-phone-form>@csrf
            <input type="hidden" name="phones" value="">
            <div data-contact-phones>
            @foreach(old('phones',$contactPhones) ?: [] as $phone)
                <div class="d-flex gap-2 mb-2"><input name="phones[]" aria-label="رقم تواصل إضافي" class="form-control" type="tel" dir="ltr" maxlength="20" required value="{{ $phone }}"><button type="button" class="btn btn-outline-danger" data-remove-phone>إزالة</button></div>
            @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary" data-add-phone>إضافة رقم</button>
            <button class="btn btn-primary" type="submit">حفظ أرقام التواصل</button>
        </form>
    </div>
</section>
@push('script')
<script>
document.querySelectorAll('[data-contact-phone-form]').forEach(form=>{
    const list=form.querySelector('[data-contact-phones]');
    form.addEventListener('click',event=>{if(event.target.matches('[data-remove-phone]')) event.target.parentElement.remove();});
    form.querySelector('[data-add-phone]').addEventListener('click',()=>{
        if(list.children.length>=20)return;
        const row=document.createElement('div');row.className='d-flex gap-2 mb-2';
        const input=document.createElement('input');input.name='phones[]';input.type='tel';input.dir='ltr';input.maxLength=20;input.required=true;input.className='form-control';input.setAttribute('aria-label','رقم تواصل إضافي');
        const button=document.createElement('button');button.type='button';button.dataset.removePhone='';button.className='btn btn-outline-danger';button.textContent='إزالة';row.append(input,button);list.append(row);input.focus();
    });
});
</script>
@endpush
@endif
