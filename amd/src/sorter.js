/* eslint-disable */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    // ---------------------------
    // Debug helpers
    // ---------------------------
    const DEBUG = false;
    const log  = (...a) => { if (DEBUG && window.console) console.log('[slideshow]', ...a); };
    const warn = (...a) => { if (DEBUG && window.console) console.warn('[slideshow]', ...a); };

    // ---------------------------
    // DOM utils
    // ---------------------------
    const by = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const getKey = (li) => li.getAttribute('data-key') || li.getAttribute('data-filename');

    // ---------------------------
    // Order persistence + AJAX
    // ---------------------------
    function persist(list) {
        const inputId = list.getAttribute('data-input') || 'id_orderjson';
        let input = document.getElementById(inputId);
        if (!input) {
            const name = inputId.startsWith('id_') ? inputId.slice(3) : inputId;
            const form = list.closest('form');
            input = (form && form.querySelector(`[name="${name}"]`)) ||
                    document.querySelector(`[name="${name}"]`);
        }
        if (!input) {
            warn('hidden field NOT FOUND', {inputId});
            return;
        }

        let items = by('li[data-key]', list);
        if (!items.length) items = by('li[data-filename]', list);
        const seq = items.map(getKey).filter(Boolean);

        input.value = JSON.stringify(seq);

        log('persist()', {
            inputId,
            found: !!input,
            seqLen: seq.length,
            preview: seq.slice(0, 5)
        });
    }

    function getOrder(list) {
        let items = by('li[data-key]', list);
        if (!items.length) items = by('li[data-filename]', list);
        return items.map(getKey).filter(Boolean);
    }

    function saveOrder(cmid, order) {
        if (!cmid) { warn('saveOrder skipped: no cmid'); return Promise.resolve(); }
        if (!order || !order.length) { warn('saveOrder skipped: empty order'); return Promise.resolve(); }

        log('AJAX > mod_slideshow_reorder', {cmid, orderLen: order.length, sample: order.slice(0,5)});
        return Ajax.call([{
            methodname: 'mod_slideshow_reorder',
            args: { cmid: Number(cmid), order: order } // array, not string
        }])[0]
        .then((resp) => {
            log('AJAX OK mod_slideshow_reorder', resp);
            return resp;
        })
        .catch((e) => {
            warn('AJAX x mod_slideshow_reorder', e);
            Notification.exception(e);
            throw e;
        });
    }

    function move(li, where) {
        const ul = li?.parentElement;
        if (!ul) return;

        if (where === 'up' && li.previousElementSibling) {
            ul.insertBefore(li, li.previousElementSibling);
        } else if (where === 'down' && li.nextElementSibling) {
            ul.insertBefore(li.nextElementSibling, li);
        } else if (where === 'top') {
            ul.insertBefore(li, ul.firstElementChild);
        } else if (where === 'bottom') {
            ul.appendChild(li);
        }

        persist(ul);

        const cmid = ul.getAttribute('data-cmid');
        const order = getOrder(ul);
        log('move()', {where, cmid, orderLen: order.length});
        saveOrder(cmid, order);
    }

    function setupButtons(list) {
        list.addEventListener('click', (e) => {
            const btn = e.target.closest('button.ss-move');
            if (!btn) return;
            e.preventDefault();
            const li = btn.closest('li[data-key], li[data-filename]');
            if (!li) return;
            if (btn.classList.contains('ss-up'))         move(li, 'up');
            else if (btn.classList.contains('ss-down'))  move(li, 'down');
            else if (btn.classList.contains('ss-top'))   move(li, 'top');
            else if (btn.classList.contains('ss-bottom'))move(li, 'bottom');
        });
        log('setupButtons() OK');
    }

    // ---------------------------
    // Auto-scroll while dragging
    // ---------------------------
    function makeAutoScroller(scrollEl) {
        const EDGE = 80;   // px from top/bottom to trigger
        const MAX  = 24;   // max px per frame
        let active = false, raf = 0, lastY = null;

        function onDragOver(e) {
            // keep receiving dragover; record pointer
            e.preventDefault();
            lastY = e.clientY;
        }

        function step() {
            if (!active || lastY == null) return;

            // recompute edges every frame based on the element's viewport box
            const r = (scrollEl === document.scrollingElement || scrollEl === document.documentElement)
                ? { top: 0, bottom: window.innerHeight }
                : scrollEl.getBoundingClientRect();

            const topEdge = r.top + EDGE;
            const botEdge = r.bottom - EDGE;

            let delta = 0;
            if (lastY < topEdge) {
                delta = -((topEdge - lastY) / EDGE) * MAX;   // up
            } else if (lastY > botEdge) {
                delta = ((lastY - botEdge) / EDGE) * MAX;    // down
            }

            if (delta) {
                // Guarantee at least 1px/frame in the correct direction
                const stepPx = delta > 0
                    ? Math.max(1, Math.round(delta))
                    : Math.min(-1, Math.round(delta));

                scrollEl.scrollTop += stepPx;
            }

            raf = requestAnimationFrame(step);
        }

        return {
            start() {
                if (active) return;
                active = true;
                document.addEventListener('dragover', onDragOver, { passive: false });
                // Some browsers fire 'drag' but not 'dragover' outside drop zones; this helps:
                document.addEventListener('drag', onDragOver, { passive: false });
                raf = requestAnimationFrame(step);
            },
            stop() {
                if (!active) return;
                active = false;
                lastY = null;
                document.removeEventListener('dragover', onDragOver);
                document.removeEventListener('drag', onDragOver);
                if (raf) cancelAnimationFrame(raf);
                raf = 0;
            }
        };
    }




    function getScrollTarget() {
        // Try common Moodle containers, then fall back to the true page scroller.
        const candidates = [
            document.querySelector('#region-main .card-body'),
            document.querySelector('#region-main'),
            document.getElementById('page'),                   // Moodle root scroll container
            document.scrollingElement || document.documentElement
        ].filter(Boolean);

        for (const el of candidates) {
            const canScroll = el.scrollHeight > el.clientHeight;
            if (canScroll) {
                log('auto-scroll target =', el.id || el.className || el.tagName);
                return el;
            }
        }
        log('auto-scroll target = window (fallback)');
        return window;
    }


    // ---------------------------
    // Drag & drop with smoothness
    // ---------------------------
    function setupDrag(list) {
        const scroller = makeAutoScroller(getScrollTarget(list));

        let lis = by('li[data-key]', list);
        if (!lis.length) lis = by('li[data-filename]', list);

        // For small FLIP animation when positions change.
    const measurePositions = () => {
        const map = new Map();
        const items = by('li[data-key], li[data-filename]', list);
        items.forEach(el => map.set(el, el.getBoundingClientRect()));
        return map;
    };

    const animateReorder = (beforeRects) => {
        const items = by('li[data-key], li[data-filename]', list);
        items.forEach(el => {
            if (el.classList.contains('dragging')) return; // don't animate the drag handle item
            const prev = beforeRects.get(el);
            if (!prev) return;
            const next = el.getBoundingClientRect();
            const dx = prev.left - next.left;
            const dy = prev.top - next.top;
            if (!dx && !dy) return;

            el.style.willChange = 'transform';
            el.style.transform = `translate(${dx}px, ${dy}px)`;
            el.style.transition = 'transform 0s';
            void el.getBoundingClientRect(); // reflow
            el.style.transition = 'transform 80ms ease-out';
            el.style.transform = 'translate(0, 0)';
            el.addEventListener('transitionend', () => {
                el.style.transition = '';
                el.style.transform = '';
                el.style.willChange = '';
            }, { once: true });
        });
    };



        // add draggable behaviour to each LI
        lis.forEach(li => {
            li.setAttribute('draggable', 'true');
            li.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', getKey(li) || '');
                li.classList.add('dragging');
                scroller.start();
            });
            li.addEventListener('dragend', () => {
                li.classList.remove('dragging');
                scroller.stop();
            });
        });

        // reorder while dragging
        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = list.querySelector('li.dragging');
            if (!dragging) return;

            const beforeRects = measurePositions();

            const after = getDragAfterElement(list, e.clientY);
            if (after == null) list.appendChild(dragging);
            else list.insertBefore(dragging, after);

            animateReorder(beforeRects);
        });

        list.addEventListener('drop', (e) => {
            e.preventDefault();
            scroller.stop();
            persist(list);
            const cmid = list.getAttribute('data-cmid');
            const order = getOrder(list);
            log('drop', {cmid, orderLen: order.length});
            saveOrder(cmid, order);
        });

        function getDragAfterElement(listEl, y) {
            let items = by('li[data-key]:not(.dragging)', listEl);
            if (!items.length) items = by('li[data-filename]:not(.dragging)', listEl);
            let closest = null;
            let closestOffset = Number.NEGATIVE_INFINITY;
            items.forEach(item => {
                const box = item.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closestOffset) {
                    closestOffset = offset;
                    closest = item;
                }
            });
            return closest;
        }

        log('setupDrag() OK (autoscroll + FLIP)');
    }

    // ---------------------------
    // Init per list
    // ---------------------------
    function initOne(list) {
        const cmid = list.getAttribute('data-cmid');
        const inputAttr = list.getAttribute('data-input') || 'id_orderjson';
        log('initOne()', { cmid, inputAttr, items: by('li', list).length });

        setupButtons(list);
        setupDrag(list);

        // Seed once so the hidden is never empty.
        persist(list);

        const form = list.closest('form');

        // Helper: resolve hidden input by id first, then by name (scoped to the form if possible).
        const resolveHidden = () => {
            let el = document.getElementById(inputAttr);
            if (!el) {
                const name = inputAttr.startsWith('id_') ? inputAttr.slice(3) : inputAttr;
                el = (form && form.querySelector(`[name="${name}"]`)) ||
                     document.querySelector(`[name="${name}"]`);
            }
            return el;
        };

        // Ensure the hidden exists/has id+name so it POSTS.
        const ensureHidden = () => {
            let el = resolveHidden();
            if (!el) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'orderjson';
                hidden.id = 'id_orderjson';
                hidden.value = '[]';
                (form || document.body).appendChild(hidden);
                el = hidden;
                warn('orderjson hidden was missing; created defensively');
            }
            if (!el.name) el.name = 'orderjson';
            if (!el.id) el.id = 'id_orderjson';
            return el;
        };

        if (form) {
            form.addEventListener('submit', () => {
                ensureHidden();
                persist(list);
                try {
                    const el = resolveHidden();
                    log('[slideshow] submit  ->  orderjson len', el ? (el.value || '').length : 0);
                } catch (e) {}
            });

            form.addEventListener('click', (e) => {
                const btn = e.target.closest('button, input[type="submit"]');
                if (!btn) return;
                setTimeout(() => { ensureHidden(); persist(list); }, 0);
            }, true);

            form.addEventListener('formdata', () => {
                ensureHidden();
                persist(list);
            });
        } else {
            warn('initOne(): no parent form found; submit persistence unavailable');
        }
    }

    // ---------------------------
    // Public init
    // ---------------------------
    return {
        init: function() {
            const boot = () => {
                const lists = by('ul#slideshow-sorter[data-input]');
                if (!lists.length) { setTimeout(boot, 50); return; }
                lists.forEach(initOne);
                log('boot: sorter ready (count=' + lists.length + ')');
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot, { once: true });
            } else {
                boot();
            }
        }
    };
});
