(() => {
    'use strict';
    document.querySelectorAll('[data-contact-phone-editor]').forEach(editor => {
        const form = editor.closest('form');
        const add = form?.querySelector('[data-add-contact-phone]');
        if (!add) return;
        const list = editor.querySelector('[data-contact-phones]');
        const status = editor.querySelector('[data-contact-phone-status]');
        const update = () => { add.disabled = list.children.length >= 20; };
        editor.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-contact-phone]');
            if (!remove) return;
            const row = remove.closest('.customer-contact-phone-row');
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove(); update();
            (next?.querySelector('input') || add).focus();
        });
        add.addEventListener('click', () => {
            if (list.children.length >= 20) return;
            const row = document.createElement('div'); row.className = 'customer-contact-phone-row';
            const input = document.createElement('input');
            Object.assign(input, {name:'phones[]', type:'tel', dir:'ltr', inputMode:'tel', autocomplete:'tel', maxLength:20, required:true, className:'form-control', placeholder:editor.dataset.label});
            input.setAttribute('aria-label', editor.dataset.label);
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'customer-contact-icon customer-contact-remove';
            remove.dataset.removeContactPhone = ''; remove.title = editor.dataset.removeLabel;
            remove.setAttribute('aria-label', editor.dataset.removeLabel);
            const existing = list.querySelector('[data-remove-contact-phone] svg');
            if (existing) remove.append(existing.cloneNode(true));
            else {
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                for (const [key, value] of Object.entries({width:'20',height:'20',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','aria-hidden':'true'})) svg.setAttribute(key,value);
                const path = document.createElementNS(svg.namespaceURI,'path'); path.setAttribute('d','m6 6 12 12M6 18 18 6'); svg.append(path); remove.append(svg);
            }
            row.append(input,remove); list.append(row); update(); input.focus();
            status.textContent = editor.dataset.label;
        });
        update();
    });
})();
