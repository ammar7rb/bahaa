document.addEventListener('click', function (event) {
    const add = event.target.closest('[data-repeat-add]');
    const remove = event.target.closest('[data-repeat-remove]');
    if (add) {
        const list = document.querySelector('[data-repeat-list="' + add.dataset.repeatAdd + '"]');
        if (!list || list.children.length >= 10) return;
        const template = document.querySelector('[data-repeat-template="' + add.dataset.repeatAdd + '"]');
        const row = template.content.firstElementChild.cloneNode(true);
        list.appendChild(row);
        row.querySelector('input').focus();
    }
    if (remove) {
        const row = remove.closest('.vendor-repeat-row');
        if (row.parentElement.children.length > 1) row.remove();
    }
});
setInterval(async function () {
    if (document.hidden) return;
    const endpoint = document.getElementById('vendor-inbox-counts-url');
    if (!endpoint) return;
    try {
        const response = await fetch(endpoint.dataset.url, {headers: {'Accept': 'application/json'}});
        if (!response.ok) return;
        const counts = await response.json();
        document.querySelectorAll('[data-inbox-count]').forEach(function (badge) {
            const count = Number(counts[badge.dataset.inboxCount] || 0);
            badge.textContent = '+' + count;
            badge.hidden = count === 0;
            const link = badge.closest('a[aria-label]');
            if (link) link.setAttribute('aria-label', link.title + (count ? ' +' + count : ''));
        });
    } catch (error) { /* Retain server-confirmed counts during connectivity loss. */ }
}, 30000);
