(() => {
    const root = document.querySelector('[data-customer-profile-history]');
    if (!root) return;
    const workspace = root.closest('.customer-profile-workspace') || document;
    const labels = {f_name:'الاسم الأول',l_name:'الاسم الأخير',phone:'الهاتف الأساسي',email:'البريد',image:'الصورة',password:'كلمة المرور',contact_phones:'أرقام التواصل',contact_person_name:'اسم المستلم',state:'المحافظة',city:'المدينة',district:'الحي',area:'المنطقة',street:'الشارع',building_number:'رقم المنزل',floor:'الدور',unit_number:'الشقة / المكتب',company_details:'بيانات الشركة / المكتب',landmark:'علامة مميزة',address:'تفاصيل العنوان',zip:'الرمز البريدي',country:'البلد',address_type:'نوع العنوان',is_billing:'عنوان الفاتورة'};
    const actions = {profile_updated:'تحديث الملف الشخصي',contact_phones_updated:'تحديث أرقام التواصل',address_created:'إضافة عنوان',address_updated:'تعديل عنوان',address_deleted:'حذف عنوان'};
    const text = value => Array.isArray(value) ? (value.join('، ') || '—') : typeof value === 'boolean' ? (value ? 'نعم' : 'لا') : value == null || value === '' ? '—' : String(value);
    const node = (tag, value, className) => { const el = document.createElement(tag); if (value !== undefined) el.textContent = text(value); if (className) el.className = className; return el; };
    const fields = entries => { const list = node('dl', undefined, 'customer-profile-data'); for (const [label, value] of entries) { const row = node('div'); row.append(node('dt', label), node('dd', value)); list.append(row); } return list; };
    const number = value => value == null ? '—' : Number(value).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const status = root.querySelector('[data-profile-sync-status]');
    const retry = root.querySelector('[data-profile-retry]');
    let page = 1, last = 1, busy = false, previous = '';
    async function load() {
        if (busy || document.hidden) return;
        busy = true; root.setAttribute('aria-busy', 'true');
        try {
            const url = new URL(root.dataset.url, location.origin); url.searchParams.set('page', String(page));
            const response = await fetch(url, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store'});
            if (!response.ok) throw new Error('snapshot unavailable');
            const data = await response.json(); last = data.last_page; page = data.current_page;
            const signature = JSON.stringify(data);
            if (signature !== previous) {
                if (data.subscription) {
                    const sub = data.subscription, allowance = sub.allowance;
                    const values = {package:sub.package_name || 'لا توجد باقة حالية', available:number(allowance?.available_limit), granted:number(allowance?.granted_limit), committed:number(allowance?.committed_limit), state:sub.active?'نشط':'غير نشط', expiry:sub.expires_at?new Date(sub.expires_at).toLocaleString('ar-EG'):'—'};
                    for (const [key, value] of Object.entries(values)) workspace.querySelectorAll(`[data-customer-live-${key}]`).forEach(el => { el.textContent = value; if (key === 'state') el.dataset.active = String(Boolean(sub.active)); });
                }
                const c = data.customer;
                root.querySelector('[data-profile-live-summary]').replaceChildren(fields([['مرجع الحساب',c.reference],['الاسم',c.name],['الهاتف الأساسي',c.phone],['البريد الإلكتروني',c.email],['أرقام التواصل',c.contact_phones],['آخر تحديث محفوظ',c.profile_changed_at || c.updated_at]]));
                workspace.querySelectorAll('[data-customer-live-name]').forEach(el => el.textContent = c.name || c.reference);
                for (const key of ['phone','email']) workspace.querySelectorAll(`[data-customer-live-${key}]`).forEach(el => { el.textContent = c[key] || '—'; if (c[key]) el.href = `${key === 'phone' ? 'tel' : 'mailto'}:${c[key]}`; else el.removeAttribute('href'); });
                workspace.querySelectorAll('[data-customer-live-image]').forEach(el => { if (c.image_url) el.src = c.image_url; });
                workspace.querySelectorAll('[data-profile-address-count]').forEach(el => el.textContent = String(data.addresses.length));
                const addressTarget = document.querySelector('#savedAddressOffcanvas [data-profile-live-addresses]');
                if (addressTarget) addressTarget.replaceChildren(...(data.addresses.length ? data.addresses.map(address => fields(Object.entries(labels).filter(([key]) => key !== 'password' && address[key] != null && address[key] !== '').map(([key,label]) => [label,address[key]]))) : [node('p','لا توجد عناوين محفوظة.')]));
                const events = data.history.map(event => {
                    const item = node('article', undefined, 'customer-profile-history-event');
                    const header = node('header'); header.append(node('strong',actions[event.action] || event.action),node('small',`${event.created_at} · ${event.actor_type === 'customer' ? 'العميل' : 'الإدارة'}`)); item.append(header);
                    for (const [field, change] of Object.entries(event.changes)) {
                        const row = node('div', undefined, 'customer-profile-history-change'); row.append(node('strong',labels[field] || field));
                        if (field === 'password') row.append(node('p','تم تغيير كلمة المرور؛ لا تعرض قيمتها.'));
                        else { const values = node('div'); for (const [key,label] of [['from','السابق'],['to','الحالي']]) { const value = node('span'); value.append(node('small',label),node('span',change[key])); values.append(value); } row.append(values); }
                        item.append(row);
                    }
                    return item;
                });
                root.querySelector('[data-profile-change-list]').replaceChildren(...(events.length ? events : [node('p','لا توجد تحديثات مسجلة بعد.')]));
                previous = signature;
            }
            const syncMessage = data.history_ready ? 'البيانات محدثة تلقائيًا' : 'بيانات العميل محدثة؛ سجل التحديثات غير متاح حاليًا.';
            if (status.textContent !== syncMessage) status.textContent = syncMessage;
            root.dataset.syncState = 'ready'; if (retry) retry.hidden = true;
            root.querySelector('[data-history-page]').textContent = `${page} / ${last}`;
            root.querySelector('[data-history-previous]').disabled = page <= 1;
            root.querySelector('[data-history-next]').disabled = page >= last;
        } catch (_) {
            status.textContent = 'تعذر تحديث البيانات. يمكنك إعادة المحاولة.'; root.dataset.syncState = 'error'; if (retry) retry.hidden = false;
            if (!previous) root.querySelector('[data-profile-change-list]').replaceChildren(node('p','تعذر تحميل السجل. استخدم إعادة المحاولة أعلاه.'));
        } finally { busy = false; root.setAttribute('aria-busy','false'); }
    }
    root.querySelector('[data-history-previous]').onclick = () => { if (!busy && page > 1) { page--; load(); } };
    root.querySelector('[data-history-next]').onclick = () => { if (!busy && page < last) { page++; load(); } };
    if (retry) retry.onclick = load;
    document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
    const timer = setInterval(load, 5000); window.addEventListener('pagehide', () => clearInterval(timer), {once:true}); load();
})();
