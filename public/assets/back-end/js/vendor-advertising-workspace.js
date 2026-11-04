(() => {
    'use strict';
    document.querySelectorAll('[data-ad-library]').forEach(library => {
        const search = library.querySelector('[data-ad-search]');
        const filter = library.querySelector('[data-ad-filter]');
        const items = [...library.querySelectorAll('[data-ad-item]')];
        const empty = library.querySelector('[data-ad-empty]');
        const refresh = () => {
            const term = search.value.trim().toLocaleLowerCase();
            let matches = 0;
            items.forEach(item => {
                const selected = filter.value === 'all' || item.dataset['ad' + filter.value[0].toUpperCase() + filter.value.slice(1)] === '1';
                item.hidden = !(selected && item.dataset.adName.toLocaleLowerCase().includes(term));
                if (!item.hidden) matches++;
            });
            empty.hidden = matches > 0;
        };
        search.addEventListener('input', refresh);
        filter.addEventListener('change', refresh);
    });
})();
