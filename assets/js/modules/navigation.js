/**
 * Navigation Module
 * Mobile menu and navigation interactions
 */

export function initMobileMenu() {
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const mobileMenu = document.querySelector('[data-mobile-menu]');
  const menuClose = document.querySelector('[data-menu-close]');
  const body = document.body;

  if (!menuToggle || !mobileMenu) return;

  // Toggle menu
  menuToggle.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.contains('is-open');

    if (isOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  // Close button
  if (menuClose) {
    menuClose.addEventListener('click', closeMenu);
  }

  // Close on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileMenu.classList.contains('is-open')) {
      closeMenu();
    }
  });

  // Close on overlay click
  mobileMenu.addEventListener('click', (e) => {
    if (e.target === mobileMenu) {
      closeMenu();
    }
  });

  function openMenu() {
    mobileMenu.classList.add('is-open');
    menuToggle.setAttribute('aria-expanded', 'true');
    body.style.overflow = 'hidden';

    // Focus first menu item
    const firstLink = mobileMenu.querySelector('a');
    if (firstLink) firstLink.focus();
  }

  function closeMenu() {
    mobileMenu.classList.remove('is-open');
    menuToggle.setAttribute('aria-expanded', 'false');
    body.style.overflow = '';
    menuToggle.focus();
  }
}

/**
 * Sticky header on scroll
 */
export function initStickyHeader() {
  const header = document.querySelector('[data-sticky-header]');
  if (!header) return;

  const headerHeight = header.offsetHeight;
  let lastScroll = 0;

  window.addEventListener(
    'scroll',
    () => {
      const currentScroll = window.pageYOffset;

      // Add sticky class when scrolled
      if (currentScroll > headerHeight) {
        header.classList.add('is-sticky');
      } else {
        header.classList.remove('is-sticky');
      }

      // Hide/show on scroll direction
      if (currentScroll > lastScroll && currentScroll > headerHeight * 2) {
        header.classList.add('is-hidden');
      } else {
        header.classList.remove('is-hidden');
      }

      lastScroll = currentScroll;
    },
    { passive: true }
  );
}
