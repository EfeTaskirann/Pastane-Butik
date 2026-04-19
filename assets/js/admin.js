/**
 * Admin Panel JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // ========== SIDEBAR TOGGLE (Mobile) ==========
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');

    function setSidebarOpen(isOpen) {
        if (!sidebar) return;
        if (isOpen) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            const isOpen = sidebar.classList.contains('open');
            setSidebarOpen(!isOpen);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            setSidebarOpen(false);
            // Fokusu geri tasi (WCAG 2.4.3 Focus Order)
            if (menuToggle) menuToggle.focus();
        });
    }

    // WCAG: Escape ile sidebar kapat (klavye navigasyonu)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            setSidebarOpen(false);
            if (menuToggle) menuToggle.focus();
        }
    });

    // ========== AUTO-HIDE ALERTS ==========
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // ========== CONFIRM DELETE ==========
    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(link => {
        if (!link.hasAttribute('onclick')) {
            link.addEventListener('click', function(e) {
                if (!confirm('Bu öğeyi silmek istediğinize emin misiniz?')) {
                    e.preventDefault();
                }
            });
        }
    });

    // ========== FILE UPLOAD PREVIEW ==========
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const wrapper = this.closest('.file-upload');

            if (file && wrapper) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Mevcut önizlemeyi bul veya oluştur
                    let preview = wrapper.parentElement.querySelector('.file-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'file-preview';
                        wrapper.parentElement.appendChild(preview);
                    }
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);

                // Upload text güncelle
                const text = wrapper.querySelector('p');
                if (text) {
                    text.innerHTML = `<strong>${file.name}</strong><br><small>Değiştirmek için tıklayın</small>`;
                }
            }
        });
    });

    // ========== FORM VALIDATION ==========
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#F56565';
                    field.addEventListener('input', function() {
                        this.style.borderColor = '';
                    }, { once: true });
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotification('Lütfen zorunlu alanları doldurun.', 'error');
            }
        });
    });

    // ========== NOTIFICATION FUNCTION ==========
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
        notification.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        const animIn = prefersReducedMotion.matches ? 'none' : 'slideIn 0.3s ease';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1080;
            animation: ${animIn};
            max-width: 350px;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (!prefersReducedMotion.matches) {
                notification.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => notification.remove(), 300);
            } else {
                notification.remove();
            }
        }, 4000);
    }

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);

    // ========== TABLE ROW HOVER ==========
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.style.cursor = 'default';
    });

    // ========== SLUG AUTO-GENERATE ==========
    const nameInput = document.getElementById('isim');
    if (nameInput) {
        nameInput.addEventListener('blur', function() {
            // Eğer slug alanı varsa ve boşsa, otomatik doldur
            const slugInput = document.getElementById('slug');
            if (slugInput && !slugInput.value) {
                slugInput.value = slugify(this.value);
            }
        });
    }

    function slugify(text) {
        const turkce = {'ş':'s', 'Ş':'s', 'ı':'i', 'İ':'i', 'ğ':'g', 'Ğ':'g', 'ü':'u', 'Ü':'u', 'ö':'o', 'Ö':'o', 'ç':'c', 'Ç':'c'};
        for (let key in turkce) {
            text = text.split(key).join(turkce[key]);
        }
        return text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    // ========== RESPONSIVE TABLE ==========
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });

    // ========== SIDEBAR NAV GROUP (acilir-kapanir alt menu) ==========
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action="toggle-nav-group"]');
        if (!btn) return;
        e.preventDefault();
        var target = btn.getAttribute('data-target');
        if (!target) return;

        var group = btn.closest('.nav-group');
        var items = document.getElementById('nav-group-' + target);
        if (!group || !items) return;

        var isOpen = group.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            items.removeAttribute('hidden');
        } else {
            items.setAttribute('hidden', '');
        }
    });
});
