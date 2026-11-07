document.querySelectorAll('.homepage-product-sorter').forEach(sorter => {
    const list = sorter.querySelector('[data-product-sort-list]');
    const order = sorter.querySelector('[data-product-order]');
    const mode = sorter.closest('tr').querySelector('select[name$="[source_mode]"]');
    let dragged = null;
    const sync = () => {
        order.value = [...list.children].map(item => item.dataset.productId).join(',');
        mode.value = 'manual';
        [...list.children].forEach((item, index) => {
            item.querySelector('[data-move="up"]').disabled = index === 0;
            item.querySelector('[data-move="down"]').disabled = index === list.children.length - 1;
        });
    };
    list.addEventListener('dragstart', event => {
        dragged = event.target.closest('[data-product-id]');
        if (!dragged) return;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.dataset.productId);
        dragged.style.opacity = '.5';
    });
    list.addEventListener('dragover', event => {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('[data-product-id]');
        if (!target || target === dragged) return;
        const bounds = target.getBoundingClientRect();
        list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
    });
    list.addEventListener('drop', event => { event.preventDefault(); sync(); });
    list.addEventListener('dragend', () => { if (dragged) dragged.style.opacity = ''; dragged = null; sync(); });
    list.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const item = button.closest('[data-product-id]');
        if (button.hasAttribute('data-remove')) item.remove();
        else if (button.dataset.move === 'up' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
        else if (button.dataset.move === 'down' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
        sync();
    });
    sorter.querySelector('[data-add-product]').addEventListener('change', event => {
        const select = event.target;
        const id = select.value;
        if (!id || [...list.children].some(item => item.dataset.productId === id)) { select.value = ''; return; }
        const item = document.createElement('li');
        item.className = 'd-flex gap-2 align-items-center border rounded p-2 mb-2 bg-white';
        item.draggable = true; item.dataset.productId = id;
        const name = document.createElement('span'); name.className = 'flex-grow-1'; name.textContent = select.selectedOptions[0].textContent; item.append(name);
        [['up', '↑'], ['down', '↓'], ['remove', '×']].forEach(([action, label]) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-secondary'; button.textContent = label;
            if (action === 'remove') button.setAttribute('data-remove', ''); else button.dataset.move = action;
            button.setAttribute('aria-label', action); item.append(button);
        });
        list.append(item); select.value = ''; sync();
    });
});
