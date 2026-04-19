/**
 * Toast Notification Component
 *
 * Vanilla JS, bağımlılıksız, CSP uyumlu.
 * Kullanim:
 *   Toast.success('Kayıt başarılı');
 *   Toast.error('Bir hata oluştu');
 *   Toast.info('Bilgilendirme mesajı');
 *   Toast.warning('Dikkat!');
 *   Toast.show('Ozel mesaj', { type: 'info', duration: 6000 });
 *
 * Ozellikler:
 *  - Auto-dismiss 4 sn (varsayilan), hover ile duraklar (animasyon)
 *  - Sag alt kosede stack, maksimum 4 aktif — ustten kaybolur
 *  - ARIA: role="status", aria-live="polite"
 *  - Reduced-motion destegi
 */
(function (global) {
    'use strict';

    if (global.Toast) {
        return;
    }

    var CONTAINER_ID = 'toast-root';
    var MAX_VISIBLE = 4;
    var DEFAULT_DURATION = 4000;

    var ICONS = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    var CLOSE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    function getContainer() {
        var container = document.getElementById(CONTAINER_ID);
        if (container) {
            return container;
        }
        container = document.createElement('div');
        container.id = CONTAINER_ID;
        container.className = 'toast-container';
        container.setAttribute('role', 'region');
        container.setAttribute('aria-label', 'Bildirimler');

        var appendWhenReady = function () {
            if (document.body) {
                document.body.appendChild(container);
            }
        };

        if (document.body) {
            document.body.appendChild(container);
        } else {
            document.addEventListener('DOMContentLoaded', appendWhenReady, { once: true });
        }
        return container;
    }

    function trimExcess(container) {
        var toasts = container.querySelectorAll('.toast');
        if (toasts.length <= MAX_VISIBLE) {
            return;
        }
        // En eski (DOM'da ustteki) toast'ları kaldir (container reverse stack oldugundan son eklenenin karşıtı)
        var removeCount = toasts.length - MAX_VISIBLE;
        for (var i = toasts.length - 1; i >= 0 && removeCount > 0; i--) {
            dismiss(toasts[i]);
            removeCount--;
        }
    }

    function dismiss(toast) {
        if (!toast || toast.dataset.dismissed === '1') {
            return;
        }
        toast.dataset.dismissed = '1';
        // Auto-dismiss timer varsa temizle (trim/escape/manual dismiss tüm yolları kapsar)
        if (toast._toastTimerId) {
            clearTimeout(toast._toastTimerId);
            toast._toastTimerId = null;
        }
        toast.classList.add('is-leaving');
        toast.classList.remove('is-visible');

        var removeNode = function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        };

        // prefers-reduced-motion → transitionend gelmeyebilir
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) {
            removeNode();
            return;
        }

        var finished = false;
        var onEnd = function () {
            if (finished) return;
            finished = true;
            toast.removeEventListener('transitionend', onEnd);
            removeNode();
        };
        toast.addEventListener('transitionend', onEnd);
        // Fallback — transitionend tetiklenmezse
        setTimeout(onEnd, 400);
    }

    function show(message, options) {
        options = options || {};
        var type = options.type || 'info';
        if (!ICONS[type]) {
            type = 'info';
        }
        var duration = typeof options.duration === 'number' ? options.duration : DEFAULT_DURATION;

        var container = getContainer();
        if (!container) {
            // DOM hazır değilse
            document.addEventListener('DOMContentLoaded', function () {
                show(message, options);
            }, { once: true });
            return null;
        }

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');

        var iconEl = document.createElement('span');
        iconEl.className = 'toast-icon';
        iconEl.innerHTML = ICONS[type];

        var messageEl = document.createElement('span');
        messageEl.className = 'toast-message';
        messageEl.textContent = String(message == null ? '' : message);

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'toast-close';
        closeBtn.setAttribute('aria-label', 'Kapat');
        closeBtn.setAttribute('title', 'Kapat');
        closeBtn.innerHTML = CLOSE_ICON;

        toast.appendChild(iconEl);
        toast.appendChild(messageEl);
        toast.appendChild(closeBtn);

        var timerId = null;
        var progressBar = null;

        if (duration > 0) {
            progressBar = document.createElement('div');
            progressBar.className = 'toast-progress';
            progressBar.style.animationDuration = duration + 'ms';
            toast.appendChild(progressBar);
        }

        // Stack'in en altina (gorsel olarak en uste) ekle
        container.insertBefore(toast, container.firstChild);

        // Reflow + transition
        // eslint-disable-next-line no-unused-expressions
        toast.offsetHeight;
        toast.classList.add('is-visible');

        trimExcess(container);

        var clearTimer = function () {
            if (timerId) {
                clearTimeout(timerId);
                timerId = null;
                toast._toastTimerId = null;
            }
        };
        var scheduleDismiss = function (remaining) {
            clearTimer();
            if (remaining > 0) {
                timerId = setTimeout(function () { dismiss(toast); }, remaining);
                toast._toastTimerId = timerId;
            }
        };

        if (duration > 0) {
            var startTime = Date.now();
            var remaining = duration;

            toast.addEventListener('mouseenter', function () {
                clearTimer();
                // Kalan süreyi animasyondan oku (basit yaklaşım: geçen süreyi çıkar)
                remaining = duration - (Date.now() - startTime);
                if (remaining < 0) remaining = 0;
                toast.classList.add('is-paused');
            });

            toast.addEventListener('mouseleave', function () {
                toast.classList.remove('is-paused');
                startTime = Date.now();
                scheduleDismiss(remaining);
            });

            toast.addEventListener('focusin', function () {
                clearTimer();
                remaining = duration - (Date.now() - startTime);
                if (remaining < 0) remaining = 0;
                toast.classList.add('is-paused');
            });

            toast.addEventListener('focusout', function () {
                toast.classList.remove('is-paused');
                startTime = Date.now();
                scheduleDismiss(remaining);
            });

            scheduleDismiss(remaining);
        }

        closeBtn.addEventListener('click', function () {
            clearTimer();
            dismiss(toast);
        });

        return {
            dismiss: function () {
                clearTimer();
                dismiss(toast);
            },
            element: toast
        };
    }

    var Toast = {
        show: show,
        success: function (msg, opts) {
            opts = opts || {};
            opts.type = 'success';
            return show(msg, opts);
        },
        error: function (msg, opts) {
            opts = opts || {};
            opts.type = 'error';
            return show(msg, opts);
        },
        warning: function (msg, opts) {
            opts = opts || {};
            opts.type = 'warning';
            return show(msg, opts);
        },
        info: function (msg, opts) {
            opts = opts || {};
            opts.type = 'info';
            return show(msg, opts);
        },
        dismissAll: function () {
            var container = document.getElementById(CONTAINER_ID);
            if (!container) return;
            var toasts = container.querySelectorAll('.toast');
            for (var i = 0; i < toasts.length; i++) {
                dismiss(toasts[i]);
            }
        }
    };

    // Global Escape key: en üstteki (son eklenen) toast'ı kapat
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' && e.key !== 'Esc') return;
        var container = document.getElementById(CONTAINER_ID);
        if (!container) return;
        // Görsel olarak en üstte olan = DOM'da ilk child (column-reverse stack)
        var topToast = container.querySelector('.toast:not(.is-leaving)');
        if (topToast) {
            dismiss(topToast);
        }
    });

    global.Toast = Toast;
})(typeof window !== 'undefined' ? window : this);
