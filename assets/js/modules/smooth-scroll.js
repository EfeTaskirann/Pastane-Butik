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

      // Skip if just '#'
      if (href === '#') return;

      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        scrollToElement(target);

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

  // Show/hide button based on scroll position
  window.addEventListener(
    'scroll',
    () => {
      if (window.pageYOffset > 300) {
        btn.classList.add('is-visible');
      } else {
        btn.classList.remove('is-visible');
      }
    },
    { passive: true }
  );

  // Click handler
  btn.addEventListener('click', scrollToTop);
}
