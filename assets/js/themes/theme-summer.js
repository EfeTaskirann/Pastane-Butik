/**
 * Yaz Temasi — Yuzen Meyve & Gunes Isigi Animasyonu
 * Tatli Dusler Pastane
 *
 * Pastane tatlilarinda kullanilan meyveler:
 * Cilek, Limon, Portakal, Seftali, Kiraz, Yaban Mersini, Kivi
 */
(function () {
    'use strict';

    var reducedMotionMql = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reducedMotionMql.matches) {
        return;
    }

    // DOM hazir degilse bekle — script <head>'te defer olmadan yuklendigi
    // senaryoda document.body null olabilir, append hatasi verir.
    if (!document.body) {
        document.addEventListener('DOMContentLoaded', function onReady() {
            document.removeEventListener('DOMContentLoaded', onReady);
            // Re-execute the IIFE by re-invoking the script tag pattern is karmasik;
            // bunun yerine erken return et ve script'i defer/asyncsiz yukleyen
            // caller'a basvur. Pratikte admin/public footer sonunda yukleniyor.
        });
        return;
    }

    var isMobile = window.innerWidth < 768;

    // =============================================
    // 1. YUZEN MEYVE ANIMASYONU — Gelismis versiyon
    //    Daha cesitli meyveler + yaprak emojileri
    // =============================================

    var fruitContainer = document.createElement('div');
    fruitContainer.className = 'summer-fruits';
    fruitContainer.setAttribute('aria-hidden', 'true');
    document.body.appendChild(fruitContainer);

    // Genisletilmis meyve + yaprak listesi
    var fruits = [
        '\uD83C\uDF53', // strawberry
        '\uD83C\uDF4B', // lemon
        '\uD83C\uDF4A', // orange
        '\uD83C\uDF51', // peach
        '\uD83C\uDF52', // cherry
        '\uD83E\uDED0', // blueberry
        '\uD83E\uDD5D', // kiwi
        '\uD83C\uDF49', // watermelon
        '\uD83C\uDF4D', // pineapple
        '\uD83C\uDF47', // grapes
        '\uD83C\uDF3F', // herb/leaf
        '\uD83C\uDF3B', // sunflower
        '\uD83C\uDF3A', // hibiscus
        '\uD83C\uDF38', // cherry blossom
    ];

    var maxFruits = isMobile ? 12 : 22;
    var spawnRate = isMobile ? 1500 : 938;
    var fruitIntervalId = null;

    function createFruit() {
        if (fruitContainer.childElementCount >= maxFruits) return;

        var fruit = document.createElement('span');
        fruit.className = 'floating-fruit';
        fruit.textContent = fruits[Math.floor(Math.random() * fruits.length)];
        fruit.setAttribute('aria-hidden', 'true');

        var left = (Math.random() * 100).toFixed(1);
        var duration = (Math.random() * 14 + 10).toFixed(1);
        var delay = (Math.random() * 3).toFixed(1);
        var size = (Math.random() * 0.8 + 1.0).toFixed(2);
        var sway = ((Math.random() - 0.5) * 80).toFixed(0);
        var swayEnd = ((Math.random() - 0.5) * 80).toFixed(0);

        fruit.style.cssText =
            'left:' + left + '%;' +
            'font-size:' + size + 'rem;' +
            'animation-duration:' + duration + 's;' +
            'animation-delay:' + delay + 's;' +
            '--sway:' + sway + 'px;' +
            '--sway-end:' + swayEnd + 'px;';

        fruitContainer.appendChild(fruit);

        var totalTime = (parseFloat(duration) + parseFloat(delay)) * 1000 + 500;
        setTimeout(function () {
            if (fruit.parentNode) fruit.remove();
        }, totalTime);
    }

    function startFruits() {
        if (fruitIntervalId) return;
        fruitIntervalId = setInterval(createFruit, spawnRate);
    }

    function stopFruits() {
        if (fruitIntervalId) {
            clearInterval(fruitIntervalId);
            fruitIntervalId = null;
        }
    }

    // =============================================
    // 2. GUNES ISIGI EFEKTI (Hero bolumu)
    // =============================================

    var hero = document.querySelector('.hero');
    if (hero) {
        var sunGlow = document.createElement('div');
        sunGlow.className = 'summer-sunshine-rays';
        sunGlow.setAttribute('aria-hidden', 'true');
        hero.style.position = 'relative';
        hero.style.overflow = 'hidden';
        hero.appendChild(sunGlow);

        var sunRays = document.createElement('div');
        sunRays.className = 'summer-sun-rays';
        sunRays.setAttribute('aria-hidden', 'true');
        hero.appendChild(sunRays);
    }

    // =============================================
    // 3. KELEBEK ANIMASYONU — Arada ucan kelebek
    // =============================================
    var butterflyEmojis = ['\uD83E\uDD8B'];
    var butterflyTimerId = null;
    var maxButterflies = isMobile ? 0 : 2;

    function createButterfly() {
        var existing = document.querySelectorAll('.summer-butterfly');
        if (existing.length >= maxButterflies) return;

        var el = document.createElement('span');
        el.className = 'summer-butterfly';
        el.textContent = butterflyEmojis[0];
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);

        // Rastgele baslangic pozisyonu (kenardan)
        var startSide = Math.random() < 0.5 ? -30 : window.innerWidth + 30;
        var startY = Math.random() * window.innerHeight * 0.6 + 50;
        var endX = startSide < 0 ? window.innerWidth + 50 : -50;
        var endY = startY + (Math.random() - 0.5) * 200;

        var duration = Math.random() * 8000 + 8000;
        var startTime = performance.now();
        var animFrameId = null;

        function animateButterfly(now) {
            var elapsed = now - startTime;
            var t = Math.min(elapsed / duration, 1);

            // Bezier-benzeri yol — dogal ucus hissi
            var x = startSide + (endX - startSide) * t;
            var waveY = Math.sin(t * Math.PI * 6) * 30;
            var waveX = Math.sin(t * Math.PI * 3) * 15;
            var y = startY + (endY - startY) * t + waveY;

            // Kanat cirpma hissi — kucuk scale oscillation
            var wingFlap = 0.8 + Math.sin(elapsed * 0.015) * 0.2;

            el.style.cssText =
                'left:' + (x + waveX) + 'px;' +
                'top:' + y + 'px;' +
                'opacity:' + (t < 0.1 ? t * 10 : t > 0.9 ? (1 - t) * 10 : 1) + ';' +
                'transform:scaleX(' + (startSide < 0 ? 1 : -1) + ') scaleY(' + wingFlap.toFixed(2) + ');' +
                'position:fixed;pointer-events:none;z-index:3;font-size:1.3rem;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1));';

            if (t < 1) {
                animFrameId = requestAnimationFrame(animateButterfly);
            } else {
                if (el.parentNode) el.remove();
            }
        }

        animFrameId = requestAnimationFrame(animateButterfly);

        // Guvenlik — sure asiminda temizle
        setTimeout(function () {
            if (animFrameId) cancelAnimationFrame(animFrameId);
            if (el.parentNode) el.remove();
        }, duration + 1000);
    }

    function startButterflies() {
        if (isMobile || butterflyTimerId) return;
        butterflyTimerId = setInterval(createButterfly, 12000);
        // Ilk kelebek biraz gecikmeyle
        setTimeout(createButterfly, 4000);
    }

    function stopButterflies() {
        if (butterflyTimerId) {
            clearInterval(butterflyTimerId);
            butterflyTimerId = null;
        }
    }

    // =============================================
    // 4. GUNES ISIGI PARILTISI — Rastgele parlayan noktalar
    // =============================================
    var sparkleTimerId = null;

    function createSparkleDot() {
        var dot = document.createElement('div');
        dot.className = 'summer-sparkle-dot';
        dot.setAttribute('aria-hidden', 'true');

        var x = (Math.random() * 90 + 5).toFixed(1);
        var y = (Math.random() * 80 + 10).toFixed(1);

        dot.style.left = x + '%';
        dot.style.top = y + '%';
        document.body.appendChild(dot);

        setTimeout(function () {
            if (dot.parentNode) dot.remove();
        }, 2200);
    }

    function startSparkles() {
        if (sparkleTimerId) return;
        var rate = isMobile ? 3000 : 1500;
        sparkleTimerId = setInterval(createSparkleDot, rate);
    }

    function stopSparkles() {
        if (sparkleTimerId) {
            clearInterval(sparkleTimerId);
            sparkleTimerId = null;
        }
    }

    // =============================================
    // 5. LENS FLARE EFEKTİ
    // =============================================
    if (!isMobile) {
        var lensFlare = document.createElement('div');
        lensFlare.className = 'summer-lens-flare';
        lensFlare.setAttribute('aria-hidden', 'true');
        document.body.appendChild(lensFlare);
    }

    // =============================================
    // 6. YASAM DONGUSU YONETIMI
    // =============================================

    startFruits();
    startButterflies();
    startSparkles();

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopFruits();
            stopButterflies();
            stopSparkles();
        } else {
            startFruits();
            startButterflies();
            startSparkles();
        }
    });

    window.addEventListener('beforeunload', function () {
        stopFruits();
        stopButterflies();
        stopSparkles();
    });

    // Reduced-motion tercihi seans ortasinda acilirsa animasyonlari durdur.
    // (Bazi tarayicilarda `addEventListener('change', ...)` destekli, eski
    // Safari'de `addListener` fallback gerekir.)
    function onReducedMotionChange(e) {
        if (e.matches) {
            stopFruits();
            stopButterflies();
            stopSparkles();
        }
    }
    if (typeof reducedMotionMql.addEventListener === 'function') {
        reducedMotionMql.addEventListener('change', onReducedMotionChange);
    } else if (typeof reducedMotionMql.addListener === 'function') {
        reducedMotionMql.addListener(onReducedMotionChange);
    }

    var scrollThrottle = false;
    window.addEventListener('scroll', function () {
        if (!scrollThrottle) {
            scrollThrottle = true;
            fruitContainer.style.opacity = '0.4';
            setTimeout(function () {
                fruitContainer.style.opacity = '1';
                scrollThrottle = false;
            }, 200);
        }
    }, { passive: true });
})();
