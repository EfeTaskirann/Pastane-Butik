/**
 * Kış Teması — Tatlı Düşler Pastane
 *
 * 1. Kar yağışı animasyonu
 * 2. Hero SVG → Pasta Kardan Adam (Olaf-benzeri)
 * 3. Teslimat SVG → Husky'ler + tatlı yüklü kızak
 */
(function () {
    'use strict';

    // DOM hazir olana kadar bekle — script <head>'de yuklense bile document.body garanti
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    function init() {
    var reducedMotionMQ = window.matchMedia('(prefers-reduced-motion: reduce)');
    var prefersReducedMotion = reducedMotionMQ.matches;
    var isMobile = window.innerWidth < 768;

    // =============================================
    // 1. KAR YAGISI — Gelismis versiyon
    //    Farkli boyut, hiz ve ruzgar efekti
    // =============================================
    var snowContainer = null;
    var breathOverlay = null;
    var snowInterval = null;
    var windChangeTimer = null;
    var windDirection = 0;
    var windStrength = 0;
    var snowChars = ['\u2744', '\u2745', '\u2746', '*', '\u00B7'];
    var maxFlakes = isMobile ? 25 : 55;
    var spawnRate = isMobile ? 500 : 280;

    /**
     * Ruzgar yonunu ve gucunu rastgele degistir.
     * Eski timer varsa temizle — cift schedule (memory leak) onlenir.
     */
    function updateWind() {
        if (windChangeTimer) { clearTimeout(windChangeTimer); windChangeTimer = null; }
        windDirection = (Math.random() - 0.5) * 2;
        windStrength = Math.random() * 60 + 20;
        var nextChange = Math.random() * 5000 + 3000;
        windChangeTimer = setTimeout(updateWind, nextChange);
    }

    function createSnowflake() {
        if (!snowContainer || snowContainer.childElementCount >= maxFlakes) return;
        var f = document.createElement('span');
        f.className = 'snowflake';
        f.setAttribute('aria-hidden', 'true');
        f.textContent = snowChars[Math.floor(Math.random() * snowChars.length)];

        // 3 katman: kucuk/yavas (uzak), orta, buyuk/hizli (yakin)
        var layer = Math.random();
        var size, dur;
        if (layer < 0.4) {
            size = (Math.random() * 0.4 + 0.3).toFixed(2);
            dur = (Math.random() * 6 + 10).toFixed(1);
        } else if (layer < 0.8) {
            size = (Math.random() * 0.6 + 0.6).toFixed(2);
            dur = (Math.random() * 5 + 7).toFixed(1);
        } else {
            size = (Math.random() * 0.8 + 1.0).toFixed(2);
            dur = (Math.random() * 4 + 5).toFixed(1);
        }

        var left = (Math.random() * 100).toFixed(1);
        var del = (Math.random() * 2).toFixed(1);
        var baseDrift = (Math.random() - 0.5) * 80;
        var drift = (baseDrift + windDirection * windStrength).toFixed(0);
        var rot = (Math.random() * 720 - 360).toFixed(0);

        f.style.cssText =
            'left:' + left + '%;' +
            'font-size:' + size + 'rem;' +
            'animation-duration:' + dur + 's;' +
            'animation-delay:' + del + 's;' +
            '--drift:' + drift + 'px;' +
            '--rotation:' + rot + 'deg;' +
            'filter:blur(' + (layer < 0.4 ? '1px' : '0') + ');';

        snowContainer.appendChild(f);
        setTimeout(function () { if (f.parentNode) f.remove(); }, (parseFloat(dur) + parseFloat(del)) * 1000 + 500);
    }

    function startSnow() {
        if (!snowContainer || snowInterval) return;
        updateWind();
        snowInterval = setInterval(createSnowflake, spawnRate);
    }
    function stopSnow() {
        if (snowInterval) { clearInterval(snowInterval); snowInterval = null; }
        if (windChangeTimer) { clearTimeout(windChangeTimer); windChangeTimer = null; }
    }

    function setupSnow() {
        if (snowContainer) return;
        snowContainer = document.createElement('div');
        snowContainer.className = 'winter-snowfall';
        snowContainer.setAttribute('aria-hidden', 'true');
        document.body.appendChild(snowContainer);

        startSnow();

        // =============================================
        // 1b. CAM BUGUSU EFEKTİ (Breath / Sicak-soguk kontrast)
        // =============================================
        if (!isMobile && !breathOverlay) {
            breathOverlay = document.createElement('div');
            breathOverlay.className = 'winter-breath-overlay';
            breathOverlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(breathOverlay);
        }
    }

    function teardownSnow() {
        stopSnow();
        if (snowContainer && snowContainer.parentNode) {
            snowContainer.parentNode.removeChild(snowContainer);
        }
        snowContainer = null;
        if (breathOverlay && breathOverlay.parentNode) {
            breathOverlay.parentNode.removeChild(breathOverlay);
        }
        breathOverlay = null;
    }

    // visibilitychange + beforeunload: tek sefer bind, handler state'e gore davranis degistirir
    document.addEventListener('visibilitychange', function () {
        if (!snowContainer) return;
        if (document.hidden) { stopSnow(); } else { startSnow(); }
    });
    window.addEventListener('beforeunload', stopSnow);

    // Scroll listener (passive) — kar opacity flicker
    var scrollThrottle = false;
    window.addEventListener('scroll', function () {
        if (!snowContainer || scrollThrottle) return;
        scrollThrottle = true;
        snowContainer.style.opacity = '0.5';
        setTimeout(function () {
            if (snowContainer) snowContainer.style.opacity = '1';
            scrollThrottle = false;
        }, 150);
    }, { passive: true });

    // prefers-reduced-motion degisikliginde kari ac/kapat (kullanici OS ayarini degistirebilir)
    function onReducedMotionChange() {
        prefersReducedMotion = reducedMotionMQ.matches;
        if (prefersReducedMotion) { teardownSnow(); } else { setupSnow(); }
    }
    if (typeof reducedMotionMQ.addEventListener === 'function') {
        reducedMotionMQ.addEventListener('change', onReducedMotionChange);
    } else if (typeof reducedMotionMQ.addListener === 'function') {
        // Safari < 14 fallback
        reducedMotionMQ.addListener(onReducedMotionChange);
    }

    if (!prefersReducedMotion) {
        setupSnow();
    }

    // =============================================
    // 2. HERO SVG → PASTA KARDAN ADAM
    // =============================================
    var heroIllustration = document.querySelector('.hero-illustration');

    if (heroIllustration) {
        // Dekoratif SVG (sef kardan adam) — ekran okuyuculara gizli
        heroIllustration.innerHTML = '<svg viewBox="0 0 400 420" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
            + '<ellipse cx="200" cy="400" rx="180" ry="20" fill="#E8EFF5" opacity="0.6"/>'

            // ALT KAT
            + '<ellipse cx="200" cy="365" rx="110" ry="18" fill="#B8CCE0"/>'
            + '<rect x="90" y="290" width="220" height="75" rx="12" fill="#D4E4F0"/>'
            + '<ellipse cx="200" cy="290" rx="110" ry="18" fill="#E8EFF5"/>'
            + '<path d="M100 320 Q115 300 130 320 Q145 300 160 320 Q175 300 190 320" stroke="#FFF" stroke-width="6" stroke-linecap="round" fill="none" opacity="0.8"/>'
            + '<path d="M210 320 Q225 300 240 320 Q255 300 270 320 Q285 300 300 320" stroke="#FFF" stroke-width="6" stroke-linecap="round" fill="none" opacity="0.8"/>'
            + '<circle cx="200" cy="310" r="9" fill="#A67C52"/><circle cx="200" cy="310" r="6" fill="#C49A6C"/>'
            + '<circle cx="197" cy="307" r="1.5" fill="#8B5E3C"/><circle cx="203" cy="313" r="1.5" fill="#8B5E3C"/>'
            + '<circle cx="200" cy="345" r="9" fill="#A67C52"/><circle cx="200" cy="345" r="6" fill="#C49A6C"/>'
            + '<circle cx="197" cy="342" r="1.5" fill="#8B5E3C"/><circle cx="203" cy="348" r="1.5" fill="#8B5E3C"/>'

            // ORTA KAT
            + '<ellipse cx="200" cy="280" rx="90" ry="15" fill="#B8CCE0"/>'
            + '<rect x="110" y="220" width="180" height="60" rx="10" fill="#D4E4F0"/>'
            + '<ellipse cx="200" cy="220" rx="90" ry="15" fill="#E8EFF5"/>'
            + '<path d="M120 245 Q135 228 150 245 Q165 228 180 245" stroke="#FFF" stroke-width="5" stroke-linecap="round" fill="none" opacity="0.8"/>'
            + '<path d="M220 245 Q235 228 250 245 Q265 228 280 245" stroke="#FFF" stroke-width="5" stroke-linecap="round" fill="none" opacity="0.8"/>'
            + '<circle cx="200" cy="250" r="8" fill="#A67C52"/><circle cx="200" cy="250" r="5" fill="#C49A6C"/>'
            + '<circle cx="197" cy="248" r="1.5" fill="#8B5E3C"/><circle cx="203" cy="252" r="1.5" fill="#8B5E3C"/>'

            // KOLLAR
            + '<line x1="110" y1="240" x2="55" y2="200" stroke="#6B4226" stroke-width="5" stroke-linecap="round"/>'
            + '<line x1="55" y1="200" x2="40" y2="185" stroke="#6B4226" stroke-width="4" stroke-linecap="round"/>'
            + '<line x1="55" y1="200" x2="45" y2="210" stroke="#6B4226" stroke-width="4" stroke-linecap="round"/>'
            + '<line x1="290" y1="240" x2="345" y2="200" stroke="#6B4226" stroke-width="5" stroke-linecap="round"/>'
            + '<line x1="345" y1="200" x2="360" y2="185" stroke="#6B4226" stroke-width="4" stroke-linecap="round"/>'
            + '<line x1="345" y1="200" x2="355" y2="210" stroke="#6B4226" stroke-width="4" stroke-linecap="round"/>'

            // BAŞ
            + '<ellipse cx="200" cy="210" rx="75" ry="12" fill="#B8CCE0"/>'
            + '<rect x="125" y="155" width="150" height="55" rx="8" fill="#D4E4F0"/>'
            + '<ellipse cx="200" cy="155" rx="75" ry="12" fill="#E8EFF5"/>'
            + '<ellipse cx="175" cy="175" rx="8" ry="9" fill="#3D2B1F"/>'
            + '<ellipse cx="225" cy="175" rx="8" ry="9" fill="#3D2B1F"/>'
            + '<circle cx="172" cy="172" r="3" fill="#FFF" opacity="0.7"/>'
            + '<circle cx="222" cy="172" r="3" fill="#FFF" opacity="0.7"/>'
            + '<polygon points="200,180 190,195 210,195" fill="#E8943A"/>'
            + '<polygon points="200,180 193,192 207,192" fill="#F0A84D"/>'
            + '<path d="M178 200 Q190 212 200 210 Q210 212 222 200" stroke="#3D2B1F" stroke-width="3" fill="none" stroke-linecap="round"/>'

            // ŞEF ŞAPKASI
            + '<ellipse cx="200" cy="152" rx="78" ry="10" fill="#FFFFFF"/>'
            + '<path d="M135 152 Q135 115 155 105 Q175 95 200 92 Q225 95 245 105 Q265 115 265 152" fill="#FFFFFF"/>'
            + '<path d="M145 145 Q155 120 175 112 Q195 105 200 103 Q205 105 225 112 Q245 120 255 145" fill="#F5F5F5"/>'
            + '<path d="M155 130 Q175 122 200 120 Q225 122 245 130" stroke="#E0E0E0" stroke-width="1" fill="none"/>'
            + '<circle cx="200" cy="92" r="12" fill="#FFFFFF"/><circle cx="200" cy="92" r="8" fill="#F8F8F8"/>'

            // ATKI
            + '<rect x="130" y="217" width="140" height="14" rx="4" fill="#C0392B"/>'
            + '<rect x="130" y="217" width="140" height="14" rx="4" fill="url(#scarfPattern)" opacity="0.3"/>'
            + '<rect x="125" y="227" width="22" height="35" rx="3" fill="#C0392B"/>'
            + '<rect x="125" y="257" width="22" height="5" rx="2" fill="#A93226"/>'
            + '<rect x="125" y="252" width="22" height="5" rx="2" fill="#A93226"/>'
            + '<defs><pattern id="scarfPattern" width="8" height="14" patternUnits="userSpaceOnUse">'
            + '<line x1="0" y1="0" x2="8" y2="0" stroke="#A93226" stroke-width="2"/>'
            + '<line x1="0" y1="7" x2="8" y2="7" stroke="#A93226" stroke-width="2"/>'
            + '</pattern></defs>'

            // KAR TANELERİ
            + '<text x="60" y="160" font-size="18" fill="#B8CCE0" opacity="0.5">\u2744</text>'
            + '<text x="330" y="250" font-size="22" fill="#B8CCE0" opacity="0.4">\u2745</text>'
            + '<text x="50" y="320" font-size="15" fill="#B8CCE0" opacity="0.3">\u2746</text>'
            + '<text x="340" y="150" font-size="16" fill="#B8CCE0" opacity="0.4">\u2744</text>'
            + '<circle cx="160" cy="240" r="2" fill="#FFF" opacity="0.6"/>'
            + '<circle cx="240" cy="230" r="2" fill="#FFF" opacity="0.6"/>'
            + '<circle cx="180" cy="300" r="2.5" fill="#FFF" opacity="0.5"/>'
            + '<circle cx="220" cy="330" r="2" fill="#FFF" opacity="0.5"/>'
            + '</svg>';
    }

    // =============================================
    // 3. TESLİMAT → 2 KÖPEK + KIZAK + TATLILAR (sabit sahne, kayan yol)
    //    Köpek SVG: SVGRepo dog emoji, buz mavisi tonlarına uyarlanmış
    // =============================================
    var truckScene = document.querySelector('.delivery-truck-scene');

    // Köpek SVG path'leri — buz mavisi tonlarına uyarlanmış (orijinal: SVGRepo dog emoji)
    // transform matrix(-1,0,0,1,72,0) ile sağa bakan hale getirildi
    var dogIndex = 0;
    var dogSvg = function(offsetX, offsetY, scale) {
        dogIndex++;
        return '<g transform="translate(' + offsetX + ',' + offsetY + ') scale(' + scale + ')">'
            + '<g class="sled-dog sled-dog-' + dogIndex + '">'
            + '<g transform="matrix(-1,0,0,1,72,0)">'
            // Gövde renkleri: #F4AA41 → #8BACC4, #E27022 → #6B8BA4
            + '<path fill="#8BACC4" d="M17.2,12.75l-1.09,5l-2.29,0.56L12,20.3l-5.5,0.63l-0.35,1.5l1.84,3.08L15,27.31l0.95,9.9l0.63,3.92l4.94,3.71l1.99,6.15l-0.8,7.53l0.8,3.46l2.57-1.14l1.89-4.93L30.09,46.5l3.37,0.88l7.55-0.88l8.08-2.19l2.94,3.88l2.93,4l0.44,7.62l1.19,2.09l3.11-0.92L60,51.73l-1.95-6.11l1.29-6.74l-0.94-3.44l1-0.06l-0.63-2.06c0,0,5.28-4.22,5.31-9.88c0.04-7.1-7.29-13.6-12.58-11.1l1.11,1.21L56.84,15l2.96,3.93l0.26,5.88l-2.85,3c0,0-3.42,1.32-3.61,1.34c-0.19,0.02-3.36-0.37-3.78-0.44c-0.43-0.06-7.66-0.22-7.91-0.23C41.65,28.48,28,28.38,28,28.38l-1.76-5l-2.08-2.81l-2.33-1.64l-1.11-2.95L19.59,13.75L17.2,12.75z"/>'
            // Arka bacak/detay
            + '<path fill="#6B8BA4" d="M44.3,45.16l1.21,3.59l4.26,2.95l-0.24,6.34l0.61,3.86l3.17,0.51c0,0,2.25-0.67,2.79-3.58l-0.75-7.88l-3.5-3.5l-2.54-4.29L44.3,45.16z"/>'
            // Ön bacak/detay
            + '<path fill="#6B8BA4" d="M16.68,39.8l-5.53,7.67c-0.01,0.02-0.02,0.04-0.02,0.06l-0.33,4.3c0,0.03,0.01,0.06,0.02,0.08l3.19,4.5c0.02,0.03,0.06,0.05,0.09,0.05l3.95,0.26c0.05,0,0.09-0.02,0.11-0.06l1.16-1.86c0.01-0.01,0.01-0.01,0.01-0.02c0.05-0.14,0.45-2.68-1.16-2.88c-1.88-0.23-2.05-1.23-1.66-2.45c-0.01-0.05,0.01-0.1,0.05-0.12l5.53-4.09c0.06-0.04,0.07-0.13,0.02-0.19l-2.87-3.06c-0.02-0.02-0.04-0.03-0.07-0.04c-0.3-0.04-2.23-0.36-2.27-2.09C16.9,39.75,16.74,39.7,16.68,39.8z"/>'
            // Çizgiler
            + '<path fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M58.67,35.92c0.67,1.7,0.85,2.96,0.52,4.5l-1.13,5.2L60,51.73c0,0,0.12,4.77,0,8.28c-0.04,1.09-0.95,1.98-2.04,1.98h-0.35c-1.35,0-1.93-1.32-1.93-2.67l-0.85-8.39c0,0-7.49-5.47-6.46-13.6"/>'
            + '<path fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M33.52,46.5c3.83-0.21,11.23,0,14.98-2.69"/>'
            + '<path fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M51.63,13.25c5.76,1.55,8.07,4.29,8.65,7.2c1.04,5.27-4.01,9.8-9.22,8.5c-2.53-0.63-4.65-0.44-4.65-0.44L28,28.38c-1.69-8.13-6.45-9.57-6.45-9.57c-0.87-5.56-4.3-5.56-4.3-5.56l-0.7,4.84l-1.46,0.25c-0.78,0.13-1.51,0.5-2.09,1.04l-1.35,1.27l-4.11,0.44c-0.76,0.08-1.23,0.87-0.93,1.58l0.88,2.1c0.29,0.69,0.91,1.19,1.64,1.33l6.47,0.73c0,0-1,13.17,2.73,15.17c8.13,4.36,4.41,16.52,4.41,16.52c-0.82,1.44-0.19,3.46,1.47,3.46h0c0.79,0,1.52-0.44,1.9-1.14l2.1-5.47l1.38-9.14c0.11-0.72-0.04-1.44-0.37-2.09c-0.58-1.13-1.25-3.36-0.14-6.53"/>'
            + '<path fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.23,40.3l-4.35,6.66c-0.32,0.47-0.5,1.03-0.52,1.62c-0.04,1.76-0.25,5.54,3,7.69c0.85,0.56,2.46,0.64,3.31,0.11c0.72-0.45,1.3-1.29,0.77-2.89"/>'
            + '<line x1="20.53" y1="45.44" x2="16.13" y2="48.52" fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>'
            + '<path fill="none" stroke="#3D4F5F" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M46.42,48.52c0.43,1.57,3.08,3,4.25,3.73l-1.07,7.14c0,0.65,0.19,1.28,0.59,1.73c1.06,1.19,2.43,0.67,2.43,0.67"/>'
            + '</g></g></g>';
    };

    if (truckScene) {
        // Arka plan sahnesi (karlı dağlar + çam ağaçları) — sabit
        // aria-hidden: dekoratif SVG sahne, ekran okuyucular icin gorunmez
        var winterBg = '<div class="winter-scene-bg" aria-hidden="true">'
            + '<svg viewBox="0 0 500 120" fill="none" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">'
            // Karlı dağlar
            + '<path d="M0 120 L0 70 Q30 30 80 55 Q110 20 160 50 Q190 15 240 45 Q280 10 330 40 Q370 20 420 50 Q460 25 500 55 L500 120Z" fill="#D4E4F0" opacity="0.5"/>'
            + '<path d="M0 120 L0 85 Q40 50 100 70 Q150 40 220 65 Q280 35 350 60 Q400 40 460 65 Q480 55 500 70 L500 120Z" fill="#E8EFF5" opacity="0.6"/>'
            // Kar çizgileri dağ üstünde
            + '<path d="M110 23 Q120 18 130 25" stroke="#FFF" stroke-width="1.5" fill="none" opacity="0.6"/>'
            + '<path d="M280 14 Q295 8 310 16" stroke="#FFF" stroke-width="1.5" fill="none" opacity="0.6"/>'
            // Çam ağaçları (sol)
            + '<g transform="translate(30, 55)">'
            + '<polygon points="10,0 0,25 20,25" fill="#5A8A7A"/>'
            + '<polygon points="10,-10 2,18 18,18" fill="#6B9B8B"/>'
            + '<polygon points="10,-18 5,8 15,8" fill="#7BAB9B"/>'
            + '<rect x="8" y="25" width="4" height="6" fill="#6B4F3C"/>'
            + '</g>'
            + '<g transform="translate(60, 62) scale(0.7)">'
            + '<polygon points="10,0 0,25 20,25" fill="#5A8A7A"/>'
            + '<polygon points="10,-10 2,18 18,18" fill="#6B9B8B"/>'
            + '<polygon points="10,-18 5,8 15,8" fill="#7BAB9B"/>'
            + '<rect x="8" y="25" width="4" height="6" fill="#6B4F3C"/>'
            + '</g>'
            // Çam ağaçları (sağ)
            + '<g transform="translate(420, 50)">'
            + '<polygon points="12,0 0,30 24,30" fill="#5A8A7A"/>'
            + '<polygon points="12,-12 3,20 21,20" fill="#6B9B8B"/>'
            + '<polygon points="12,-22 6,10 18,10" fill="#7BAB9B"/>'
            + '<rect x="10" y="30" width="4" height="7" fill="#6B4F3C"/>'
            + '</g>'
            + '<g transform="translate(460, 60) scale(0.8)">'
            + '<polygon points="10,0 0,25 20,25" fill="#5A8A7A"/>'
            + '<polygon points="10,-10 2,18 18,18" fill="#6B9B8B"/>'
            + '<polygon points="10,-18 5,8 15,8" fill="#7BAB9B"/>'
            + '<rect x="8" y="25" width="4" height="6" fill="#6B4F3C"/>'
            + '</g>'
            // Zemin kar
            + '<rect x="0" y="105" width="500" height="15" fill="#E8EFF5" opacity="0.4" rx="3"/>'
            + '</svg></div>';

        truckScene.innerHTML =
            winterBg
            + '<div class="winter-sled-static" aria-hidden="true">'
            + '<svg class="winter-scene-svg" viewBox="0 0 380 80" fill="none" aria-hidden="true" focusable="false">'

            // === KIZAK + TATLILAR (sol taraf) ===
            // Kızak gövdesi
            + '<rect x="5" y="52" width="100" height="10" rx="4" fill="#8B6F5C" stroke="#6B4F3C" stroke-width="1.5"/>'
            + '<rect x="5" y="52" width="100" height="5" rx="4" fill="#A67C52" opacity="0.4"/>'
            // Kızak kayakları
            + '<path d="M2 72 Q2 65 10 65 L98 65 Q106 65 106 68 Q106 73 114 73" stroke="#D4A574" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
            // Korkuluklar
            + '<line x1="8" y1="52" x2="8" y2="38" stroke="#6B4F3C" stroke-width="2" stroke-linecap="round"/>'
            + '<line x1="102" y1="52" x2="102" y2="38" stroke="#6B4F3C" stroke-width="2" stroke-linecap="round"/>'
            + '<line x1="8" y1="38" x2="102" y2="38" stroke="#6B4F3C" stroke-width="1.5"/>'
            // Kırmızı kurdele
            + '<rect x="5" y="50" width="100" height="2.5" rx="1" fill="#C0392B" opacity="0.5"/>'

            // 3 katlı pasta (kızak platformuna oturmuş)
            + '<rect x="62" y="28" width="34" height="22" rx="4" fill="#D4E4F0"/>'
            + '<ellipse cx="79" cy="28" rx="18" ry="5" fill="#E8EFF5"/>'
            + '<rect x="67" y="19" width="24" height="11" rx="3" fill="#D4E4F0"/>'
            + '<ellipse cx="79" cy="19" rx="13" ry="4" fill="#FFFFFF"/>'
            + '<path d="M65 38 Q69 32 73 38 Q77 32 81 38 Q85 32 89 38" stroke="#FFF" stroke-width="2" stroke-linecap="round" fill="none"/>'
            + '<circle cx="79" cy="15" r="4.5" fill="#C0392B"/>'
            + '<path d="M79 12 L79 9" stroke="#4A7C59" stroke-width="1.5"/>'

            // Cupcake (kızak platformuna oturmuş)
            + '<path d="M18 44 L14 52 L30 52 L26 44 Z" fill="#D4A574"/>'
            + '<circle cx="22" cy="40" r="7" fill="#E8EFF5"/>'
            + '<circle cx="22" cy="40" r="5" fill="#FFFFFF"/>'
            + '<circle cx="22" cy="35" r="3" fill="#C0392B"/>'

            // Kurabiye yığını
            + '<ellipse cx="48" cy="49" rx="10" ry="3.5" fill="#C49A6C"/>'
            + '<ellipse cx="48" cy="46" rx="9" ry="3" fill="#D4A574"/>'
            + '<ellipse cx="48" cy="43" rx="8" ry="2.5" fill="#E0B88A"/>'
            + '<circle cx="45" cy="43" r="1" fill="#FFF"/><circle cx="51" cy="43" r="1" fill="#FFF"/>'

            // Yıldız kurabiye
            + '<polygon points="48,35 50,39 54,39 51,41 52,45 48,43 44,45 45,41 42,39 46,39" fill="#FFD700" stroke="#DAA520" stroke-width="0.5"/>'

            // === KOŞUM İPLERİ (kızaktan her iki köpeğin göğsüne) ===
            // Arka köpeğe giden ip (üst)
            + '<path d="M108 50 Q120 48 148 47" stroke="#8B6F5C" stroke-width="1.3" stroke-dasharray="4,3" fill="none"/>'
            // Ön köpeğe giden ip (alt)
            + '<path d="M108 53 Q130 50 168 45" stroke="#8B6F5C" stroke-width="1.3" stroke-dasharray="4,3" fill="none"/>'

            // === 2 KÖPEK (SVGRepo dog emoji, buz mavisi) ===
            + dogSvg(130, 12, 0.82)  // Arka köpek
            + dogSvg(155, 8, 0.92)   // Ön köpek

            + '</svg>'
            + '</div>';
    }

    } // end init()

})();
