/**
 * Smooth Scroll Module
 * Handle smooth scrolling for anchor links
 */

export function initSmoothScroll() {
  // Get all anchor links
  const anchors = document.querySelectorAll('a[href^="#"]');

  anchors.forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');

      // Skip if just '#' or empty
      if (!href || href === '#') return;

      // CSS.escape ile ID icinde ozel karakterleri guvenli hale getir —
      // aksi halde querySelector DOMException firlatabilir.
      const id = href.slice(1);
      let target = null;
      try {
        target = document.getElementById(id) || document.querySelector(`#${CSS.escape(id)}`);
      } catch (_err) {
        target = null;
      }

      if (target) {
        e.preventDefault();
        scrollToElement(target);

        // Skip-link entegrasyonu: hedefe klavye odagi tasi (WCAG).
        // Native <main>/<section> focus alamaz -> tabindex="-1" otomatik ekle.
        if (!target.hasAttribute('tabindex')) {
          target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });

        // Update URL hash without scrolling
        history.pushState(null, '', href);
      }
    });
  });
}

/**
 * Scroll to element with offset
 */
export function scrollToElement(element, offset = 80) {
  const elementPosition = element.getBoundingClientRect().top;
  const offsetPosition = elementPosition + window.pageYOffset - offset;

  // Check for reduced motion preference
  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;

  window.scrollTo({
    top: offsetPosition,
    behavior: prefersReducedMotion ? 'auto' : 'smooth',
  });
}

/**
 * Scroll to top
 */
export function scrollToTop() {
  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;

  window.scrollTo({
    top: 0,
    behavior: prefersReducedMotion ? 'auto' : 'smooth',
  });
}

/**
 * Initialize scroll to top button
 */
export function initScrollToTopButton() {
  const btn = document.querySelector('[data-scroll-top]');
  if (!btn) return;

  // Scroll listener rAF ile throttle edilir + son durum cache'lenir
  // -> 60Hz'de classList mutation'dan kacinir (CLAUDE.md performans pattern).
  let ticking = false;
  let lastVisible = false;

  window.addEventListener(
    'scroll',
    () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const shouldShow = window.pageYOffset > 300;
        if (shouldShow !== lastVisible) {
          btn.classList.toggle('is-visible', shouldShow);
          lastVisible = shouldShow;
        }
        ticking = false;
      });
    },
    { passive: true }
  );

  // Click handler
  btn.addEventListener('click', scrollToTop);
}
