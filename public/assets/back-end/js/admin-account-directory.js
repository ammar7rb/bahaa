(() => {
    'use strict';
    if (window.adminAccountDirectoryStarted) return;
    window.adminAccountDirectoryStarted=true;
    function initialize() {
    const root=document.querySelector('.account-directory');
    if (!root || root.dataset.bound) return;
    root.dataset.bound='true';
    if (window.matchMedia('(max-width:650px)').matches) root.querySelector('[data-directory-filters]')?.removeAttribute('open');
    const dialog=root.querySelector('#directory-delete-dialog'), form=root.querySelector('#directory-delete-form');
    let trigger;
    root.addEventListener('click',event=>{
        const button=event.target.closest('[data-delete-account]');
        if (button) {
            trigger=button; form.reset(); form.action=button.dataset.url;
            form.elements.confirmation.setCustomValidity('');
            root.querySelector('#directory-delete-account').textContent=button.dataset.name+' · '+button.dataset.identifier;
            form.elements.confirmation.placeholder=button.dataset.identifier;
            form.elements.confirmation.dataset.expected=button.dataset.identifier;
            dialog.showModal(); form.elements.confirmation.focus();
        }
        if (event.target.closest('[data-cancel-delete]')) dialog.close();
    });
    dialog.addEventListener('close',()=>trigger?.focus());
    form.addEventListener('input',()=>form.elements.confirmation.setCustomValidity(''));
    form.addEventListener('submit',event=>{
        if(form.elements.confirmation.value!==form.elements.confirmation.dataset.expected){event.preventDefault();form.elements.confirmation.setCustomValidity(form.elements.confirmation.placeholder);form.elements.confirmation.reportValidity();}
    });
    root.querySelectorAll('form').forEach(form=>form.addEventListener('submit',event=>{
        if(event.defaultPrevented) return;
        root.setAttribute('aria-busy','true');
        form.querySelectorAll('button[type=submit],button:not([type])').forEach(button=>button.disabled=true);
    }));
    }
    initialize();
    document.addEventListener('v2:page-loaded',initialize);
    window.addEventListener('pageshow',()=>{
        initialize();
        const root=document.querySelector('.account-directory');
        root?.removeAttribute('aria-busy');
        root?.querySelectorAll('button:disabled').forEach(button=>button.disabled=false);
    });
})();
