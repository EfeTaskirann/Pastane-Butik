/**
 * Animations Module
 * Scroll-triggered animations
 */

export function initAnimations() {
  // Check for reduced motion preference
  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;

  if (prefersReducedMotion) {
    // Remove animation classes for users who prefer reduced motion
    document
      .querySelectorAll('[data-animate]')
      .forEach((el) => el.classList.add('no-animate'));
    return;
  }

  // Initialize scroll animations
  initScrollAnimations();
}

/**
 * Initialize scroll-triggered animations
 */
function initScrollAnimations() {
  const animatedElements = document.querySelectorAll('[data-animate]');

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const animation = el.dataset.animate || 'fade-in';
            const delay = el.dataset.animateDelay || '0';

            el.style.animationDelay = `${delay}ms`;
            el.classList.add('animate', `animate-${animation}`);

            observer.unobserve(el);
          }
        });
      },
      {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px',
      }
    );

    animatedElements.forEach((el) => {
      observer.observe(el);
    });
  } else {
    // Fallback: show all elements
    animatedElements.forEach((el) => {
      el.classList.add('animate');
    });
  }
}

/**
 * Stagger animation for lists
 */
export function staggerAnimation(container, selector = ':scope > *', delay = 100) {
  const items = container.querySelectorAll(selector);

  items.forEach((item, index) => {
    item.style.animationDelay = `${index * delay}ms`;
    item.classList.add('animate', 'animate-fade-in-up');
  });
}

/**
 * Animate counter
 */
export function animateCounter(element, target, duration = 2000) {
  const start = 0;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function (ease-out)
    const easeOut = 1 - Math.pow(1 - progress, 3);
    const current = Math.floor(start + (target - start) * easeOut);

    element.textContent = current.toLocaleString('tr-TR');

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

/**
 * Typing animation
 */
export function typeWriter(element, text, speed = 50) {
  let index = 0;
  element.textContent = '';

  function type() {
    if (index < text.length) {
      element.textContent += text.charAt(index);
      index++;
      setTimeout(type, speed);
    }
  }

  type();
}
