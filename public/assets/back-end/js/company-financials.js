(() => {
    'use strict';
    const root = document.querySelector('.company-financials');
    if (!root) return;
    const content = root.querySelector('.cf-content');
    const tabs = root.querySelector('.cf-tabs');
    const revealActiveTab = () => {
        const active = tabs.querySelector('[aria-current]');
        if (!active) return;
        const bounds = tabs.getBoundingClientRect();
        const item = active.getBoundingClientRect();
        if (item.left < bounds.left || item.right > bounds.right) tabs.scrollBy({left:item.left-bounds.left-(bounds.width-item.width)/2,top:0,behavior:'instant'});
    };
    revealActiveTab();
    if (!content) return;
    // Native navigation and form submission remain fully usable without JavaScript.
    root.querySelector('[data-cf-form]')?.addEventListener('submit', () => {
        root.querySelector('.cf-load').hidden = false;
        content.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        root.querySelector('.cf-load').hidden = true;
        content.removeAttribute('aria-busy');
        revealActiveTab();
    });
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    // Only a pointer-initiated tab change receives a brief content transition.
    // Keyboard navigation, back/forward and reduced-motion keep immediate content.
    try {
        const arriving = sessionStorage.getItem('sigma-company-tab');
        sessionStorage.removeItem('sigma-company-tab');
        if (arriving === location.search && !reduced.matches) content.animate([{opacity:.7},{opacity:1}],{duration:140,easing:'cubic-bezier(.23,1,.32,1)'});
    } catch (_) { /* Storage may be disabled; navigation remains native. */ }
    tabs.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.detail === 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || reduced.matches) return;
        try { sessionStorage.setItem('sigma-company-tab',new URL(link.href).search); } catch (_) { /* Optional visual feedback only. */ }
    });
    root.querySelectorAll('details').forEach(details => {
        let pointer = false;
        let animation;
        details.querySelector('summary').addEventListener('pointerdown', () => { pointer = true; });
        details.querySelector('summary').addEventListener('keydown', () => { pointer = false; });
        details.addEventListener('toggle', () => {
            animation?.cancel();
            const body = details.querySelector('.cf-rule-body');
            if (body && details.open && pointer && !reduced.matches) animation = body.animate([{opacity: .55, transform: 'translateY(-3px)'}, {opacity: 1, transform: 'translateY(0)'}], {duration: 150, easing: 'cubic-bezier(.23,1,.32,1)'});
            pointer = false;
        });
    });
})();
