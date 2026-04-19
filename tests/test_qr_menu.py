"""QR Menu full test - masa aktif et + menu goruntule"""
from playwright.sync_api import sync_playwright
import sys
sys.stdout.reconfigure(encoding='utf-8')

BASE = "http://localhost/pastane"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)

    # ============ ADMIN: Masa aktif et + QR token al ============
    admin_page = browser.new_page(viewport={"width": 1280, "height": 800})

    # Login
    admin_page.goto(f"{BASE}/admin/index.php", wait_until="networkidle")
    admin_page.fill("input[name='username']", "admin")
    admin_page.fill("input[name='password']", "admin123")
    admin_page.click("button[type='submit']")
    admin_page.wait_for_load_state("networkidle")
    print(f"Login: {admin_page.url}")

    # Masalar sayfasi
    admin_page.goto(f"{BASE}/admin/masalar.php", wait_until="networkidle")

    # Bos masa varsa aktif et
    aktif_btn = admin_page.locator("button:has-text('Aktif Et')").first
    if aktif_btn.count() > 0:
        aktif_btn.click()
        admin_page.wait_for_load_state("networkidle")
        admin_page.wait_for_timeout(300)
        print("Masa aktif edildi")

    # QR token al (aktif masadan)
    admin_page.goto(f"{BASE}/admin/masalar.php", wait_until="networkidle")
    qr_token = admin_page.evaluate("""() => {
        const btn = document.querySelector('.js-qr-kod');
        return btn ? btn.dataset.qrToken : null;
    }""")
    print(f"QR Token: {qr_token}")

    if not qr_token:
        print("HATA: QR token bulunamadi!")
        # Tum masa kartlarinin HTML'ini goster
        cards_html = admin_page.evaluate("""() => {
            return Array.from(document.querySelectorAll('.masa-card')).map(c => ({
                text: c.textContent.substring(0,80).trim(),
                durum: c.className,
                buttons: Array.from(c.querySelectorAll('button')).map(b => b.className + ' | ' + b.textContent.trim().substring(0,20))
            }));
        }""")
        import json
        print(json.dumps(cards_html, indent=2, ensure_ascii=False))
        admin_page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_qr_debug.png", full_page=True)
        browser.close()
        sys.exit(1)

    admin_page.close()

    # ============ MUSTERI: QR Menu test (mobil) ============
    menu_page = browser.new_page(viewport={"width": 390, "height": 844})

    errors = []
    menu_page.on("console", lambda msg: errors.append(f"[{msg.type}] {msg.text}") if msg.type == "error" else None)
    menu_page.on("pageerror", lambda err: errors.append(f"PAGE: {err}"))

    menu_url = f"{BASE}/menu?t={qr_token}"
    print(f"\n=== QR Menu URL ===\n{menu_url}\n")

    resp = menu_page.goto(menu_url, wait_until="networkidle")
    menu_page.wait_for_timeout(1000)
    print(f"HTTP Status: {resp.status}")
    print(f"Title: {menu_page.title()}")
    print(f"URL: {menu_page.url}")

    body = menu_page.inner_text("body")
    html = menu_page.content()

    # PHP hata kontrolu
    php_err = any(kw in body for kw in ["Fatal", "Exception", "Warning:", "Parse error"])
    if php_err:
        print(f"\n!!! PHP HATASI !!!\n{body[:500]}")

    # Aktif degil kontrolu
    if "Aktif Degil" in body or "aktif degil" in body.lower():
        print(f"\n!!! MASA HALA AKTIF DEGIL !!!\n{body[:300]}")

    menu_page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_qr_menu_01.png", full_page=True)
    print(f"\n=== Sayfa Icerik (ilk 600 karakter) ===\n{body[:600]}")

    # Sayfa elemanlari
    print(f"\n=== Sayfa Analizi ===")
    h_tags = menu_page.locator("h1, h2, h3").all()
    for h in h_tags[:5]:
        print(f"  {h.evaluate('el => el.tagName')}: {h.inner_text()[:60]}")

    kategori_count = menu_page.locator("[class*='kategori'], [class*='category'], .menu-kategori").count()
    urun_count = menu_page.locator("[class*='urun'], [class*='product'], .menu-item").count()
    print(f"  Kategori elem: {kategori_count}")
    print(f"  Urun elem: {urun_count}")

    # Scroll down for full page
    menu_page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
    menu_page.wait_for_timeout(500)
    menu_page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_qr_menu_02_scroll.png", full_page=True)

    if errors:
        print(f"\n=== JS HATALAR ({len(errors)}) ===")
        for e in errors:
            print(f"  {e}")

    browser.close()
    print("\n=== TEST TAMAMLANDI ===")
