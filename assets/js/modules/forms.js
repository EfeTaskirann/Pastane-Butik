/**
 * Forms Module
 * Form validation and submission handling
 */

import Swal from 'sweetalert2';

export function initForms() {
  // Initialize all forms with validation
  const forms = document.querySelectorAll('[data-validate]');
  forms.forEach((form) => {
    initFormValidation(form);
  });

  // Initialize AJAX forms
  const ajaxForms = document.querySelectorAll('[data-ajax-form]');
  ajaxForms.forEach((form) => {
    initAjaxForm(form);
  });
}

/**
 * Initialize form validation
 */
function initFormValidation(form) {
  const inputs = form.querySelectorAll('[required], [data-validate]');

  inputs.forEach((input) => {
    // Validate on blur
    input.addEventListener('blur', () => {
      validateInput(input);
    });

    // Clear error on input
    input.addEventListener('input', () => {
      clearError(input);
    });
  });

  // Validate on submit
  form.addEventListener('submit', (e) => {
    let isValid = true;

    inputs.forEach((input) => {
      if (!validateInput(input)) {
        isValid = false;
      }
    });

    if (!isValid) {
      e.preventDefault();

      // Focus first invalid input
      const firstInvalid = form.querySelector('.is-invalid');
      if (firstInvalid) firstInvalid.focus();
    }
  });
}

/**
 * Validate single input
 */
function validateInput(input) {
  const value = input.value.trim();
  const type = input.type;
  const rules = input.dataset.rules?.split('|') || [];
  let isValid = true;
  let errorMessage = '';

  // Required check
  if (input.required && !value) {
    isValid = false;
    errorMessage = 'Bu alan zorunludur.';
  }

  // Type-specific validation
  if (isValid && value) {
    switch (type) {
      case 'email':
        if (!isValidEmail(value)) {
          isValid = false;
          errorMessage = 'Geçerli bir e-posta adresi giriniz.';
        }
        break;

      case 'tel':
        if (!isValidPhone(value)) {
          isValid = false;
          errorMessage = 'Geçerli bir telefon numarası giriniz.';
        }
        break;
    }
  }

  // Custom rules
  rules.forEach((rule) => {
    if (!isValid) return;

    const [ruleName, ruleValue] = rule.split(':');

    switch (ruleName) {
      case 'min':
        if (value.length < parseInt(ruleValue)) {
          isValid = false;
          errorMessage = `En az ${ruleValue} karakter giriniz.`;
        }
        break;

      case 'max':
        if (value.length > parseInt(ruleValue)) {
          isValid = false;
          errorMessage = `En fazla ${ruleValue} karakter girebilirsiniz.`;
        }
        break;

      case 'match':
        const matchInput = document.querySelector(`[name="${ruleValue}"]`);
        if (matchInput && value !== matchInput.value) {
          isValid = false;
          errorMessage = 'Alanlar eşleşmiyor.';
        }
        break;
    }
  });

  // Show/hide error
  if (!isValid) {
    showError(input, errorMessage);
  } else {
    clearError(input);
  }

  return isValid;
}

/**
 * Show error message
 */
function showError(input, message) {
  input.classList.add('is-invalid');
  input.classList.remove('is-valid');

  let errorEl = input.parentNode.querySelector('.form-error');
  if (!errorEl) {
    errorEl = document.createElement('span');
    errorEl.className = 'form-error';
    input.parentNode.appendChild(errorEl);
  }

  errorEl.textContent = message;
  input.setAttribute('aria-invalid', 'true');
  input.setAttribute('aria-describedby', errorEl.id || '');
}

/**
 * Clear error message
 */
function clearError(input) {
  input.classList.remove('is-invalid');
  input.setAttribute('aria-invalid', 'false');

  const errorEl = input.parentNode.querySelector('.form-error');
  if (errorEl) {
    errorEl.remove();
  }
}

/**
 * Initialize AJAX form submission
 */
function initAjaxForm(form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn?.textContent;

    try {
      // Disable submit button
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Gönderiliyor...';
      }

      const formData = new FormData(form);
      const action = form.action || window.location.href;
      const method = form.method || 'POST';

      const response = await fetch(action, {
        method: method,
        body: formData,
        headers: {
          Accept: 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        // Success
        Swal.fire({
          icon: 'success',
          title: 'Başarılı!',
          text: data.message || 'İşlem başarıyla tamamlandı.',
          confirmButtonColor: '#d4a574',
        });

        // Reset form
        if (form.dataset.reset !== 'false') {
          form.reset();
        }

        // Redirect if specified
        if (data.redirect) {
          window.location.href = data.redirect;
        }
      } else {
        // Error
        Swal.fire({
          icon: 'error',
          title: 'Hata!',
          text: data.error || 'Bir hata oluştu.',
          confirmButtonColor: '#d4a574',
        });

        // Show field errors
        if (data.errors) {
          Object.keys(data.errors).forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
              showError(input, data.errors[field][0]);
            }
          });
        }
      }
    } catch (error) {
      console.error('Form submission error:', error);
      Swal.fire({
        icon: 'error',
        title: 'Hata!',
        text: 'Bağlantı hatası. Lütfen tekrar deneyin.',
        confirmButtonColor: '#d4a574',
      });
    } finally {
      // Re-enable submit button
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  });
}

// Validation helpers
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
  const cleaned = phone.replace(/\D/g, '');
  return cleaned.length >= 10 && cleaned.length <= 11;
}
