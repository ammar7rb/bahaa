(() => {
    'use strict';
    const root = document.querySelector('.customer-accounts');
    if (!root || root.dataset.caReady) return;
    root.dataset.caReady = '1';
    const openers = new WeakMap();
    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const openDialog = (id, opener) => {
        const dialog = document.getElementById(id);
        if (!dialog || !root.contains(dialog) || dialog.open) return;
        dialog.classList.remove('is-closing');
        if (opener) openers.set(dialog, opener);
        dialog.showModal();
    };
    const closeDialog = dialog => {
        if (!dialog?.open || dialog.classList.contains('is-closing')) return;
        const finish = () => {
            dialog.close(); dialog.classList.remove('is-closing');
            const opener = openers.get(dialog);
            if (opener?.isConnected) opener.focus({preventScroll: true});
        };
        if (reducedMotion()) { finish(); return; }
        dialog.classList.add('is-closing');
        window.setTimeout(finish, 140);
    };
    root.addEventListener('click', event => {
        const remote = event.target.closest('[data-ca-remote], [data-ca-profile]');
        if (remote) {
            event.preventDefault();
            if (remote.dataset.caLoading) return;
            const url = new URL(remote.href, location.href);
            if (url.origin !== location.origin) return;
            if (remote.hasAttribute('data-ca-profile')) {
                let dialog = document.getElementById('ca-profile-modal');
                if (!dialog) {
                    dialog = document.createElement('dialog'); dialog.id = 'ca-profile-modal'; dialog.className = 'ca-dialog ca-profile-modal';
                    dialog.setAttribute('aria-label', 'ملف العميل الكامل');
                    const header = document.createElement('header'); header.textContent = 'ملف العميل الكامل';
                    const close = document.createElement('button'); close.type='button';close.className='ca-profile-close';close.setAttribute('aria-label','إغلاق ملف العميل');close.title='إغلاق';close.dataset.caClose='';
                    const icon=document.createElement('i');icon.className='fi fi-rr-cross-small';icon.setAttribute('aria-hidden','true');close.append(icon);header.append(close);
                    const frame = document.createElement('iframe');frame.title='بيانات العميل وحركاته';
                    frame.addEventListener('load',()=>{try{const style=frame.contentDocument.createElement('style');style.textContent='html,body{height:auto!important;min-height:100%!important;overflow:auto!important}.v2-header,.v2-rail,.v2-ctxpanel{display:none!important}.app-v2{display:block!important;height:auto!important;min-height:100vh!important;overflow:visible!important}.v2-main{margin:0!important;padding:16px!important;width:100%!important;height:auto!important;overflow:visible!important}';frame.contentDocument.head.append(style);}catch{}});
                    dialog.append(header,frame);root.append(dialog);dialog.addEventListener('cancel',e=>{e.preventDefault();closeDialog(dialog)});
                }
                dialog.querySelector('iframe').src=url.href;openDialog(dialog.id,remote);return;
            }
            remote.dataset.caLoading='1';
            fetch(url.href,{credentials:'same-origin',headers:{Accept:'text/html'}}).then(response=>{if(!response.ok)throw Error();return response.text()}).then(html=>{
                const doc=new DOMParser().parseFromString(html,'text/html'),id='ca-account-'+url.searchParams.get('focus_action'),source=doc.getElementById(id);
                if(!source)throw Error();const old=document.getElementById(id);if(old&&!old.open)old.remove();
                const dialog=document.importNode(source,true);root.append(dialog);dialog.addEventListener('cancel',e=>{e.preventDefault();closeDialog(dialog)});openDialog(id,remote);
            }).catch(()=>{
                let dialog=document.getElementById('ca-load-error');
                if(!dialog){dialog=document.createElement('dialog');dialog.id='ca-load-error';dialog.className='ca-dialog';dialog.setAttribute('aria-label','تعذر تحميل الإجراء');
                    const message=document.createElement('p');message.textContent='تعذر تحميل إجراء الحساب. أغلق النافذة وأعد المحاولة؛ لم يتم تنفيذ أي تغيير.';
                    const close=document.createElement('button');close.type='button';close.className='btn btn-secondary';close.textContent='إغلاق';close.dataset.caClose='';dialog.append(message,close);root.append(dialog);dialog.addEventListener('cancel',e=>{e.preventDefault();closeDialog(dialog)});}
                openDialog(dialog.id,remote);
            }).finally(()=>{delete remote.dataset.caLoading});
            return;
        }
        const opener = event.target.closest('[data-ca-open]');
        if (opener) openDialog(opener.dataset.caOpen, opener);
        const closer = event.target.closest('[data-ca-close]');
        if (closer) closeDialog(closer.closest('dialog'));
    });
    root.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(dialog); });
    });
    let lookupTimer;
    root.addEventListener('input',event=>{
        const input=event.target.closest('[data-ca-lookup]');if(!input)return;
        const form=input.closest('form');form.elements.customer_id.value='';form.elements.confirmation.value='';clearTimeout(lookupTimer);
        const term=input.value.trim();if(term.length<2){form.querySelector('[data-ca-results]').replaceChildren();return;}
        lookupTimer=setTimeout(async()=>{try{const url=new URL(input.dataset.caLookup);url.searchParams.set('q',term);const response=await fetch(url,{credentials:'same-origin'});if(!response.ok)throw Error();const rows=await response.json();if(input.value.trim()!==term)return;const list=form.querySelector('[data-ca-results]');list.replaceChildren();rows.forEach(row=>{const button=document.createElement('button');button.type='button';button.className='btn btn-outline-primary';button.textContent=row.label;button.onclick=()=>{form.elements.customer_id.value=row.id;form.elements.confirmation.value=row.label.split(' — ')[0];form.querySelector('[data-ca-selected]').textContent='العميل المختار: '+row.label;list.replaceChildren()};list.append(button)});if(!rows.length)list.textContent='لا توجد نتائج';}catch{form.querySelector('[data-ca-results]').textContent='تعذر البحث؛ أعد المحاولة.';}},250);
    });
    const submittedLabels = new WeakMap();
    root.addEventListener('submit',event=>{
        const form=event.target;if(!form.matches('[data-ca-financial]'))return;
        if(form.dataset.submitting){event.preventDefault();return;}
        form.dataset.submitting='1';form.setAttribute('aria-busy','true');
        form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>{submittedLabels.set(button,button.innerHTML);button.disabled=true;button.textContent='جارٍ التحقق من نتيجة العملية…';});
    });
    window.addEventListener('pageshow', () => {
        root.querySelectorAll('[data-ca-financial][data-submitting]').forEach(form => {
            delete form.dataset.submitting; form.removeAttribute('aria-busy');
            form.querySelectorAll('button').forEach(button => {
                if (submittedLabels.has(button)) { button.innerHTML = submittedLabels.get(button); button.disabled = false; }
            });
        });
    });
    root.querySelectorAll('[data-ca-subscription]').forEach(form => {
        const sync = () => {
            const option = form.elements.package_id.selectedOptions[0];
            if (!option) return;
            const price = form.elements.operation.value === 'renew_limit' ? option.dataset.renewal : option.dataset.price;
            form.elements.quote_token.value = option.dataset.token;
            form.elements.expected_amount.value = price || '';
            form.querySelector('[data-ca-price]').textContent = price ? `رسوم العملية: ${price} ج.م؛ تُخصم من أموال المحفظة.` : 'لم تُحدد رسوم هذه العملية؛ لا يمكن تنفيذ الخصم.';
            form.querySelector('[type="submit"]').disabled = !price || Number(price) <= 0;
        };
        form.elements.operation.addEventListener('change', () => {
            if (form.elements.operation.value === 'renew_limit') form.elements.package_id.value = form.elements.operation.dataset.activePackage;
            sync();
        });
        form.addEventListener('change', sync); sync();
    });
    if (root.dataset.caInitialDialog) openDialog(root.dataset.caInitialDialog);
})();
