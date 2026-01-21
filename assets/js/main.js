/**
 * Pasta Butik - Ana JavaScript
 * Smooth scroll, reveal animasyonları ve kategori filtreleme
 */

// Global: Promo Banner Kapatma Fonksiyonu
function closePromoBanner() {
    const banner = document.getElementById('promoBanner');
    if (banner) {
        banner.classList.add('hidden');
        sessionStorage.setItem('promoBannerClosed', 'true');
        setTimeout(() => {
            banner.style.display = 'none';
            document.body.classList.remove('has-promo-banner');
        }, 300);
    }
}

// Global: Ürün Modal Fonksiyonları
function openProductModal(productData) {
    const modal = document.getElementById('productModal');
    const modalImage = document.getElementById('modalImage');
    const modalCategory = document.getElementById('modalCategory');
    const modalTitle = document.getElementById('modalTitle');
    const modalDescription = document.getElementById('modalDescription');
    const modalPricing = document.getElementById('modalPricing');
    const modalOrderBtn = document.getElementById('modalOrderBtn');

    if (!modal) return;

    // Görsel
    if (productData.gorsel) {
        modalImage.innerHTML = `<img src="uploads/products/${productData.gorsel}" alt="${productData.isim}">`;
    } else {
        // Varsayılan SVG
        modalImage.innerHTML = `
            <svg viewBox="0 0 200 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="160" rx="70" ry="12" fill="#E8C4D4"/>
                <rect x="30" y="120" width="140" height="40" rx="5" fill="#F5E1E9"/>
                <ellipse cx="100" cy="120" rx="70" ry="12" fill="#FDF8F5"/>
                <rect x="45" y="85" width="110" height="35" rx="4" fill="#F5E1E9"/>
                <ellipse cx="100" cy="85" rx="55" ry="10" fill="#FDF8F5"/>
                <path d="M60 60 Q80 40 100 50 Q120 40 140 60" fill="#FFFFFF"/>
                <circle cx="100" cy="45" r="12" fill="#D4A5A5"/>
                <circle cx="70" cy="95" r="5" fill="#A5C4A5"/>
                <circle cx="130" cy="100" r="4" fill="#D4A5A5"/>
            </svg>
        `;
    }

    // Kategori
    if (productData.kategori) {
        modalCategory.textContent = productData.kategori;
        modalCategory.style.display = 'inline-block';
    } else {
        modalCategory.style.display = 'none';
    }

    // Başlık
    modalTitle.textContent = productData.isim;

    // Açıklama
    if (productData.aciklama) {
        modalDescription.textContent = productData.aciklama;
        modalDescription.style.display = 'block';
    } else {
        modalDescription.style.display = 'none';
    }

    // Fiyatlandırma
    const hasPorsiyonFiyat = productData.fiyat_4kisi || productData.fiyat_6kisi || productData.fiyat_8kisi || productData.fiyat_10kisi;

    if (hasPorsiyonFiyat) {
        let priceItems = '';
        if (productData.fiyat_4kisi) {
            priceItems += `<div class="price-item"><span class="portion">4 Kişilik</span><span class="amount">${formatPrice(productData.fiyat_4kisi)} ₺</span></div>`;
        }
        if (productData.fiyat_6kisi) {
            priceItems += `<div class="price-item"><span class="portion">6 Kişilik</span><span class="amount">${formatPrice(productData.fiyat_6kisi)} ₺</span></div>`;
        }
        if (productData.fiyat_8kisi) {
            priceItems += `<div class="price-item"><span class="portion">8 Kişilik</span><span class="amount">${formatPrice(productData.fiyat_8kisi)} ₺</span></div>`;
        }
        if (productData.fiyat_10kisi) {
            priceItems += `<div class="price-item"><span class="portion">10+ Kişilik</span><span class="amount">${formatPrice(productData.fiyat_10kisi)} ₺</span></div>`;
        }
        modalPricing.innerHTML = `
            <div class="price-title">Porsiyon Seçenekleri</div>
            <div class="price-grid">${priceItems}</div>
        `;
    } else {
        modalPricing.innerHTML = `<div class="single-price">${formatPrice(productData.fiyat)} ₺</div>`;
    }

    // WhatsApp Sipariş Butonu
    modalOrderBtn.href = `https://wa.me/905551234567?text=${productData.waMessage}`;

    // Modalı aç
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(price);
}

// ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // ========== PROMO BANNER ==========
    const promoBanner = document.getElementById('promoBanner');

    if (promoBanner) {
        // Banner görünürse body'ye class ekle
        document.body.classList.add('has-promo-banner');

        // Session'da kapatılmış mı kontrol et
        if (sessionStorage.getItem('promoBannerClosed') === 'true') {
            promoBanner.style.display = 'none';
            document.body.classList.remove('has-promo-banner');
        }
    }

    // ========== SCROLL PROGRESS BAR ==========
    const scrollProgress = document.getElementById('scrollProgress');

    function updateScrollProgress() {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = (scrollTop / docHeight) * 100;
        scrollProgress.style.width = scrollPercent + '%';
    }

    window.addEventListener('scroll', updateScrollProgress);

    // ========== SMOOTH SCROLL ==========
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ========== REVEAL ON SCROLL ==========
    const revealElements = document.querySelectorAll('.reveal, .product-card');

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible', 'active');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    revealElements.forEach(el => {
        revealObserver.observe(el);
    });

    // ========== PRODUCT CARD STAGGER ANIMATION ==========
    const productCards = document.querySelectorAll('.product-card');

    const productObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('visible');
                }, index * 100);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -30px 0px'
    });

    productCards.forEach(card => {
        productObserver.observe(card);
    });

    // ========== PAGINATION & CATEGORY FILTER ==========
    const filterBtns = document.querySelectorAll('.filter-btn');
    const productsGrid = document.getElementById('productsGrid');
    const paginationContainer = document.getElementById('productPagination');
    const PRODUCTS_PER_PAGE = 6;
    let currentPage = 1;
    let currentCategory = 'all';

    // Sayfalama fonksiyonu
    function updatePagination() {
        const allCards = productsGrid.querySelectorAll('.product-card');
        let visibleCards = [];

        // Kategoriye göre filtrele
        allCards.forEach(card => {
            const cardCategory = card.dataset.category;
            if (currentCategory === 'all' || cardCategory === currentCategory) {
                visibleCards.push(card);
            }
        });

        const totalPages = Math.ceil(visibleCards.length / PRODUCTS_PER_PAGE);

        // Sayfa numarasını sınırla
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        // Tüm kartları gizle
        allCards.forEach(card => {
            card.style.display = 'none';
            card.classList.remove('visible');
        });

        // Sadece mevcut sayfadaki kartları göster
        const startIndex = (currentPage - 1) * PRODUCTS_PER_PAGE;
        const endIndex = startIndex + PRODUCTS_PER_PAGE;

        visibleCards.slice(startIndex, endIndex).forEach((card, index) => {
            card.style.display = 'block';
            setTimeout(() => {
                card.classList.add('visible');
            }, index * 50);
        });

        // Sayfalama butonlarını oluştur
        renderPaginationButtons(totalPages, visibleCards.length);
    }

    function renderPaginationButtons(totalPages, totalItems) {
        if (!paginationContainer) return;

        // 6 veya daha az ürün varsa sayfalama gösterme
        if (totalItems <= PRODUCTS_PER_PAGE) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';

        // Önceki butonu
        html += `<button class="pagination-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>`;

        // Sayfa numaraları
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }

        // Sonraki butonu
        html += `<button class="pagination-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>`;

        paginationContainer.innerHTML = html;
    }

    // Global fonksiyon - sayfa değiştirme (animasyonlu)
    window.goToPage = function(page) {
        if (page === currentPage) return;

        const direction = page > currentPage ? 'next' : 'prev';
        currentPage = page;

        // Animasyonlu geçiş
        animatePageTransition(direction);
    };

    // Sayfa geçiş animasyonu
    function animatePageTransition(direction) {
        if (!productsGrid) return;

        // Çıkış animasyonu class'ı
        const outClass = direction === 'next' ? 'slide-out-left' : 'slide-out-right';
        const inClass = direction === 'next' ? 'slide-in-right' : 'slide-in-left';

        // Önce çıkış animasyonu
        productsGrid.classList.add(outClass);

        // Animasyon bitince içeriği güncelle
        setTimeout(() => {
            productsGrid.classList.remove(outClass);

            // Kartları güncelle
            updatePaginationContent();

            // Giriş animasyonu
            productsGrid.classList.add(inClass);

            // Giriş animasyonu bitince class'ı temizle
            setTimeout(() => {
                productsGrid.classList.remove(inClass);
            }, 300);

            // Sayfalama butonlarını güncelle
            updatePaginationButtons();

            // Ürünler bölümüne yumuşak kaydır
            const productsSection = document.getElementById('products');
            if (productsSection) {
                productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

        }, 300);
    }

    // Sadece içeriği güncelle (animasyonsuz)
    function updatePaginationContent() {
        const allCards = productsGrid.querySelectorAll('.product-card');
        let visibleCards = [];

        // Kategoriye göre filtrele
        allCards.forEach(card => {
            const cardCategory = card.dataset.category;
            if (currentCategory === 'all' || cardCategory === currentCategory) {
                visibleCards.push(card);
            }
        });

        // Tüm kartları gizle
        allCards.forEach(card => {
            card.style.display = 'none';
            card.classList.remove('visible');
        });

        // Sadece mevcut sayfadaki kartları göster
        const startIndex = (currentPage - 1) * PRODUCTS_PER_PAGE;
        const endIndex = startIndex + PRODUCTS_PER_PAGE;

        visibleCards.slice(startIndex, endIndex).forEach((card) => {
            card.style.display = 'block';
            card.classList.add('visible');
        });
    }

    // Sadece butonları güncelle
    function updatePaginationButtons() {
        const allCards = productsGrid.querySelectorAll('.product-card');
        let visibleCards = [];

        allCards.forEach(card => {
            const cardCategory = card.dataset.category;
            if (currentCategory === 'all' || cardCategory === currentCategory) {
                visibleCards.push(card);
            }
        });

        const totalPages = Math.ceil(visibleCards.length / PRODUCTS_PER_PAGE);
        renderPaginationButtons(totalPages, visibleCards.length);
    }

    // Kategori filtresi
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Aktif butonu güncelle
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            currentCategory = this.dataset.category;
            currentPage = 1; // Kategori değişince ilk sayfaya dön
            updatePagination();
        });
    });

    // Sayfa yüklendiğinde sayfalamayı başlat
    if (productsGrid) {
        updatePagination();
    }

    // ========== PARALLAX EFFECT (Subtle) ==========
    const decorations = document.querySelectorAll('.decoration');

    window.addEventListener('scroll', function() {
        const scrollY = window.scrollY;

        decorations.forEach((dec, index) => {
            const speed = (index + 1) * 0.02;
            dec.style.transform = `translateY(${scrollY * speed}px)`;
        });
    });

    // ========== GRADIENT BACKGROUND TRANSITION ==========
    const sections = document.querySelectorAll('section');
    const body = document.body;

    const gradients = {
        hero: 'linear-gradient(180deg, #F5E1E9 0%, #FDF8F5 100%)',
        about: 'linear-gradient(180deg, #FDF8F5 0%, #F5EDE8 50%, #F5E1E9 100%)',
        products: 'linear-gradient(180deg, #F5E1E9 0%, #FDF8F5 100%)',
        contact: 'linear-gradient(180deg, #F5E1E9 0%, #E8C4D4 100%)'
    };

    const bgObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
                const sectionId = entry.target.id;
                if (gradients[sectionId]) {
                    body.style.transition = 'background 0.8s ease';
                    body.style.background = gradients[sectionId];
                }
            }
        });
    }, {
        threshold: [0.3, 0.5, 0.7],
        rootMargin: '-10% 0px -10% 0px'
    });

    sections.forEach(section => {
        if (section.id) {
            bgObserver.observe(section);
        }
    });

    // ========== FORM VALIDATION ==========
    const contactForm = document.querySelector('.contact-form');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const name = this.querySelector('#name').value.trim();
            const message = this.querySelector('#message').value.trim();

            if (name.length < 2) {
                e.preventDefault();
                showNotification('Lütfen adınızı girin.', 'error');
                return;
            }

            if (message.length < 10) {
                e.preventDefault();
                showNotification('Mesajınız en az 10 karakter olmalıdır.', 'error');
                return;
            }
        });
    }

    // ========== NOTIFICATION SYSTEM ==========
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? '#7BA87B' : '#D4A5A5'};
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // Animation keyframes ekleme
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

    // ========== HERO ANIMATION ==========
    const heroContent = document.querySelector('.hero-content');
    if (heroContent) {
        heroContent.style.opacity = '0';
        heroContent.style.transform = 'translateY(30px)';

        setTimeout(() => {
            heroContent.style.transition = 'all 1s ease';
            heroContent.style.opacity = '1';
            heroContent.style.transform = 'translateY(0)';
        }, 200);
    }

    // ========== LAZY LOADING FOR IMAGES ==========
    const lazyImages = document.querySelectorAll('img[data-src]');

    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                imageObserver.unobserve(img);
            }
        });
    });

    lazyImages.forEach(img => {
        imageObserver.observe(img);
    });

    // ========== WHATSAPP BUTTON ANIMATION ==========
    const whatsappBtn = document.querySelector('.whatsapp-float');

    if (whatsappBtn) {
        let lastScroll = 0;

        window.addEventListener('scroll', function() {
            const currentScroll = window.scrollY;

            if (currentScroll > lastScroll && currentScroll > 300) {
                whatsappBtn.style.transform = 'scale(0.8)';
                whatsappBtn.style.opacity = '0.7';
            } else {
                whatsappBtn.style.transform = 'scale(1)';
                whatsappBtn.style.opacity = '1';
            }

            lastScroll = currentScroll;
        });
    }

    // ========== MOUSE FOLLOWER (Optional subtle effect) ==========
    const heroSection = document.querySelector('.hero');

    if (heroSection && window.innerWidth > 768) {
        heroSection.addEventListener('mousemove', function(e) {
            const decorations = this.querySelectorAll('.decoration');
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            decorations.forEach((dec, i) => {
                const factor = (i + 1) * 15;
                dec.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
            });
        });
    }

    // ========== SPARKLE GENERATOR ==========
    const sparkleContainer = document.getElementById('sparkleContainer');

    if (sparkleContainer && window.innerWidth > 768) {
        let isScrolling = false;
        let scrollTimeout;

        // Scroll sırasında parıltıları gizle
        window.addEventListener('scroll', function() {
            isScrolling = true;
            sparkleContainer.style.opacity = '0.2';
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                isScrolling = false;
                sparkleContainer.style.opacity = '1';
            }, 150);
        });

        sparkleContainer.style.transition = 'opacity 0.3s ease';

        function createSparkle() {
            if (isScrolling) return;
            const sparkle = document.createElement('div');
            const types = ['sparkle', 'sparkle sparkle-pink', 'sparkle sparkle-gold'];
            sparkle.className = types[Math.floor(Math.random() * types.length)];
            sparkle.style.left = Math.random() * 100 + '%';
            sparkle.style.top = Math.random() * 100 + '%';
            const size = Math.random() * 3 + 5; // 5-8px arası
            sparkle.style.width = size + 'px';
            sparkle.style.height = size + 'px';
            sparkle.style.animationDelay = Math.random() * 4 + 's';
            sparkle.style.animationDuration = (Math.random() * 4 + 6) + 's';
            sparkleContainer.appendChild(sparkle);
            setTimeout(() => sparkle.remove(), 12000);
        }

        // Başlangıçta 20 parıltı oluştur
        for (let i = 0; i < 20; i++) {
            setTimeout(() => createSparkle(), i * 150);
        }
        // Her 600ms'de yeni parıltı ekle
        setInterval(createSparkle, 600);

        function createSugarDust() {
            if (isScrolling) return;
            const dust = document.createElement('div');
            dust.className = 'sugar-dust';
            dust.style.left = Math.random() * 100 + '%';
            dust.style.animationDelay = Math.random() * 4 + 's';
            dust.style.animationDuration = (Math.random() * 5 + 8) + 's';
            const dustSize = Math.random() * 2 + 3; // 3-5px
            dust.style.width = dustSize + 'px';
            dust.style.height = dustSize + 'px';
            sparkleContainer.appendChild(dust);
            setTimeout(() => dust.remove(), 14000);
        }

        // Başlangıçta 10 pudra şekeri
        for (let i = 0; i < 10; i++) {
            setTimeout(() => createSugarDust(), i * 300);
        }
        // Her 1000ms'de yeni pudra ekle
        setInterval(createSugarDust, 1000);
    }

    // ========== CALENDAR LOADER ==========
    const calendarGrid = document.getElementById('calendarGrid');

    if (calendarGrid) {
        loadCalendar();
    }

    async function loadCalendar() {
        try {
            const response = await fetch('api/takvim.php');
            const data = await response.json();

            if (data.success && data.takvim) {
                renderCalendar(data.takvim);
            } else {
                calendarGrid.innerHTML = '<div class="calendar-loading"><span>Takvim yüklenemedi.</span></div>';
            }
        } catch (error) {
            console.error('Takvim yükleme hatası:', error);
            calendarGrid.innerHTML = '<div class="calendar-loading"><span>Takvim yüklenemedi.</span></div>';
        }
    }

    function renderCalendar(takvim) {
        calendarGrid.innerHTML = '';

        takvim.forEach((gun, index) => {
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day ${gun.durum}`;
            dayElement.style.animationDelay = `${index * 30}ms`;
            dayElement.innerHTML = `
                <span class="day-number">${gun.gun}</span>
                <span class="day-name">${gun.gunAdi}</span>
                <span class="day-status">${gun.label}</span>
            `;
            calendarGrid.appendChild(dayElement);
        });
    }

    // ========== GIFT BOX ANIMATION ==========
    const giftBoxContainer = document.getElementById('giftBoxContainer');

    if (giftBoxContainer) {
        let giftAnimationPlayed = false;

        const giftObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !giftAnimationPlayed) {
                    // Animasyonu başlat
                    setTimeout(() => {
                        giftBoxContainer.classList.add('animate');
                    }, 300);
                    giftAnimationPlayed = true;
                    giftObserver.unobserve(giftBoxContainer);
                }
            });
        }, {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        });

        giftObserver.observe(giftBoxContainer);
    }

    // ========== FAQ ACCORDION ==========
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');

        question.addEventListener('click', function() {
            // Diğer açık olanları kapat
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });

            // Bu öğeyi aç/kapat
            item.classList.toggle('active');
        });
    });

    // ========== PRODUCT CARD SPARKLE ON HOVER ==========
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    const sparkle = document.createElement('div');
                    sparkle.className = 'star-sparkle';
                    sparkle.style.position = 'absolute';
                    sparkle.style.left = Math.random() * 100 + '%';
                    sparkle.style.top = Math.random() * 100 + '%';
                    sparkle.style.zIndex = '10';
                    this.appendChild(sparkle);
                    setTimeout(() => sparkle.remove(), 3000);
                }, i * 100);
            }
        });
    });

    // ========== PRODUCT CARD CLICK TO OPEN MODAL ==========
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Sipariş butonuna tıklandıysa modal açılmasın
            if (e.target.closest('.product-order-btn')) {
                return;
            }

            const productData = this.dataset.product;
            if (productData) {
                try {
                    const product = JSON.parse(productData);
                    openProductModal(product);
                } catch (error) {
                    console.error('Ürün verisi ayrıştırılamadı:', error);
                }
            }
        });
    });
});
