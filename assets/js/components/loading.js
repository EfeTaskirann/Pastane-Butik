/**
 * Loading State Component
 *
 * Vanilla JS, bağımlılıksız, CSP uyumlu.
 *
 * Kullanim:
 *   Loading.show(button);         // butonu disable + spinner
 *   Loading.hide(button);          // orijinal hale dondur
 *   Loading.wrap(button, promiseLike); // otomatik show/hide
 *
 * Form auto-wire:
 *   <form data-loading>   → submit edildiğinde submit butonu otomatik loading state'e geçer.
 *   <form data-loading="selector"> → belirtilen buton selector'u kullanilir.
 */
(function (global) {
    'use strict';

    if (global.Loading) {
        return;
    }

    var STATE_KEY = 'loadingState';
    var ORIGINAL_ATTR = 'data-loading-original-html';
    var DISABLED_ATTR = 'data-loading-was-disabled';

    function isEl(node) {
        return node && node.nodeType === 1;
    }

    // Screen reader duyurusu — offscreen aria-live region
    function announce(message) {
        if (!document.body) return;
        var liveRegion = document.getElementById('loading-live-region');
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'loading-live-region';
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            // Offscreen ama screen reader'a görünür
            liveRegion.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = message || '';
    }

    function show(element) {
        if (!isEl(element)) return;

        // Zaten loading state'te ise tekrar uygulama (idempotent)
        if (element.classList.contains('is-loading')) {
            return;
        }

        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT') {
            element.setAttribute(DISABLED_ATTR, element.disabled ? '1' : '0');
            element.disabled = true;
        } else {
            // non-button elementler icin ARIA ile devre disi birak
            element.setAttribute('aria-disabled', 'true');
        }

        // Orijinali sakla (innerHTML dinamik değişirse restore için).
        // INPUT elementleri innerHTML içermez — atla.
        if (element.tagName !== 'INPUT') {
            element.setAttribute(ORIGINAL_ATTR, element.innerHTML);
        }

        element.classList.add('is-loading');
        element.setAttribute('aria-busy', 'true');
        element.dataset[STATE_KEY] = 'loading';

        announce('Yükleniyor');
    }

    function hide(element) {
        if (!isEl(element)) return;
        if (!element.classList.contains('is-loading')) return;

        element.classList.remove('is-loading');
        element.removeAttribute('aria-busy');

        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT') {
            var wasDisabled = element.getAttribute(DISABLED_ATTR) === '1';
            element.disabled = wasDisabled;
            element.removeAttribute(DISABLED_ATTR);
        } else {
            element.removeAttribute('aria-disabled');
        }

        // Orijinal içeriği restore et (show sırasında innerHTML'i dinamik değiştiren kullanıcı kodu için güvenli)
        if (element.hasAttribute(ORIGINAL_ATTR)) {
            var orig = element.getAttribute(ORIGINAL_ATTR);
            if (element.innerHTML !== orig) {
                element.innerHTML = orig;
            }
            element.removeAttribute(ORIGINAL_ATTR);
        }
        delete element.dataset[STATE_KEY];

        announce('');
    }

    /**
     * Promise/thenable-like tamamlanana kadar loading'de tut.
     */
    function wrap(element, promise) {
        show(element);
        if (!promise || typeof promise.then !== 'function') {
            // Promise degilse hemen hide et
            hide(element);
            return promise;
        }
        var done = function () { hide(element); };
        return promise.then(function (value) {
            done();
            return value;
        }, function (err) {
            done();
            throw err;
        });
    }

    /**
     * Form submit interceptor — <form data-loading> butonunu otomatik loading yapar.
     * Varsayilan olarak sayfa reload olacagi icin hide cagrisina gerek yoktur.
     */
    function attachFormHandlers(root) {
        root = root || document;
        var forms = root.querySelectorAll('form[data-loading]');
        for (var i = 0; i < forms.length; i++) {
            var form = forms[i];
            if (form.dataset.loadingBound === '1') continue;
            form.dataset.loadingBound = '1';

            form.addEventListener('submit', function (e) {
                // Form validasyonu başarısızsa browser submit'i engeller → loading'e girmeyelim
                if (typeof e.currentTarget.checkValidity === 'function' && !e.currentTarget.checkValidity()) {
                    return;
                }

                var selector = e.currentTarget.getAttribute('data-loading');
                var button = null;

                if (selector && selector.trim() !== '') {
                    button = e.currentTarget.querySelector(selector) || document.querySelector(selector);
                } else {
                    button = e.currentTarget.querySelector('button[type="submit"], input[type="submit"]');
                    // Submitter öncelikli
                    if (e.submitter) {
                        button = e.submitter;
                    }
                }

                if (button) {
                    show(button);
                }
            });
        }
    }

    function init() {
        attachFormHandlers(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    // bfcache (back/forward navigation) — loading state'te bırakılmış butonları kurtar
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) return;
        var stuck = document.querySelectorAll('.is-loading');
        for (var i = 0; i < stuck.length; i++) {
            hide(stuck[i]);
        }
    });

    global.Loading = {
        show: show,
        hide: hide,
        wrap: wrap,
        attachFormHandlers: attachFormHandlers
    };
})(typeof window !== 'undefined' ? window : this);
