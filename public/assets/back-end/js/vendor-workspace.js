(function () {
    'use strict';
    const shell = document.querySelector('.vendor-shell');
    if (!shell) return;
    const sidebar = document.getElementById('v2-ctxpanel');
    const toggle = document.getElementById('v2-sidebar-toggle');
    const mobile = () => window.matchMedia('(max-width: 1023px)').matches;
    const header = shell.querySelector('.v2-header');
    const main = shell.querySelector('.v2-main');
    function syncDrawerAccessibility() {
        const open = mobile() && shell.classList.contains('v2-is-open-mobile');
        sidebar.inert = mobile() && !open;
        header.inert = open;
        main.inert = open;
        if (toggle) toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            if (!sidebar.contains(document.activeElement)) sidebar.querySelector('[data-vendor-sidebar-close]')?.focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (sidebar.contains(document.activeElement) && mobile()) toggle?.focus();
        }
    }
    function sidebarState(open) {
        shell.classList.toggle('v2-is-open-mobile', open);
        syncDrawerAccessibility();
    }
    if (toggle) {
        toggle.setAttribute('aria-controls', 'v2-ctxpanel');
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            sidebarState(!shell.classList.contains('v2-is-open-mobile'));
        });
    }
    shell.querySelectorAll('[data-vendor-sidebar-close]').forEach(button => button.addEventListener('click', () => {
        sidebarState(false);
        toggle?.focus();
    }));
    function initSections() {
        sidebar.querySelectorAll('.v2-ctx-section').forEach(section => {
            const heading = section.querySelector('.v2-ctx-head');
            if (section.classList.contains('v2-is-on')) section.classList.add('vendor-section-expanded');
            heading.setAttribute('aria-expanded', String(section.classList.contains('vendor-section-expanded')));
            heading.addEventListener('click', () => {
                const open = section.classList.toggle('vendor-section-expanded');
                heading.setAttribute('aria-expanded', String(open));
            });
        });
        sidebarState(false);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSections);
    else initSections();
    window.addEventListener('resize', syncDrawerAccessibility);
    new MutationObserver(syncDrawerAccessibility).observe(shell, {attributes: true, attributeFilter: ['class']});
    shell.querySelectorAll('.v2-dropdown').forEach(dropdown => {
        const button = dropdown.querySelector('[data-v2-dropdown-toggle]');
        const menu = dropdown.querySelector('.v2-dropdown-menu');
        if (!button || !menu) return;
        const sync = () => {
            const open = dropdown.classList.contains('v2-is-open');
            button.setAttribute('aria-expanded', String(open));
            menu.hidden = !open;
        };
        sync();
        new MutationObserver(sync).observe(dropdown, {attributes: true, attributeFilter: ['class']});
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Tab' && mobile() && shell.classList.contains('v2-is-open-mobile')) {
            const focusable = Array.from(sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex="0"]')).filter(element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first)?.focus();
            }
        }
        if (event.key !== 'Escape') return;
        const open = shell.querySelector('.v2-dropdown.v2-is-open');
        if (open) {
            open.classList.remove('v2-is-open');
            open.querySelector('[data-v2-dropdown-toggle]')?.focus();
        } else if (shell.classList.contains('v2-is-open-mobile')) {
            sidebarState(false);
            toggle?.focus();
        }
    });
}());

(function () {
    const select = document.getElementById('withdrawal_definition');
    function syncMethod() { document.querySelectorAll('[data-withdrawal-definition]').forEach(el => { el.hidden = el.dataset.withdrawalDefinition !== select.value; el.disabled = el.hidden; }); }
    if (select) {
        select.addEventListener('change', syncMethod); syncMethod();
        document.querySelectorAll('[data-edit-withdrawal]').forEach(button => button.addEventListener('click', () => {
            const method = JSON.parse(button.dataset.editWithdrawal);
            document.getElementById('withdrawal-method-form').reset();
            document.getElementById('withdrawal_method_id').value = method.id;
            document.getElementById('withdrawal_method_name').value = method.method_name;
            select.value = method.withdraw_method_id; syncMethod();
            const group = Array.from(document.querySelectorAll('[data-withdrawal-definition]')).find(el => !el.disabled);
            if (group) group.querySelectorAll('input').forEach(input => { const key = input.name.slice(12, -1); input.value = method.method_info[key] || ''; });
            document.getElementById('withdrawal_method_name').focus();
        }));
    }
    const noDates = document.getElementById('no_production_expiry_dates');
    if (noDates) {
        const sync = () => ['production_date','expiry_date'].forEach(id => { const input = document.getElementById(id); if (!input) return; input.disabled = noDates.checked; input.required = !noDates.checked; if(noDates.checked) input.value=''; });
        noDates.addEventListener('change', sync); sync();
    }
    const stock = document.querySelector('[name="current_stock"]'), unit = document.getElementById('sale_unit_type');
    if (stock && unit) {
        const sync = () => {
            const wrapper = document.getElementById('sale_unit_selector'); if(wrapper) wrapper.hidden = !(Number(stock.value) > 0);
            const isPackage = unit.value === 'package';
            const price = document.querySelector('[name="unit_price"]');
            const priceLabel = price && (document.querySelector('label[for="'+price.id+'"]') || price.parentElement.querySelector('label'));
            const stockLabel = document.querySelector('label[for="'+stock.id+'"]') || stock.parentElement.querySelector('label');
            if(priceLabel) priceLabel.textContent = isPackage ? unit.dataset.packagePrice : unit.dataset.piecePrice;
            if(stockLabel) stockLabel.textContent = isPackage ? unit.dataset.packageQuantity : unit.dataset.pieceQuantity;
        };
        stock.addEventListener('input',sync); unit.addEventListener('change',sync); sync();
    }
})();
