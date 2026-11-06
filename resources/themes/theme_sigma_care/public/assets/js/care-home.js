(() => {
    'use strict';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    document.querySelectorAll('[data-care-product-row]').forEach(row => {
        const rail = row.querySelector('.care-product-rail');
        const prev = row.querySelector('[data-care-row-prev]');
        const next = row.querySelector('[data-care-row-next]');
        const pause = row.querySelector('[data-care-row-pause]');
        if (!rail || !prev || !next || rail.children.length < 2) return;
        const track = document.createElement('div');
        track.className = 'care-product-track';
        while (rail.firstChild) track.appendChild(rail.firstChild);
        rail.appendChild(track);
        rail.classList.add('is-continuous');
        const rtl = getComputedStyle(rail).direction === 'rtl';
        let position = 0, step = 0, cardWidth = 0, viewportWidth = 0;
        let visible = false, hovering = false, paused = false, lastTime = 0, frame = 0;
        let interactionUntil = 0, manual = null, drag = null, suppressClick = false, centered = null;
        const draw = () => {
            if (!step) return;
            while (position >= step) {track.appendChild(track.firstElementChild); position -= step; if (manual) {manual.from -= step; manual.to -= step;}}
            while (position < 0) {track.prepend(track.lastElementChild); position += step; if (manual) {manual.from += step; manual.to += step;}}
            track.style.transform = `translate3d(${(rtl ? 1 : -1) * position}px,0,0)`;
            const index = Math.max(0, Math.min(track.children.length - 1, Math.round((viewportWidth / 2 + position - cardWidth / 2) / step)));
            const card = track.children[index];
            if (centered !== card) {
                centered?.classList.remove('is-centered');
                card.classList.add('is-centered');
                centered = card;
            }
        };
        const measure = () => {
            const fraction = step ? position / step : 0;
            cardWidth = track.firstElementChild.getBoundingClientRect().width;
            step = cardWidth + parseFloat(getComputedStyle(track).columnGap);
            viewportWidth = rail.clientWidth;
            position = fraction * step;
            manual = null;
            const fits = track.children.length * step - (step - cardWidth) <= viewportWidth + 2;
            rail.classList.toggle('is-static', fits);
            prev.hidden = next.hidden = fits;
            if (pause) pause.hidden = fits || reduceMotion.matches;
            draw();
        };
        const tick = time => {
            frame = 0;
            const elapsed = lastTime ? Math.min(time - lastTime, 50) : 0;
            lastTime = time;
            if (manual) {
                const progress = Math.min(1, (time - manual.start) / 250);
                position = manual.from + (manual.to - manual.from) * (1 - Math.pow(1 - progress, 3));
                if (progress === 1) manual = null;
                draw();
            } else if (!paused && !hovering && !drag && !reduceMotion.matches && !row.contains(document.activeElement) && Date.now() >= interactionUntil && !rail.classList.contains('is-static')) {
                position += elapsed * 0.022;
                draw();
            }
            if (visible && !document.hidden) frame = requestAnimationFrame(tick);
        };
        const wake = () => {if (!frame && visible && !document.hidden) {lastTime = 0; frame = requestAnimationFrame(tick);}};
        const move = (direction, keyboard) => {
            interactionUntil = Date.now() + 1200;
            if (keyboard || reduceMotion.matches) {manual = null; position += direction * step; draw();}
            else {manual = {from: position, to: position + direction * step, start: performance.now()}; wake();}
        };
        prev.addEventListener('click', event => move(-1, event.detail === 0));
        next.addEventListener('click', event => move(1, event.detail === 0));
        rail.addEventListener('keydown', event => {
            if (event.target !== rail || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault(); move((event.key === 'ArrowLeft') === rtl ? 1 : -1, true);
        });
        rail.addEventListener('pointerenter', event => {if (event.pointerType === 'mouse') {hovering = true; manual = null;}});
        rail.addEventListener('pointerleave', () => {hovering = false; wake();});
        rail.addEventListener('pointerdown', event => {
            interactionUntil = Date.now() + 1200;
            if (event.pointerType !== 'mouse') drag = {id: event.pointerId, x: event.clientX, moved: false};
        });
        rail.addEventListener('pointermove', event => {
            if (!drag || drag.id !== event.pointerId) return;
            const delta = event.clientX - drag.x;
            if (!drag.moved && Math.abs(delta) < 8) return;
            if (!drag.moved) rail.setPointerCapture(event.pointerId);
            drag.moved = true; suppressClick = true; manual = null;
            position += delta * (rtl ? 1 : -1); drag.x = event.clientX; draw();
        });
        const endDrag = () => {drag = null; interactionUntil = Date.now() + 1200; wake();};
        rail.addEventListener('pointerup', endDrag);
        rail.addEventListener('pointercancel', endDrag);
        rail.addEventListener('click', event => {if (suppressClick) {event.preventDefault(); event.stopPropagation(); suppressClick = false;}}, true);
        if (pause) pause.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? pause.dataset.playLabel : pause.dataset.pauseLabel);
            pause.querySelector('i').className = paused ? 'bi bi-play' : 'bi bi-pause';
            wake();
        });
        new ResizeObserver(measure).observe(rail);
        new IntersectionObserver(entries => {visible = entries[0].isIntersecting; if (!visible) {cancelAnimationFrame(frame); frame = 0;} else wake();}, {threshold: 0}).observe(rail);
        document.addEventListener('visibilitychange', () => {if (document.hidden) {cancelAnimationFrame(frame); frame = 0;} else wake();});
        reduceMotion.addEventListener('change', () => {measure(); wake();});
        measure();
    });
    const panel = document.getElementById('careSupportPanel');
    const form = document.getElementById('care-support-form');
    if (!panel || !form) return;
    const arabic = document.documentElement.lang.startsWith('ar') || document.documentElement.dir === 'rtl';
    const submit = form.querySelector('[type="submit"]');
    const status = form.querySelector('.care-support-status');
    const success = panel.querySelector('.care-support-success');
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity() || submit.disabled) return;
        submit.disabled = true;
        submit.textContent = submit.dataset.loadingLabel;
        status.hidden = true;
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    const labels = arabic
                        ? {ticket_subject: 'عنوان المشكلة', ticket_description: 'تفاصيل المشكلة', ticket_type: 'نوع المشكلة', ticket_priority: 'درجة الأهمية'}
                        : {ticket_subject: 'issue subject', ticket_description: 'issue details', ticket_type: 'issue category', ticket_priority: 'priority'};
                    const fields = Object.keys(data.errors).map(field => labels[field]).filter(Boolean);
                    throw new Error(fields.length
                        ? (arabic ? `راجع البيانات التالية ثم أعد الإرسال: ${fields.join('، ')}.` : `Check these fields and try again: ${fields.join(', ')}.`)
                        : (arabic ? 'راجع بيانات المشكلة ثم أعد الإرسال.' : 'Check your issue details and try again.'));
                }
                throw new Error([401,403,419].includes(response.status) ? (arabic ? 'انتهت الجلسة. حدّث الصفحة وسجّل الدخول ثم حاول مرة أخرى.' : 'Your session expired. Refresh, sign in and try again.') : (arabic ? 'تعذّر إرسال مشكلتك. حاول مرة أخرى.' : 'Unable to send your issue. Please try again.'));
            }
            if (!data.url || !data.ticket_id) throw new Error(arabic ? 'تعذّر تأكيد الإرسال. راجع رسائلك في حسابك.' : 'Unable to confirm submission. Check the messages in your account.');
            panel.querySelector('[data-care-ticket-link]').href = data.url;
            form.hidden = true;
            success.hidden = false;
            success.focus();
            form.reset();
            window.location.assign(data.url);
        } catch (error) {
            status.textContent = error.message || (arabic ? 'تحقق من اتصالك وحاول مرة أخرى.' : 'Check your connection and try again.');
            status.hidden = false;
        } finally {
            form.removeAttribute('aria-busy');
            submit.disabled = false;
            submit.textContent = submit.dataset.idleLabel;
        }
    });
    panel.querySelector('[data-care-new-ticket]').addEventListener('click', () => {
        success.hidden = true;
        form.hidden = false;
        form.querySelector('[name="ticket_subject"]').focus();
    });
})();
