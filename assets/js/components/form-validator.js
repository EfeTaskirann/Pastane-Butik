/**
 * Form Validator Component
 *
 * Vanilla JS, bagimliliksiz, CSP uyumlu (inline handler yok).
 *
 * Kullanim — HTML:
 *   <input type="text" name="isim" data-validate="required|min:3|max:60">
 *   <input type="email" name="email" data-validate="required|email">
 *   <input type="password" name="sifre_tekrar" data-validate="required|match:sifre">
 *
 * Form iceriginde data-validate attribute'u tasiyan input'lar otomatik bulunur.
 * Kurallar:
 *   required            — bos olamaz
 *   email               — gecerli email
 *   min:N               — minimum N karakter
 *   max:N               — maksimum N karakter
 *   numeric             — sadece rakam
 *   phone               — Turkiye telefon formatı (05XX XXX XX XX / 5XX...)
 *   match:fieldName     — belirtilen input ile ayni deger (sifre onay)
 *
 * Form submit'te full validation yapilir; hatali alan varsa submit engellenir,
 * ilk hatali input'a focus verilir, Toast.error cagrilir.
 *
 * Realtime: blur + throttled keyup (200ms).
 *
 * @module FormValidator
 */
(function (global) {
    'use strict';

    if (global.FormValidator) {
        return;
    }

    // ---------------- Kural Uygulama ----------------

    var PHONE_RE = /^(\+90\s?|0)?5\d{2}[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    var RULES = {
        required: function (value) {
            if (value === null || value === undefined) return 'Bu alan zorunludur';
            if (typeof value === 'string' && value.trim() === '') return 'Bu alan zorunludur';
            return null;
        },
        email: function (value) {
            if (value === '' || value == null) return null; // required ayri kontrol eder
            return EMAIL_RE.test(String(value).trim()) ? null : 'Geçerli bir e-posta adresi girin';
        },
        min: function (value, param) {
            if (value === '' || value == null) return null;
            var n = parseInt(param, 10);
            if (isNaN(n)) return null;
            return String(value).length >= n ? null : 'En az ' + n + ' karakter olmalı';
        },
        max: function (value, param) {
            if (value === '' || value == null) return null;
            var n = parseInt(param, 10);
            if (isNaN(n)) return null;
            return String(value).length <= n ? null : 'En fazla ' + n + ' karakter olabilir';
        },
        numeric: function (value) {
            if (value === '' || value == null) return null;
            return /^[0-9]+$/.test(String(value)) ? null : 'Sadece rakam girin';
        },
        phone: function (value) {
            if (value === '' || value == null) return null;
            return PHONE_RE.test(String(value).trim()) ? null : 'Geçerli bir telefon numarası girin (ör: 05XX XXX XX XX)';
        },
        match: function (value, param, input) {
            if (!param || !input || !input.form) return null;
            var other = input.form.elements[param];
            if (!other) return null;
            return String(value) === String(other.value) ? null : 'Değerler eşleşmiyor';
        }
    };

    // ---------------- Yardimci fonksiyonlar ----------------

    function parseRules(ruleStr) {
        if (!ruleStr) return [];
        return ruleStr.split('|').map(function (part) {
            var tokens = part.split(':');
            return {
                name:  tokens[0].trim(),
                param: tokens.length > 1 ? tokens.slice(1).join(':').trim() : null
            };
        }).filter(function (r) { return r.name; });
    }

    /**
     * Bir input'u validate et.
     * @returns {string|null} Hata mesaji veya null (gecerli)
     */
    function validateInput(input) {
        if (!input || !input.getAttribute) return null;
        var rulesAttr = input.getAttribute('data-validate');
        if (!rulesAttr) return null;

        var value = input.value;
        var rules = parseRules(rulesAttr);

        for (var i = 0; i < rules.length; i++) {
            var fn = RULES[rules[i].name];
            if (typeof fn !== 'function') continue;
            var err = fn(value, rules[i].param, input);
            if (err) {
                return err;
            }
        }
        return null;
    }

    function getOrCreateErrorEl(input) {
        // Input'un parent'inda data-field-error="<name>" tasiyan varsa onu kullan
        var errEl = null;
        if (input.id) {
            errEl = document.querySelector('[data-field-error-for="' + input.id + '"]');
        }
        if (errEl) return errEl;

        // Input'un hemen ardinda field-error var mi
        var next = input.nextElementSibling;
        if (next && next.classList && next.classList.contains('field-error')) {
            return next;
        }

        errEl = document.createElement('small');
        errEl.className = 'field-error';
        errEl.setAttribute('role', 'alert');
        errEl.setAttribute('aria-live', 'polite');
        if (input.id) {
            errEl.setAttribute('data-field-error-for', input.id);
        }

        // Parent'a ekle
        if (input.parentNode) {
            input.parentNode.insertBefore(errEl, input.nextSibling);
        }
        return errEl;
    }

    function setError(input, message) {
        var errEl = getOrCreateErrorEl(input);
        if (message) {
            errEl.textContent = message;
            errEl.classList.add('is-visible');
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            // aria-describedby baglamasi — id yoksa üret
            var errId = errEl.id || ('err-' + (input.id || Math.random().toString(36).slice(2, 9)));
            errEl.id = errId;
            var existing = input.getAttribute('aria-describedby') || '';
            if ((' ' + existing + ' ').indexOf(' ' + errId + ' ') === -1) {
                input.setAttribute('aria-describedby', (existing + ' ' + errId).trim());
            }
        } else {
            errEl.textContent = '';
            errEl.classList.remove('is-visible');
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            // aria-describedby'den error id'yi temizle (diğer referanslar kalsın)
            if (errEl.id) {
                var describedBy = input.getAttribute('aria-describedby') || '';
                if (describedBy) {
                    var cleaned = describedBy.split(/\s+/).filter(function (id) {
                        return id && id !== errEl.id;
                    }).join(' ');
                    if (cleaned) {
                        input.setAttribute('aria-describedby', cleaned);
                    } else {
                        input.removeAttribute('aria-describedby');
                    }
                }
            }
        }
    }

    function throttle(fn, wait) {
        var last = 0;
        var timer = null;
        return function () {
            var ctx = this;
            var args = arguments;
            var now = Date.now();
            var remaining = wait - (now - last);
            if (remaining <= 0) {
                if (timer) { clearTimeout(timer); timer = null; }
                last = now;
                fn.apply(ctx, args);
            } else if (!timer) {
                timer = setTimeout(function () {
                    last = Date.now();
                    timer = null;
                    fn.apply(ctx, args);
                }, remaining);
            }
        };
    }

    // ---------------- Form baglama ----------------

    function attach(form) {
        if (!form || form.tagName !== 'FORM') return;
        if (form.dataset.validatorAttached === '1') return;
        form.dataset.validatorAttached = '1';

        var inputs = form.querySelectorAll('[data-validate]');
        if (!inputs.length) return;

        inputs.forEach(function (input) {
            var handler = function () {
                var err = validateInput(input);
                setError(input, err);
            };

            input.addEventListener('blur', handler);
            input.addEventListener('input', throttle(handler, 200));

            // match: kuralı icin eslesilen alanin degisimi de tetiklesin
            var rules = parseRules(input.getAttribute('data-validate') || '');
            rules.forEach(function (r) {
                if (r.name === 'match' && r.param && form.elements[r.param]) {
                    form.elements[r.param].addEventListener('input', throttle(handler, 200));
                }
            });
        });

        form.addEventListener('submit', function (e) {
            var result = validateForm(form);
            if (!result.valid) {
                e.preventDefault();
                e.stopPropagation();
                if (window.Toast) {
                    window.Toast.error('Lütfen formdaki hataları düzeltin');
                }
                if (result.firstInvalid) {
                    try {
                        // Önce viewport'a kaydır (header/sticky element engelliyor olabilir), sonra focus
                        if (typeof result.firstInvalid.scrollIntoView === 'function') {
                            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                            result.firstInvalid.scrollIntoView({
                                behavior: reduceMotion ? 'auto' : 'smooth',
                                block: 'center'
                            });
                        }
                        result.firstInvalid.focus({ preventScroll: true });
                    } catch (_) {
                        try { result.firstInvalid.focus(); } catch (__) {}
                    }
                }
            }
        });
    }

    /**
     * Tum form input'larini validate et, ilk hatali elemanla birlikte sonuc don.
     */
    function validateForm(form) {
        if (!form) return { valid: true, firstInvalid: null };
        var inputs = form.querySelectorAll('[data-validate]');
        var firstInvalid = null;
        var valid = true;

        inputs.forEach(function (input) {
            var err = validateInput(input);
            setError(input, err);
            if (err) {
                valid = false;
                if (!firstInvalid) firstInvalid = input;
            }
        });

        return { valid: valid, firstInvalid: firstInvalid };
    }

    function attachAll(root) {
        root = root || document;
        var forms = root.querySelectorAll('form');
        forms.forEach(function (f) {
            // form icinde data-validate varsa bagla
            if (f.querySelector('[data-validate]')) {
                attach(f);
            }
        });
    }

    // ---------------- Public API ----------------

    var FormValidator = {
        attach: attach,
        attachAll: attachAll,
        validateInput: validateInput,
        validateForm: validateForm,
        addRule: function (name, fn) {
            if (typeof name === 'string' && typeof fn === 'function') {
                RULES[name] = fn;
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { attachAll(); });
    } else {
        attachAll();
    }

    global.FormValidator = FormValidator;
})(typeof window !== 'undefined' ? window : this);
