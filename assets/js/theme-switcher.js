/**
 * Theme Switcher — Dark/Light Mode
 *
 * Ozellikler:
 * - Manuel toggle: html[data-theme="dark|light"] attribute
 * - localStorage persistence ("pastane_theme")
 * - System preference (prefers-color-scheme) fallback
 * - matchMedia listener: sistem temasi degistiginde manuel tercih yoksa auto-follow
 * - aria-pressed state guncellenir
 * - CSP uyumlu — inline handler yok, data-action ile baglanir
 *
 * Kullanim:
 * <button class="theme-toggle" data-action="toggle-theme" aria-label="Tema degistir" aria-pressed="false">...</button>
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'pastane_theme';
    var VALID_THEMES = ['light', 'dark'];

    // ---------- Storage helpers ----------

    function getStoredTheme() {
        try {
            var val = localStorage.getItem(STORAGE_KEY);
            return VALID_THEMES.indexOf(val) !== -1 ? val : null;
        } catch (e) {
            return null;
        }
    }

    function setStoredTheme(theme) {
        try {
            if (theme === null) {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, theme);
            }
        } catch (e) {
            // localStorage dolu, private mode, vb. — sessiz fail
        }
    }

    // ---------- System preference ----------

    function getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    // ---------- Theme application ----------

    function applyTheme(theme) {
        var html = document.documentElement;
        if (theme === 'dark' || theme === 'light') {
            html.setAttribute('data-theme', theme);
        } else {
            html.removeAttribute('data-theme');
        }

        // Meta theme-color (mobile browser status bar)
        var metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.setAttribute('content', theme === 'dark' ? '#0F0E14' : '#FDF8F5');
        }

        updateToggleState(theme);
    }

    function updateToggleState(theme) {
        var toggles = document.querySelectorAll('[data-action="toggle-theme"]');
        var effectiveTheme = theme || getSystemTheme();
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].setAttribute('aria-pressed', effectiveTheme === 'dark' ? 'true' : 'false');
            var label = effectiveTheme === 'dark' ? 'Açık temaya geç' : 'Koyu temaya geç';
            toggles[i].setAttribute('aria-label', label);
            toggles[i].setAttribute('title', label);
        }
    }

    // ---------- Current theme ----------

    function getCurrentTheme() {
        var stored = getStoredTheme();
        if (stored) {
            return stored;
        }
        return getSystemTheme();
    }

    function toggleTheme() {
        var current = getCurrentTheme();
        var next = current === 'dark' ? 'light' : 'dark';
        setStoredTheme(next);
        applyTheme(next);
    }

    // ---------- Init ----------

    function init() {
        // Apply early theme (flash prevention yapildiysa onceden set edilmis olabilir)
        var current = getCurrentTheme();
        applyTheme(current);

        // System preference listener
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function (e) {
                // Eger kullanicinin manuel tercihi YOKSA (localStorage bos), sistem degisimini takip et
                if (!getStoredTheme()) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            };
            // Modern + legacy syntax
            if (mq.addEventListener) {
                mq.addEventListener('change', onChange);
            } else if (mq.addListener) {
                mq.addListener(onChange);
            }
        }

        // Delegation: data-action="toggle-theme"
        document.addEventListener('click', function (e) {
            var target = e.target;
            while (target && target !== document) {
                if (target.getAttribute && target.getAttribute('data-action') === 'toggle-theme') {
                    e.preventDefault();
                    toggleTheme();
                    return;
                }
                target = target.parentNode;
            }
        });

        // Keyboard support — Enter/Space zaten button native, ama ekstra koruma
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var target = e.target;
            if (target && target.getAttribute && target.getAttribute('data-action') === 'toggle-theme') {
                e.preventDefault();
                toggleTheme();
            }
        });
    }

    // Public API
    window.PastaneTheme = {
        get: getCurrentTheme,
        set: function (theme) {
            if (VALID_THEMES.indexOf(theme) !== -1) {
                setStoredTheme(theme);
                applyTheme(theme);
            }
        },
        reset: function () {
            setStoredTheme(null);
            applyTheme(getSystemTheme());
        },
        toggle: toggleTheme
    };

    // DOM hazir olunca init et
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
