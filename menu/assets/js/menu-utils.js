/**
 * Menu Ortak Yardimci Fonksiyonlar
 *
 * Tum menu sayfalari (index, sepet, siparis-takip) tarafindan kullanilir.
 * Tekrar eden kodlari ortaklastirir.
 *
 * @package Pastane\Menu
 * @since 1.0.0
 */

/**
 * Fiyat formatla
 * Ornek: 350.00 -> "350,00 ₺"
 *
 * @param {number} amount - Fiyat degeri
 * @returns {string} Formatlanmis fiyat
 */
function formatPrice(amount) {
    var num = parseFloat(amount);
    if (isNaN(num)) return '0,00 \u20BA';
    return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' \u20BA';
}

/**
 * HTML escape (XSS korumasi)
 *
 * @param {string} str - Escape edilecek metin
 * @returns {string} Escape edilmis metin
 */
function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

/**
 * Cookie'den deger oku
 *
 * @param {string} name - Cookie adi
 * @returns {string|null} Cookie degeri veya null
 */
function getCookie(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}
