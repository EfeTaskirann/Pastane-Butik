/**
 * Accessibility Utilities
 * ARIA, keyboard navigation, focus management
 */

/**
 * Initialize accessibility features
 */
export function initAccessibility() {
  initSkipLink();
  initKeyboardNavigation();
  initFocusManagement();
  initAriaLiveRegions();
  initReducedMotion();
}

/**
 * Skip to main content link
 *
 * CLAUDE.md Sprint 3 dersi: <main>/<section> native olarak focus alamaz.
 * tabindex="-1" yoksa ekle; focus({preventScroll}) + scrollIntoView sırası
 * çift jump'u onler.
 */
function initSkipLink() {
  const skipLink = document.querySelector('[data-skip-link]');
  const mainContent = document.getElementById('main-content');

  if (skipLink && mainContent) {
    // Ensure target is programmatically focusable (WCAG skip-link pattern)
    if (!mainContent.hasAttribute('tabindex')) {
      mainContent.setAttribute('tabindex', '-1');
    }

    skipLink.addEventListener('click', (e) => {
      e.preventDefault();
      // preventScroll: focus() browsera göre jump edebilir; scrollIntoView tek kaynak olsun
      mainContent.focus({ preventScroll: true });
      mainContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
}

/**
 * Keyboard navigation for custom components
 */
function initKeyboardNavigation() {
  // Tab panels
  document.querySelectorAll('[role="tablist"]').forEach((tablist) => {
    const tabs = tablist.querySelectorAll('[role="tab"]');
    const panels = document.querySelectorAll(
      `[aria-labelledby="${tabs[0]?.id}"]`
    ).length
      ? tabs
      : [];

    tabs.forEach((tab, index) => {
      tab.addEventListener('keydown', (e) => {
        let targetIndex;

        switch (e.key) {
          case 'ArrowLeft':
            targetIndex = index - 1;
            if (targetIndex < 0) targetIndex = tabs.length - 1;
            break;
          case 'ArrowRight':
            targetIndex = index + 1;
            if (targetIndex >= tabs.length) targetIndex = 0;
            break;
          case 'Home':
            targetIndex = 0;
            break;
          case 'End':
            targetIndex = tabs.length - 1;
            break;
          default:
            return;
        }

        e.preventDefault();
        tabs[targetIndex].focus();
        tabs[targetIndex].click();
      });
    });
  });

  // Dropdown menus
  document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');
    const items = menu?.querySelectorAll('a, button');

    if (!trigger || !menu || !items?.length) return;

    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        menu.classList.add('is-open');
        items[0].focus();
      }
    });

    menu.addEventListener('keydown', (e) => {
      const currentIndex = [...items].indexOf(document.activeElement);

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          items[(currentIndex + 1) % items.length].focus();
          break;
        case 'ArrowUp':
          e.preventDefault();
          items[(currentIndex - 1 + items.length) % items.length].focus();
          break;
        case 'Escape':
          menu.classList.remove('is-open');
          trigger.focus();
          break;
        case 'Tab':
          menu.classList.remove('is-open');
          break;
      }
    });
  });

  // Modal focus trap
  document.querySelectorAll('[data-modal]').forEach((modal) => {
    initFocusTrap(modal);
  });
}

/**
 * Focus trap for modals/dialogs
 *
 * Her Tab'da canli olarak odaklanabilir elementleri yeniden hesaplar —
 * boylece modal icerigi dinamik degisse bile trap calisir.
 * initFocusTrap cagrisinda aktif oge saklanir ve Escape ile restore edilir.
 */
export function initFocusTrap(container) {
  const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), summary, [role="button"]:not([disabled]), [tabindex]:not([tabindex="-1"])';

  // Remember the element that had focus before the trap activated.
  const previouslyFocused =
    document.activeElement instanceof HTMLElement ? document.activeElement : null;

  container.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;

    // Recompute each time — modal contents may have changed
    const focusableElements = container.querySelectorAll(FOCUSABLE_SELECTOR);
    if (focusableElements.length === 0) return;

    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    if (e.shiftKey) {
      if (document.activeElement === firstFocusable) {
        e.preventDefault();
        lastFocusable.focus();
      }
    } else {
      if (document.activeElement === lastFocusable) {
        e.preventDefault();
        firstFocusable.focus();
      }
    }
  });

  // Restore focus when the container is hidden/removed.
  return () => {
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      previouslyFocused.focus();
    }
  };
}

/**
 * Focus management utilities
 */
function initFocusManagement() {
  // Add focus-visible polyfill behavior
  document.body.addEventListener('mousedown', () => {
    document.body.classList.add('using-mouse');
  });

  document.body.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      document.body.classList.remove('using-mouse');
    }
  });
}

/**
 * ARIA live regions for dynamic content
 */
function initAriaLiveRegions() {
  // Create announcement region
  let announcer = document.getElementById('aria-announcer');
  if (!announcer) {
    announcer = document.createElement('div');
    announcer.id = 'aria-announcer';
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    announcer.className = 'sr-only';
    document.body.appendChild(announcer);
  }
}

/**
 * Announce message to screen readers
 *
 * Birbirini izleyen cagrilarda onceki setTimeout iptal edilir —
 * aksi halde mesajlar cakisir ve ekran okuyucu bazen sonuncuyu atlar.
 */
let announceTimer = null;
export function announce(message, priority = 'polite') {
  const announcer = document.getElementById('aria-announcer');
  if (!announcer) return;

  if (announceTimer) {
    clearTimeout(announceTimer);
    announceTimer = null;
  }

  announcer.setAttribute('aria-live', priority);
  announcer.textContent = '';

  // Small delay to ensure the change is announced
  announceTimer = setTimeout(() => {
    announcer.textContent = message;
    announceTimer = null;
  }, 100);
}

/**
 * Reduced motion preference
 */
function initReducedMotion() {
  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  );

  function handleReducedMotion(e) {
    if (e.matches) {
      document.documentElement.classList.add('reduced-motion');
    } else {
      document.documentElement.classList.remove('reduced-motion');
    }
  }

  handleReducedMotion(prefersReducedMotion);
  prefersReducedMotion.addEventListener('change', handleReducedMotion);
}

/**
 * Check color contrast
 */
export function checkContrast(foreground, background) {
  const getLuminance = (color) => {
    const rgb = color.match(/\d+/g).map(Number);
    const [r, g, b] = rgb.map((c) => {
      c = c / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };

  const l1 = getLuminance(foreground);
  const l2 = getLuminance(background);
  const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);

  return {
    ratio: ratio.toFixed(2),
    AA: ratio >= 4.5,
    AAA: ratio >= 7,
    AALarge: ratio >= 3,
  };
}

// Auto-initialize
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAccessibility);
} else {
  initAccessibility();
}
