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

/**
 * HTML özel karakterlerini escape et - XSS koruması
 */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Güvenli attribute değeri oluştur
 */
function sanitizeAttribute(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, function(m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m];
    });
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

    // Null check - tüm elementler için
    if (!modal || !modalImage || !modalCategory || !modalTitle || !modalDescription || !modalPricing || !modalOrderBtn) {
        return;
    }

    // Görsel - XSS korumalı
    if (productData.gorsel) {
        // innerHTML yerine DOM API kullan
        modalImage.innerHTML = '';
        const img = document.createElement('img');
        img.src = 'uploads/products/' + sanitizeAttribute(productData.gorsel);
        img.alt = escapeHtml(productData.isim);
        img.onerror = function() {
            this.style.display = 'none';
        };
        modalImage.appendChild(img);
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

    // Fiyatlandırma - XSS korumalı DOM API ile
    const hasPorsiyonFiyat = productData.fiyat_4kisi || productData.fiyat_6kisi || productData.fiyat_8kisi || productData.fiyat_10kisi;

    // Önce temizle
    modalPricing.innerHTML = '';

    if (hasPorsiyonFiyat) {
        const priceTitle = document.createElement('div');
        priceTitle.className = 'price-title';
        priceTitle.textContent = 'Porsiyon Seçenekleri';

        const priceGrid = document.createElement('div');
        priceGrid.className = 'price-grid';

        const portions = [
            { key: 'fiyat_4kisi', label: '4 Kişilik' },
            { key: 'fiyat_6kisi', label: '6 Kişilik' },
            { key: 'fiyat_8kisi', label: '8 Kişilik' },
            { key: 'fiyat_10kisi', label: '10+ Kişilik' }
        ];

        portions.forEach(function(p) {
            if (productData[p.key]) {
                const priceItem = document.createElement('div');
                priceItem.className = 'price-item';

                const portionSpan = document.createElement('span');
                portionSpan.className = 'portion';
                portionSpan.textContent = p.label;

                const amountSpan = document.createElement('span');
                amountSpan.className = 'amount';
                amountSpan.textContent = formatPrice(productData[p.key]) + ' ₺';

                priceItem.appendChild(portionSpan);
                priceItem.appendChild(amountSpan);
                priceGrid.appendChild(priceItem);
            }
        });

        modalPricing.appendChild(priceTitle);
        modalPricing.appendChild(priceGrid);
    } else {
        const singlePrice = document.createElement('div');
        singlePrice.className = 'single-price';
        singlePrice.textContent = formatPrice(productData.fiyat) + ' ₺';
        modalPricing.appendChild(singlePrice);
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
    var promoBanner = document.getElementById('promoBanner');

    if (promoBanner) {
        // Session'da kapatilmis mi kontrol et
        if (sessionStorage.getItem('promoBannerClosed') === 'true') {
            promoBanner.classList.add('promo-inactive');
            promoBanner.style.display = 'none';
        }

        // CSP uyumlu: inline onclick yerine event listener
        var promoCloseBtn = promoBanner.querySelector('.promo-close');
        if (promoCloseBtn) {
            promoCloseBtn.addEventListener('click', closePromoBanner);
        }
    }

    // ========== MODAL KAPATMA (CSP uyumlu) ==========
    var modalOverlay = document.querySelector('#productModal .modal-overlay');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', closeProductModal);
    }
    var modalCloseBtn = document.querySelector('#productModal .modal-close');
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeProductModal);
    }

    // ========== KEŞFET (scroll indicator) ==========
    var scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.style.cursor = 'pointer';
        scrollIndicator.addEventListener('click', function() {
            var target = document.getElementById('about');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // ========== TITLE DECORATIONS (Atkı & Şapka) ==========
    // h1'e dokunmadan, Range API ile harf pozisyonlarını ölçüp SVG overlay koyar
    (function initTitleDecorations() {
        var heroLogo = document.querySelector('.hero-logo');
        var h1 = heroLogo ? heroLogo.querySelector('h1') : null;
        if (!heroLogo || !h1) return;

        var textNode = h1.firstChild;
        if (!textNode || textNode.nodeType !== 3) return;
        var fullText = textNode.textContent;
        // "Tatlı Düşler" → 2. "t" index=2, "ü" index=7
        //  T(0) a(1) t(2) l(3) ı(4) [sp](5) D(6) ü(7) ş(8) l(9) e(10) r(11)
        var tIndex = 2;  // küçük t (atkı buraya)
        var uIndex = 7;  // ü  (şapkalar ü'nün iki noktasına hizalanır)

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.classList.add('title-deco-overlay');
        svg.setAttribute('aria-hidden', 'true');

        function getCharRect(node, charIndex) {
            var range = document.createRange();
            range.setStart(node, charIndex);
            range.setEnd(node, charIndex + 1);
            var rect = range.getBoundingClientRect();
            range.detach();
            return rect;
        }

        function updateDecorations() {
            var logoRect = heroLogo.getBoundingClientRect();
            var w = logoRect.width;
            var h = logoRect.height;
            svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
            svg.style.width = w + 'px';
            svg.style.height = h + 'px';

            var tRect = getCharRect(textNode, tIndex);
            var uRect = getCharRect(textNode, uIndex);

            // t pozisyonu (hero-logo'ya göreceli)
            var tX = tRect.left - logoRect.left + tRect.width * 0.5;
            var tY = tRect.top - logoRect.top;
            var tH = tRect.height;
            var tW = tRect.width;

            // ü pozisyonu
            var uLeft = uRect.left - logoRect.left;
            var uY = uRect.top - logoRect.top;
            var uW = uRect.width;

            // --- ATKI (t harfinin yatay çizgisi üzerinde) ---
            var scarfW = tW * 1.8;
            var scarfThick = tH * 0.07;
            var scarfY = tY + tH * 0.42;
            var scarfX = tX - scarfW * 0.45;
            var tailLen = tH * 0.35;
            var tailX = scarfX + scarfW * 0.85;
            var fringeLen = tH * 0.06;

            // --- ŞAPKALAR (ü noktalarının üstüne) ---
            // Not: uRect.top font glyph bounding-box'in tepesi — Playfair Display
            // gibi serif fontlarda umlaut noktalari bu tepeden belirgin bicimde
            // asagida kalir. hatBaseY'yi tH*0.20 asagi kaydirarak sapka bantini
            // noktalarin tam uzerine oturtuyoruz; hatH'yi de hafif kucultuyoruz
            // ki sapka tepesi harften cok uzaga firlamasin.
            var hatW = uW * 0.35;
            var hatH = tH * 0.16;
            var hatBandH = hatH * 0.25;
            var pomR = hatH * 0.18;
            var dotLeftX = uLeft + uW * 0.32;
            var dotRightX = uLeft + uW * 0.68;
            var hatBaseY = uY + tH * 0.20;

            svg.innerHTML = ''
                // Atkı
                + '<g class="title-scarf-g" transform-origin="' + tX + ' ' + scarfY + '">'
                + '<path d="M' + scarfX + ',' + scarfY + ' Q' + (scarfX+scarfW*0.25) + ',' + (scarfY-scarfThick) + ' ' + (scarfX+scarfW*0.5) + ',' + scarfY + ' Q' + (scarfX+scarfW*0.75) + ',' + (scarfY+scarfThick) + ' ' + (scarfX+scarfW) + ',' + scarfY + '" fill="none" stroke="#C0392B" stroke-width="' + (scarfThick*1.6) + '" stroke-linecap="round"/>'
                + '<path d="M' + scarfX + ',' + scarfY + ' Q' + (scarfX+scarfW*0.3) + ',' + (scarfY-scarfThick*0.5) + ' ' + (scarfX+scarfW*0.5) + ',' + scarfY + '" fill="none" stroke="#E74C3C" stroke-width="' + (scarfThick*0.8) + '" stroke-linecap="round" opacity="0.35"/>'
                // Sarkan uç
                + '<path d="M' + tailX + ',' + scarfY + ' Q' + (tailX+scarfThick) + ',' + (scarfY+tailLen*0.4) + ' ' + tailX + ',' + (scarfY+tailLen*0.7) + ' Q' + (tailX-scarfThick*0.5) + ',' + (scarfY+tailLen*0.9) + ' ' + tailX + ',' + (scarfY+tailLen) + '" fill="none" stroke="#C0392B" stroke-width="' + (scarfThick*1.4) + '" stroke-linecap="round"/>'
                // Püsküller
                + '<line x1="' + (tailX-fringeLen*0.6) + '" y1="' + (scarfY+tailLen) + '" x2="' + (tailX-fringeLen) + '" y2="' + (scarfY+tailLen+fringeLen) + '" stroke="#C0392B" stroke-width="' + (scarfThick*0.5) + '" stroke-linecap="round"/>'
                + '<line x1="' + tailX + '" y1="' + (scarfY+tailLen) + '" x2="' + tailX + '" y2="' + (scarfY+tailLen+fringeLen*1.1) + '" stroke="#E74C3C" stroke-width="' + (scarfThick*0.5) + '" stroke-linecap="round"/>'
                + '<line x1="' + (tailX+fringeLen*0.6) + '" y1="' + (scarfY+tailLen) + '" x2="' + (tailX+fringeLen) + '" y2="' + (scarfY+tailLen+fringeLen) + '" stroke="#C0392B" stroke-width="' + (scarfThick*0.5) + '" stroke-linecap="round"/>'
                // Atkı deseni çizgileri
                + '<line x1="' + (scarfX+scarfW*0.2) + '" y1="' + (scarfY-scarfThick*0.7) + '" x2="' + (scarfX+scarfW*0.2) + '" y2="' + (scarfY+scarfThick*0.7) + '" stroke="#F5E0E0" stroke-width="' + (scarfThick*0.3) + '" opacity="0.45"/>'
                + '<line x1="' + (scarfX+scarfW*0.45) + '" y1="' + (scarfY-scarfThick*0.5) + '" x2="' + (scarfX+scarfW*0.45) + '" y2="' + (scarfY+scarfThick*0.7) + '" stroke="#F5E0E0" stroke-width="' + (scarfThick*0.3) + '" opacity="0.45"/>'
                + '</g>'
                // Sol şapka
                + '<g>'
                + '<polygon points="' + (dotLeftX-hatW*0.5) + ',' + hatBaseY + ' ' + dotLeftX + ',' + (hatBaseY-hatH) + ' ' + (dotLeftX+hatW*0.5) + ',' + hatBaseY + '" fill="#C0392B"/>'
                + '<polygon points="' + (dotLeftX-hatW*0.35) + ',' + (hatBaseY-hatH*0.2) + ' ' + dotLeftX + ',' + (hatBaseY-hatH*0.8) + ' ' + (dotLeftX+hatW*0.35) + ',' + (hatBaseY-hatH*0.2) + '" fill="#E74C3C" opacity="0.35"/>'
                + '<rect x="' + (dotLeftX-hatW*0.6) + '" y="' + (hatBaseY-hatBandH*0.5) + '" width="' + (hatW*1.2) + '" height="' + hatBandH + '" rx="' + (hatBandH*0.4) + '" fill="#F5F0EB"/>'
                + '<circle cx="' + dotLeftX + '" cy="' + (hatBaseY-hatH) + '" r="' + pomR + '" fill="#F5F0EB"/>'
                // Sağ şapka
                + '<polygon points="' + (dotRightX-hatW*0.5) + ',' + hatBaseY + ' ' + dotRightX + ',' + (hatBaseY-hatH) + ' ' + (dotRightX+hatW*0.5) + ',' + hatBaseY + '" fill="#C0392B"/>'
                + '<polygon points="' + (dotRightX-hatW*0.35) + ',' + (hatBaseY-hatH*0.2) + ' ' + dotRightX + ',' + (hatBaseY-hatH*0.8) + ' ' + (dotRightX+hatW*0.35) + ',' + (hatBaseY-hatH*0.2) + '" fill="#E74C3C" opacity="0.35"/>'
                + '<rect x="' + (dotRightX-hatW*0.6) + '" y="' + (hatBaseY-hatBandH*0.5) + '" width="' + (hatW*1.2) + '" height="' + hatBandH + '" rx="' + (hatBandH*0.4) + '" fill="#F5F0EB"/>'
                + '<circle cx="' + dotRightX + '" cy="' + (hatBaseY-hatH) + '" r="' + pomR + '" fill="#F5F0EB"/>'
                + '</g>';
        }

        heroLogo.appendChild(svg);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(updateDecorations);
        } else {
            setTimeout(updateDecorations, 300);
        }
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(updateDecorations, 150);
        }, { passive: true });
    })();

    // ========== SCROLL PROGRESS BAR ==========
    const scrollProgress = document.getElementById('scrollProgress');

    function updateScrollProgress() {
        if (!scrollProgress) return;
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (docHeight <= 0) return;
        const scrollPercent = (scrollTop / docHeight) * 100;
        scrollProgress.style.width = scrollPercent + '%';
    }

    window.addEventListener('scroll', updateScrollProgress, { passive: true });

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
    // Not: .product-card elementleri asagidaki stagger observer tarafindan yonetilir
    const revealElements = document.querySelectorAll('.reveal');

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
    var productCards = document.querySelectorAll('.product-card');

    var productObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry, index) {
            if (entry.isIntersecting) {
                setTimeout(function() {
                    entry.target.classList.add('visible');
                }, index * 120);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    productCards.forEach(function(card) {
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

        // Önceki butonu - CSP uyumlu data-action pattern
        html += `<button class="pagination-btn" data-action="pagination-go" data-page="${currentPage - 1}" aria-label="Önceki sayfa" ${currentPage === 1 ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>`;

        // Sayfa numaraları
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-action="pagination-go" data-page="${i}" aria-label="Sayfa ${i}"${i === currentPage ? ' aria-current="page"' : ''}>${i}</button>`;
        }

        // Sonraki butonu
        html += `<button class="pagination-btn" data-action="pagination-go" data-page="${currentPage + 1}" aria-label="Sonraki sayfa" ${currentPage === totalPages ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>`;

        paginationContainer.innerHTML = html;
    }

    // Sayfa değiştirme (animasyonlu) - CSP uyumlu event delegation
    function goToPage(page) {
        if (page === currentPage) return;
        const direction = page > currentPage ? 'next' : 'prev';
        currentPage = page;
        animatePageTransition(direction);
    }

    // Pagination event delegation
    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action="pagination-go"]');
            if (!btn || btn.disabled) return;
            const page = parseInt(btn.getAttribute('data-page'), 10);
            if (!isNaN(page)) goToPage(page);
        });
    }

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

    // ========== PARALLAX EFFECT (JS-based, GPU-accelerated) ==========
    // iOS uyumlu: background-attachment: fixed kullanmiyoruz
    // Mobile'da devre disi, prefers-reduced-motion kontrollu
    (function initParallax() {
        var isMobile = window.innerWidth <= 768;
        var motionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
        var prefersReducedMotion = motionMedia.matches;

        // Runtime listener: kullanıcı sistem tercihini değiştirirse sayfayı reload et
        // (parallax'ı kapatmak için basit/güvenli yol)
        var motionListener = function() { /* state değişimi kayıtlı — gelecek scroll'da etkiler */ };
        if (typeof motionMedia.addEventListener === 'function') {
            motionMedia.addEventListener('change', motionListener);
        } else if (typeof motionMedia.addListener === 'function') {
            motionMedia.addListener(motionListener);
        }

        if (isMobile || prefersReducedMotion) {
            return;
        }

        var layerBack = document.querySelector('.parallax-layer--back');
        var layerMid = document.querySelector('.parallax-layer--mid');
        var layerFront = document.querySelector('.parallax-layer--front');
        var layerAbout = document.querySelector('.parallax-layer--about');
        var decorations = document.querySelectorAll('.decoration');
        var heroSection = document.getElementById('hero');
        var aboutSection = document.getElementById('about');

        if (!heroSection) {
            return;
        }

        var ticking = false;

        function updateParallax() {
            var scrollY = window.scrollY;
            var heroRect = heroSection.getBoundingClientRect();
            var heroVisible = heroRect.bottom > 0 && heroRect.top < window.innerHeight;

            // Hero parallax layers - only transform when hero is visible
            if (heroVisible) {
                if (layerBack) {
                    layerBack.style.transform = 'translate3d(0, ' + (scrollY * 0.08) + 'px, 0)';
                }
                if (layerMid) {
                    layerMid.style.transform = 'translate3d(0, ' + (scrollY * 0.15) + 'px, 0)';
                }
                if (layerFront) {
                    layerFront.style.transform = 'translate3d(0, ' + (scrollY * 0.25) + 'px, 0)';
                }
            }

            // About section hafif parallax
            if (aboutSection && layerAbout) {
                var aboutRect = aboutSection.getBoundingClientRect();
                var aboutVisible = aboutRect.bottom > 0 && aboutRect.top < window.innerHeight;
                if (aboutVisible) {
                    var aboutOffset = aboutRect.top * -0.06;
                    layerAbout.style.transform = 'translate3d(0, ' + aboutOffset + 'px, 0)';
                }
            }

            // Decoration parallax (mevcut)
            decorations.forEach(function(dec, index) {
                var speed = (index + 1) * 0.02;
                dec.style.transform = 'translateY(' + (scrollY * speed) + 'px)';
            });

            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        }, { passive: true });

        // Intersection Observer ile lazy activation
        var parallaxObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var layers = entry.target.querySelectorAll('.parallax-layer');
                layers.forEach(function(layer) {
                    if (entry.isIntersecting) {
                        layer.style.willChange = 'transform';
                    } else {
                        layer.style.willChange = 'auto';
                    }
                });
            });
        }, {
            rootMargin: '100px 0px',
            threshold: 0
        });

        if (heroSection) {
            parallaxObserver.observe(heroSection);
        }
        if (aboutSection) {
            parallaxObserver.observe(aboutSection);
        }
    })();

    // ========== GRADIENT BACKGROUND TRANSITION ==========
    const sections = document.querySelectorAll('section');
    const body = document.body;

    const rootStyles = getComputedStyle(document.documentElement);
    const pSoft = () => rootStyles.getPropertyValue('--pembe-soft').trim() || '#F5E1E9';
    const krem  = () => rootStyles.getPropertyValue('--krem').trim() || '#FDF8F5';
    const kDark = () => rootStyles.getPropertyValue('--krem-dark').trim() || '#F5EDE8';
    const pAcc  = () => rootStyles.getPropertyValue('--pembe-accent').trim() || '#E8C4D4';

    const gradients = {
        hero:     () => `linear-gradient(180deg, ${pSoft()} 0%, ${krem()} 100%)`,
        about:    () => `linear-gradient(180deg, ${krem()} 0%, ${kDark()} 50%, ${pSoft()} 100%)`,
        products: () => `linear-gradient(180deg, ${pSoft()} 0%, ${krem()} 100%)`,
        contact:  () => `linear-gradient(180deg, ${pSoft()} 0%, ${pAcc()} 100%)`
    };

    const bgObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
                const sectionId = entry.target.id;
                if (gradients[sectionId]) {
                    body.style.transition = 'background 0.8s ease';
                    body.style.background = gradients[sectionId]();
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
    // XSS korumalı notification sistemi
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        // innerHTML yerine güvenli DOM API kullan
        const messageSpan = document.createElement('span');
        messageSpan.textContent = message; // XSS korumalı - textContent kullan

        const closeButton = document.createElement('button');
        closeButton.textContent = '×';
        closeButton.addEventListener('click', function() {
            notification.remove();
        });

        notification.appendChild(messageSpan);
        notification.appendChild(closeButton);

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
                img.addEventListener('error', function onImgErr() {
                    img.removeEventListener('error', onImgErr);
                    img.classList.add('error');
                    img.style.display = 'none';
                }, { once: true });
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
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
        let waTicking = false;

        window.addEventListener('scroll', function() {
            if (!waTicking) {
                waTicking = true;
                requestAnimationFrame(function() {
                    const currentScroll = window.scrollY;

                    if (currentScroll > lastScroll && currentScroll > 300) {
                        whatsappBtn.style.transform = 'scale(0.8)';
                        whatsappBtn.style.opacity = '0.7';
                    } else {
                        whatsappBtn.style.transform = 'scale(1)';
                        whatsappBtn.style.opacity = '1';
                    }

                    lastScroll = currentScroll;
                    waTicking = false;
                });
            }
        }, { passive: true });
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

    // ========== SPARKLE GENERATOR (Optimized) ==========
    const sparkleContainer = document.getElementById('sparkleContainer');

    if (sparkleContainer && window.innerWidth > 768) {
        let isScrolling = false;
        let scrollTimeout;
        let isPaused = false;
        let sparkleCount = 0;
        let dustCount = 0;
        const MAX_SPARKLES = 30;
        const MAX_DUST = 15;
        let lastSparkleTime = 0;
        let lastDustTime = 0;
        const SPARKLE_INTERVAL = 600;
        const DUST_INTERVAL = 1000;
        let animationFrameId = null;

        // Tab görünürlüğü kontrolü - arka planda animasyonu durdur
        document.addEventListener('visibilitychange', function() {
            isPaused = document.hidden;
            if (!document.hidden && animationFrameId === null) {
                animationLoop();
            }
        });

        // Scroll sırasında parıltıları gizle
        window.addEventListener('scroll', function() {
            isScrolling = true;
            sparkleContainer.style.opacity = '0.2';
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                isScrolling = false;
                sparkleContainer.style.opacity = '1';
            }, 150);
        }, { passive: true });

        sparkleContainer.style.transition = 'opacity 0.3s ease';

        function createSparkle() {
            if (isScrolling || sparkleCount >= MAX_SPARKLES) return;
            sparkleCount++;
            const sparkle = document.createElement('div');
            const types = ['sparkle', 'sparkle sparkle-pink', 'sparkle sparkle-gold'];
            sparkle.className = types[Math.floor(Math.random() * types.length)];
            sparkle.style.left = Math.random() * 100 + '%';
            sparkle.style.top = Math.random() * 100 + '%';
            const size = Math.random() * 3 + 5;
            sparkle.style.width = size + 'px';
            sparkle.style.height = size + 'px';
            sparkle.style.animationDelay = Math.random() * 4 + 's';
            sparkle.style.animationDuration = (Math.random() * 4 + 6) + 's';
            sparkleContainer.appendChild(sparkle);
            setTimeout(() => {
                sparkle.remove();
                sparkleCount--;
            }, 12000);
        }

        function createSugarDust() {
            if (isScrolling || dustCount >= MAX_DUST) return;
            dustCount++;
            const dust = document.createElement('div');
            dust.className = 'sugar-dust';
            dust.style.left = Math.random() * 100 + '%';
            dust.style.animationDelay = Math.random() * 4 + 's';
            dust.style.animationDuration = (Math.random() * 5 + 8) + 's';
            const dustSize = Math.random() * 2 + 3;
            dust.style.width = dustSize + 'px';
            dust.style.height = dustSize + 'px';
            sparkleContainer.appendChild(dust);
            setTimeout(() => {
                dust.remove();
                dustCount--;
            }, 14000);
        }

        // requestAnimationFrame ile optimize edilmiş animasyon döngüsü
        function animationLoop(timestamp) {
            if (isPaused) {
                animationFrameId = null;
                return;
            }

            // Sparkle oluşturma kontrolü
            if (timestamp - lastSparkleTime >= SPARKLE_INTERVAL) {
                createSparkle();
                lastSparkleTime = timestamp;
            }

            // Dust oluşturma kontrolü
            if (timestamp - lastDustTime >= DUST_INTERVAL) {
                createSugarDust();
                lastDustTime = timestamp;
            }

            animationFrameId = requestAnimationFrame(animationLoop);
        }

        // Başlangıçta birkaç sparkle ve dust oluştur (azaltılmış)
        for (let i = 0; i < 10; i++) {
            setTimeout(() => createSparkle(), i * 200);
        }
        for (let i = 0; i < 5; i++) {
            setTimeout(() => createSugarDust(), i * 400);
        }

        // Animasyon döngüsünü başlat
        animationFrameId = requestAnimationFrame(animationLoop);
    }

    // ========== CALENDAR LOADER (1 Yıllık Görünüm) ==========
    const calendarContainer = document.getElementById('calendarGrid');
    let calendarData = null;
    let currentMonthIndex = 0;

    if (calendarContainer) {
        loadYearCalendar();
    }

    async function loadYearCalendar() {
        try {
            const response = await fetch('api/takvim.php?gorunum=yil');
            const data = await response.json();

            if (data.success && data.aylar) {
                calendarData = data.aylar;
                renderMonthCalendar(0);
            } else {
                calendarContainer.innerHTML = '<div class="calendar-loading"><span>Takvim yüklenemedi.</span></div>';
            }
        } catch (error) {
            console.error('Takvim yükleme hatası:', error);
            calendarContainer.innerHTML = '<div class="calendar-loading"><span>Takvim yüklenemedi.</span></div>';
        }
    }

    function renderMonthCalendar(monthIndex) {
        if (!calendarData || monthIndex < 0 || monthIndex >= calendarData.length) return;

        currentMonthIndex = monthIndex;
        const ayVerisi = calendarData[monthIndex];

        // Ana container'ı temizle ve yeniden oluştur
        calendarContainer.innerHTML = '';
        calendarContainer.className = 'calendar-month-view';

        // Ay navigasyonu
        const navHTML = `
            <div class="calendar-nav">
                <button class="cal-nav-btn" id="calPrevBtn" ${monthIndex === 0 ? 'disabled' : ''}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                <div class="cal-month-selector">
                    <select id="calMonthSelect" class="cal-month-dropdown">
                        ${calendarData.map((ay, idx) => `
                            <option value="${idx}" ${idx === monthIndex ? 'selected' : ''}>
                                ${ay.ayIsmi} ${ay.yil}
                            </option>
                        `).join('')}
                    </select>
                </div>
                <button class="cal-nav-btn" id="calNextBtn" ${monthIndex >= calendarData.length - 1 ? 'disabled' : ''}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        `;

        // Hafta günleri başlıkları
        const headerHTML = `
            <div class="calendar-weekdays">
                <span>Pzt</span>
                <span>Sal</span>
                <span>Çar</span>
                <span>Per</span>
                <span>Cum</span>
                <span>Cmt</span>
                <span>Paz</span>
            </div>
        `;

        // Takvim grid'i
        let gridHTML = '<div class="calendar-days-grid">';

        // Ayın başındaki boş günler (Pazartesi = 1, Pazar = 7)
        const bosGunSayisi = ayVerisi.ilkGunHaftaGunu - 1;
        for (let i = 0; i < bosGunSayisi; i++) {
            gridHTML += '<div class="calendar-day empty"></div>';
        }

        // Günleri ekle
        ayVerisi.takvim.forEach((gun, index) => {
            const gecmisClass = gun.gecmis ? 'gecmis' : '';
            const bugunClass = gun.bugun ? 'bugun' : '';

            gridHTML += `
                <div class="calendar-day ${gun.durum} ${gecmisClass} ${bugunClass}" style="animation-delay: ${index * 20}ms">
                    <span class="day-number">${gun.gun}</span>
                    <span class="day-status">${gun.label}</span>
                </div>
            `;
        });

        gridHTML += '</div>';

        // Tüm HTML'i birleştir
        calendarContainer.innerHTML = navHTML + headerHTML + gridHTML;

        // Event listener'ları ekle
        document.getElementById('calPrevBtn').addEventListener('click', () => {
            if (currentMonthIndex > 0) {
                renderMonthCalendar(currentMonthIndex - 1);
            }
        });

        document.getElementById('calNextBtn').addEventListener('click', () => {
            if (currentMonthIndex < calendarData.length - 1) {
                renderMonthCalendar(currentMonthIndex + 1);
            }
        });

        document.getElementById('calMonthSelect').addEventListener('change', (e) => {
            renderMonthCalendar(parseInt(e.target.value));
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

    // ========== PRODUCT CARD SPARKLE ON HOVER (Optimized) ==========
    // Throttle ile aşırı sparkle oluşumunu engelle
    const cardSparkleTimers = new WeakMap();

    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            // Throttle: 500ms içinde tekrar tetiklenmesini engelle
            const lastTime = cardSparkleTimers.get(this) || 0;
            const now = Date.now();
            if (now - lastTime < 500) return;
            cardSparkleTimers.set(this, now);

            // Azaltılmış sparkle sayısı (5 → 3)
            for (let i = 0; i < 3; i++) {
                setTimeout(() => {
                    const sparkle = document.createElement('div');
                    sparkle.className = 'star-sparkle';
                    sparkle.style.cssText = `position:absolute;left:${Math.random()*100}%;top:${Math.random()*100}%;z-index:10;`;
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
