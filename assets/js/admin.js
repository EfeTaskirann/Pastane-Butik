/**
 * Admin Panel JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // ========== SIDEBAR TOGGLE (Mobile) ==========
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

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
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 350px;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
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
});
