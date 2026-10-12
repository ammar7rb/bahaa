@if(auth('customer')->check())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const pictures = document.querySelectorAll('.profile-sidebar-avatar img, .profile--info .profile-img img');
    if (!pictures.length) return;
    const picker = document.createElement('input'); picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp'; picker.hidden = true;
    const status = document.createElement('p'); status.setAttribute('role', 'status'); pictures[0].parentElement.after(status); document.body.append(picker);
    let uploading = false;
    pictures.forEach(img => {
        img.tabIndex = 0; img.setAttribute('role', 'button'); img.setAttribute('aria-label', 'تغيير صورة الملف الشخصي'); img.style.cursor = 'pointer';
        const choose = () => { if (!uploading) picker.click(); };
        img.addEventListener('click', choose); img.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(); } });
    });
    picker.addEventListener('change', () => {
        const file = picker.files[0]; if (!file) return;
        if (file.size > 5 * 1024 * 1024 || !['image/jpeg','image/png','image/webp'].includes(file.type)) { status.textContent = 'اختر صورة JPG أو PNG أو WebP بحجم لا يتجاوز 5 ميجابايت.'; picker.value = ''; return; }
        uploading = true; const form = new FormData(); form.append('image', file);
        const xhr = new XMLHttpRequest(); xhr.open('POST', @json(route('customer.profile-image'))); xhr.setRequestHeader('X-CSRF-TOKEN', @json(csrf_token())); xhr.setRequestHeader('Accept', 'application/json'); xhr.timeout = 30000;
        xhr.upload.onprogress = e => { status.textContent = e.lengthComputable ? `جاري رفع الصورة ${Math.round(e.loaded / e.total * 100)}%` : 'جاري رفع الصورة'; };
        xhr.onload = () => { let data; try { data = JSON.parse(xhr.responseText); } catch (_) {} if (xhr.status === 200 && data?.image_full_url?.path) { pictures.forEach(img => img.src = data.image_full_url.path); status.textContent = 'تم تحديث الصورة.'; } else status.textContent = Object.values(data?.errors ?? {}).flat()[0] ?? 'تعذر رفع الصورة. حاول مرة أخرى.'; };
        xhr.onerror = xhr.ontimeout = () => status.textContent = 'تعذر الاتصال. أعد اختيار الصورة للمحاولة.';
        xhr.onloadend = () => { uploading = false; picker.value = ''; }; xhr.send(form);
    });
});
</script>
@endif
