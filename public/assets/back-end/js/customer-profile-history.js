(() => {
    const root=document.querySelector('[data-customer-profile-history]');if(!root)return;
    const labels={f_name:'الاسم الأول',l_name:'الاسم الأخير',phone:'الهاتف الأساسي',email:'البريد',image:'الصورة',password:'كلمة المرور',contact_phones:'أرقام التواصل',contact_person_name:'اسم المستلم',state:'المحافظة',city:'المدينة',district:'الحي',area:'المنطقة',street:'الشارع',building_number:'رقم المنزل',floor:'الدور',unit_number:'الشقة / المكتب',company_details:'بيانات الشركة / المكتب',landmark:'علامة مميزة',address:'تفاصيل العنوان',zip:'الرمز البريدي',country:'البلد',address_type:'نوع العنوان',is_billing:'عنوان الفاتورة'};
    const actions={profile_updated:'تحديث الملف الشخصي',contact_phones_updated:'تحديث أرقام التواصل',address_created:'إضافة عنوان',address_updated:'تعديل عنوان',address_deleted:'حذف عنوان'};
    let page=1,last=1,busy=false,previous='';
    const text=value=>Array.isArray(value)?(value.join('، ') || '—'):(typeof value==='boolean'?(value?'نعم':'لا'):(value==null||value===''?'—':String(value)));
    const p=value=>{const node=document.createElement('p');node.textContent=value;return node;};
    async function load(){
        if(busy||document.hidden)return;busy=true;
        try{
            const url=new URL(root.dataset.url,location.origin);url.searchParams.set('page',String(page));
            const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error();
            const data=await response.json();last=data.last_page;
            const signature=JSON.stringify(data);
            if(signature!==previous){
                previous=signature;
                const c=data.customer;
                root.querySelector('[data-profile-live-summary]').replaceChildren(p(`${c.reference} · ${c.name}`),p(`الهاتف الأساسي: ${text(c.phone)} · البريد: ${text(c.email)}`),p(`أرقام التواصل: ${text(c.contact_phones)}`),p(`آخر تحديث محفوظ: ${text(c.profile_changed_at || c.updated_at)}`));
                document.querySelectorAll('[data-customer-live-name]').forEach(node=>node.textContent=c.name);
                document.querySelectorAll('[data-customer-live-phone]').forEach(node=>{node.textContent=c.phone || '—';if(c.phone)node.href=`tel:${c.phone}`;else node.removeAttribute('href');});
                document.querySelectorAll('[data-customer-live-email]').forEach(node=>{node.textContent=c.email || '—';if(c.email)node.href=`mailto:${c.email}`;else node.removeAttribute('href');});
                document.querySelectorAll('[data-customer-live-image]').forEach(node=>node.src=c.image_url);
                const addresses=data.addresses.map(a=>p(Object.keys(labels).filter(key=>key!=='password'&&a[key]!=null&&a[key]!=='').map(key=>`${labels[key]}: ${text(a[key])}`).join(' · ')));
                root.querySelector('[data-profile-live-addresses]').replaceChildren(...(addresses.length?addresses:[p('لا توجد عناوين محفوظة.')]));
                const events=data.history.map(event=>{
                    const item=document.createElement('article');item.className='border-bottom py-3';
                    item.append(p(`${actions[event.action] || event.action} · ${event.created_at} · ${event.actor_type==='customer'?'العميل':'الإدارة'}`));
                    Object.entries(event.changes).forEach(([field,change])=>item.append(p(field==='password'?'تم تغيير كلمة المرور؛ لا تعرض قيمتها.':`${labels[field] || field}: ${text(change.from)} ← ${text(change.to)}`)));
                    return item;
                });
                root.querySelector('[data-profile-change-list]').replaceChildren(...(events.length?events:[p('لا توجد تحديثات مسجلة بعد.')]));
            }
            root.querySelector('[data-profile-sync-status]').textContent=data.history_ready?'البيانات محدثة تلقائياً.':'سجل التحديثات غير متاح حالياً.';
            root.querySelector('[data-history-page]').textContent=`${page} / ${last}`;
            root.querySelector('[data-history-previous]').disabled=page<=1;root.querySelector('[data-history-next]').disabled=page>=last;
        }catch(_){root.querySelector('[data-profile-sync-status]').textContent='تعذر تحديث البيانات. ستتم إعادة المحاولة تلقائياً.';}finally{busy=false;}
    }
    root.querySelector('[data-history-previous]').onclick=()=>{if(!busy&&page>1){page--;load();}};
    root.querySelector('[data-history-next]').onclick=()=>{if(!busy&&page<last){page++;load();}};
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
    const timer=setInterval(load,5000);window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});load();
})();
