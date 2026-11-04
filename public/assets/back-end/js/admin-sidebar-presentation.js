/* Shared, dependency-free admin sidebar presentation. Rendered inline before main content. */
(function (window) {
  'use strict';
  function all(root, selector) { return Array.from(root.querySelectorAll(selector)); }
  const NAV_ICON_RULES = [
    [/dashboard|overview|home/, 'apps'],
    [/\bpos\b|cash-register/, 'cash-register'],
    [/out[_ -]?for[_ -]?delivery|logistics|shipping|delivery/, 'truck-moving'],
    [/failed|denied|reject|cancel/, 'cross-circle'],
    [/confirmed|approved|activate|activation/, 'check-circle'],
    [/pending|request|review/, 'hourglass-end'],
    [/refund|return/, 'rotate-left'],
    [/order|sales|transaction|invoice/, 'shopping-cart'],
    [/platform-products|in-house|in_house|inhouse-shop/, 'shop'],
    [/product|stock|restock|catalog/, 'box-open'],
    [/categor|attribute|tag/, 'tags'],
    [/brand/, 'badge-check'],
    [/gallery|banner|image/, 'picture'],
    [/vendor-add|add-new-vendor/, 'user-add'],
    [/vendor|seller|store|shop/, 'shop'],
    [/customer|subscriber|people|employee/, 'users'],
    [/role|permission|insurance|policy|vat|tax|seo/, 'shield-check'],
    [/wallet|earning|finance|withdraw|payment/, 'wallet'],
    [/loyalty|bonus|offer|deal|coupon|promotion/, 'star'],
    [/notification|announcement|marketing/, 'megaphone'],
    [/inbox|contact|email|message/, 'envelope'],
    [/support|ticket/, 'headset'],
    [/report|analytics|statistics/, 'chart-histogram'],
    [/blog|page|content|template|document/, 'document'],
    [/firebase|system|setting|setup|config|addon|theme|ai/, 'settings'],
    [/import|upload/, 'file-import'],
    [/list/, 'list']
  ];

  function navigationIconName(el) {
    const parent = el.classList.contains('v2-nav-child')
      ? el.closest('.v2-nav-children')?.previousElementSibling
      : null;
    const source = [
      el.dataset.item,
      el.dataset.pin,
      el.getAttribute('href'),
      el.querySelector('[href]')?.getAttribute('href'),
      el.querySelector('.v2-nav-label, .v2-nav-child-label')?.textContent,
      parent?.dataset?.item
    ].filter(Boolean).join(' ').toLowerCase();

    const match = NAV_ICON_RULES.find(([pattern]) => pattern.test(source));
    return match ? match[1] : 'apps';
  }

  function decorateNavigationIcons(root) {
    all(root, '.v2-ctx-section .v2-nav-item:not([data-v2-pinned])').forEach(item => {
      const btn = item.querySelector(':scope > .v2-nav-btn');
      if (!btn || btn.querySelector(':scope > .v2-nav-icon, :scope > .v2-platform-parent-icon')) return;
      const icon = document.createElement('span');
      icon.className = 'v2-nav-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = `<i class="fi fi-rr-${navigationIconName(item)}"></i>`;
      btn.prepend(icon);
    });

    all(root, '.v2-ctx-section .v2-nav-child:not([data-v2-pinned])').forEach(child => {
      if (child.querySelector(':scope > .v2-nav-child-icon, :scope > .v2-platform-child-icon')) return;
      const label = child.querySelector(':scope > .v2-nav-child-label');
      if (!label) return;
      const icon = document.createElement('span');
      icon.className = 'v2-nav-child-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = `<i class="fi fi-rr-${navigationIconName(child)}"></i>`;
      label.before(icon);
    });
  }

  function injectChildPinButtons(root) {
    all(root, '.v2-nav-child').forEach(child => {
      if (child.querySelector(':scope > .v2-pin-btn')) return;
      const href = child.getAttribute('href') || '';
      if (!href || href.startsWith('javascript:') || href === '#') return;
      const pinId = 'child:' + href;
      child.dataset.pin = pinId;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'v2-pin-btn v2-pin-btn-child';
      btn.dataset.pin = pinId;
      btn.setAttribute('aria-label', 'Pin');
      // Insert BEFORE the count badge so the count always sits at the
      // inline-end edge of the row. Pin button is invisible by default
      // (CSS opacity:0) but reserves space, fades in on row hover.
      const count = child.querySelector(':scope > .v2-nav-child-count');
      if (count) child.insertBefore(btn, count);
      else child.appendChild(btn);
    });
  }
  function applyPins(root, pins) {
    pins = Array.isArray(pins) ? pins : [];
    // Refresh pin button visual state across nav-items, nav-children,
    // AND clones inside the pinned section.
    document.querySelectorAll('.v2-pin-btn').forEach(btn => {
      btn.classList.toggle('v2-is-on', pins.includes(btn.dataset.pin));
    });
    all(root, '.v2-ctx-section').forEach(sec => {
      const pinGroup = sec.querySelector('.v2-ctx-group.v2-is-pinned');
      if (!pinGroup) return;
      const host = pinGroup.querySelector('.v2-ctx-group-body');
      if (!host) return;
      host.innerHTML = '';
      // Pre-compute which parent ids are pinned (with children). When
      // both a parent AND one of its children are pinned, the parent's
      // cloned subtree already includes that child — so we skip the
      // standalone child clone to avoid duplicates.
      const pinnedParentIds = new Set();
      pins.forEach(id => {
        const p = sec.querySelector(`.v2-nav-item.v2-has-children[data-item="${CSS.escape(id)}"]`);
        if (p) pinnedParentIds.add(id);
      });
      // For pinned children whose parent is NOT pinned, render the
      // parent row + a fresh .v2-nav-children container holding ONLY
      // the pinned children — same dropdown UX as the menu, but only
      // the pinned subset. Track containers so siblings of the same
      // parent slot in the same dropdown.
      const childGroupContainers = new Map();
      let any = false;
      pins.forEach(id => {
        // Try parent (.v2-nav-item) first.
        const parent = sec.querySelector(`.v2-nav-item[data-item="${CSS.escape(id)}"]`);
        if (parent && !parent.closest('.v2-ctx-group.v2-is-pinned')) {
          const cloneParent = parent.cloneNode(true);
          cloneParent.dataset.v2Pinned = '1';
          host.appendChild(cloneParent);
          // If the parent has children, also clone the matching
          // .v2-nav-children container right after. Same URL-driven
          // expand rule as the main-section dropdowns: open ONLY when
          // the parent is v2-is-active or one of its children matches
          // the current page (.v2-nav-child.v2-is-on). Otherwise it
          // starts collapsed — the user can click the chevron to peek
          // at the full submenu like any other dropdown.
          if (parent.classList.contains('v2-has-children')) {
            const kids = sec.querySelector(`.v2-nav-children[data-children-for="${CSS.escape(id)}"]`);
            if (kids) {
              const cloneKids = kids.cloneNode(true);
              cloneKids.dataset.v2Pinned = '1';
              // Mark every cloned child too — without this the
              // :not([data-v2-pinned]) lookup in togglePin returns the
              // cloned child first and then can't walk up to the
              // original .v2-nav-children, leaving parentId null and
              // breaking the parent-auto-unpin sync.
              cloneKids.querySelectorAll('.v2-nav-child').forEach(c => c.dataset.v2Pinned = '1');
              host.appendChild(cloneKids);
              const hasActiveChild = parent.classList.contains('v2-is-active') ||
                !!cloneKids.querySelector('.v2-nav-child.v2-is-on');
              cloneKids.classList.toggle('v2-is-collapsed', !hasActiveChild);
              const chev = cloneParent.querySelector('.v2-nav-chev');
              if (chev) chev.classList.toggle('v2-is-open', hasActiveChild);
            }
          }
          any = true;
          return;
        }
        // Then try child (.v2-nav-child) by injected pin id.
        const child = sec.querySelector(`.v2-nav-child[data-pin="${CSS.escape(id)}"]`)
                   || sec.querySelector(`.v2-nav-child:has(> .v2-pin-btn[data-pin="${CSS.escape(id)}"])`);
        if (child && !child.closest('.v2-ctx-group.v2-is-pinned')) {
          // If this child's parent is also pinned, the parent's cloned
          // subtree already shows the child — skip the standalone clone
          // to prevent the same row from appearing twice.
          const kidsContainer = child.closest('.v2-nav-children');
          const parentItemId = kidsContainer ? kidsContainer.dataset.childrenFor : null;
          if (!parentItemId) return;
          if (pinnedParentIds.has(parentItemId)) return;

          // Render the parent row as a dropdown header the FIRST time
          // we see a pinned child of this parent. Defaults to collapsed;
          // the post-loop pass below opens it when any pinned child
          // matches the current page. Subsequent pinned children of the
          // same parent slot into the existing dropdown container.
          let groupChildren = childGroupContainers.get(parentItemId);
          if (!groupChildren) {
            const parentItem = sec.querySelector(`.v2-nav-item[data-item="${CSS.escape(parentItemId)}"]`);
            if (!parentItem) return;
            const cloneParent = parentItem.cloneNode(true);
            cloneParent.dataset.v2Pinned = '1';
            const chev = cloneParent.querySelector('.v2-nav-chev');
            if (chev) chev.classList.remove('v2-is-open');
            host.appendChild(cloneParent);

            groupChildren = document.createElement('div');
            groupChildren.className = 'v2-nav-children v2-is-collapsed';
            groupChildren.dataset.childrenFor = parentItemId;
            groupChildren.dataset.v2Pinned = '1';
            host.appendChild(groupChildren);
            childGroupContainers.set(parentItemId, groupChildren);
          }

          const cloneChild = child.cloneNode(true);
          cloneChild.dataset.v2Pinned = '1';
          groupChildren.appendChild(cloneChild);
          any = true;
        }
      });
      // URL-driven expansion for child-pinned dropdowns: open the
      // container only when one of the pinned children inside it
      // matches the current page; otherwise leave it collapsed.
      childGroupContainers.forEach((kids) => {
        const hasActiveChild = !!kids.querySelector('.v2-nav-child.v2-is-on');
        kids.classList.toggle('v2-is-collapsed', !hasActiveChild);
        const cloneParent = kids.previousElementSibling;
        if (cloneParent) {
          const chev = cloneParent.querySelector('.v2-nav-chev');
          if (chev) chev.classList.toggle('v2-is-open', hasActiveChild);
        }
      });
      pinGroup.style.display = any ? '' : 'none';
    });

  }
  function prepare(root, pins) {
    if (!root) return;
    try {
      root.classList.toggle('v2-collapsed', JSON.parse(localStorage.getItem('6v_v2_collapsed') || 'false') === true);
      root.classList.toggle('v2-theme-dark', JSON.parse(localStorage.getItem('6v_v2_theme') || '"light"') === 'dark');
    } catch (error) { /* Invalid or unavailable browser storage keeps the server defaults. */ }
    decorateNavigationIcons(root);
    injectChildPinButtons(root);
    applyPins(root, pins);
    root.dataset.v2PresentationReady = 'true';
  }
  window.AdminSidebarPresentation = {prepare, decorateNavigationIcons, injectChildPinButtons, applyPins};
})(window);
