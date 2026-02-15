/**
 * Lazy Loading Module
 * Lazy load images and other assets
 */

export function initLazyLoading() {
  // Use native lazy loading with fallback
  if ('loading' in HTMLImageElement.prototype) {
    // Native lazy loading supported
    const images = document.querySelectorAll('img[data-src]');
    images.forEach((img) => {
      img.src = img.dataset.src;
      img.loading = 'lazy';
    });
  } else {
    // Fallback to Intersection Observer
    initIntersectionObserver();
  }
}

/**
 * Initialize Intersection Observer for lazy loading
 */
function initIntersectionObserver() {
  const lazyImages = document.querySelectorAll('[data-src]');

  if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            loadImage(img);
            observer.unobserve(img);
          }
        });
      },
      {
        rootMargin: '50px 0px',
        threshold: 0.01,
      }
    );

    lazyImages.forEach((img) => {
      imageObserver.observe(img);
    });
  } else {
    // Fallback: load all images immediately
    lazyImages.forEach((img) => {
      loadImage(img);
    });
  }
}

/**
 * Load image
 */
function loadImage(img) {
  const src = img.dataset.src;
  const srcset = img.dataset.srcset;

  if (src) {
    img.src = src;
    img.removeAttribute('data-src');
  }

  if (srcset) {
    img.srcset = srcset;
    img.removeAttribute('data-srcset');
  }

  img.classList.add('is-loaded');
}

/**
 * Preload critical images
 */
export function preloadImages(urls) {
  urls.forEach((url) => {
    const link = document.createElement('link');
    link.rel = 'preload';
    link.as = 'image';
    link.href = url;
    document.head.appendChild(link);
  });
}
