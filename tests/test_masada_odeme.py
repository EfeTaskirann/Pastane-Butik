"""Test: Masada Odeme akisi - detayli debug"""
from playwright.sync_api import sync_playwright
import sys, json
sys.stdout.reconfigure(encoding='utf-8')

BASE = "http://localhost/pastane"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)

    # ADMIN: Login + Aktif et
    admin = browser.new_page(viewport={"width": 1280, "height": 800})
    admin.goto(f"{BASE}/admin/index.php", wait_until="networkidle")
    admin.fill("input[name='username']", "admin")
    admin.fill("input[name='password']", "admin123")
    admin.click("button[type='submit']")
    admin.wait_for_load_state("networkidle")
    admin.goto(f"{BASE}/admin/masalar.php", wait_until="networkidle")
    aktif_btn = admin.locator("button:has-text('Aktif Et')").first
    if aktif_btn.count() > 0:
        aktif_btn.click()
        admin.wait_for_load_state("networkidle")
    admin.goto(f"{BASE}/admin/masalar.php", wait_until="networkidle")
    qr_token = admin.evaluate("() => { const b = document.querySelector('.js-qr-kod'); return b ? b.dataset.qrToken : null; }")
    print(f"QR Token: {qr_token[:20] if qr_token else 'NONE'}")
    admin.close()

    # MUSTERI
    page = browser.new_page(viewport={"width": 390, "height": 844})

    console_msgs = []
    page.on("console", lambda msg: console_msgs.append(f"[{msg.type}] {msg.text}"))
    page.on("pageerror", lambda err: console_msgs.append(f"PAGE_ERR: {err}"))

    # Intercept network requests to API
    api_responses = []
    def handle_response(response):
        if "api/v1" in response.url:
            try:
                body = response.text()
            except:
                body = "(could not read)"
            api_responses.append({"url": response.url, "status": response.status, "body": body[:300]})
    page.on("response", handle_response)

    # Menu + add to cart
    page.goto(f"{BASE}/menu?t={qr_token}", wait_until="networkidle")
    page.wait_for_timeout(1000)

    # Sepete urun ekle - + butonuna tikla
    add_btn = page.locator("button:has-text('+')").first
    if add_btn.count() > 0:
        add_btn.click()
        page.wait_for_timeout(500)
        print("+ butonuna tiklandi")

    # Check localStorage
    storage_keys = page.evaluate("() => Object.keys(localStorage).filter(k => k.includes('sepet'))")
    print(f"localStorage sepet keys: {storage_keys}")

    # Sepet
    page.goto(f"{BASE}/menu/sepet.php", wait_until="networkidle")
    page.wait_for_timeout(1000)
    page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_masada_sepet.png", full_page=True)

    # Check if empty
    body_sepet = page.inner_text("body")
    print(f"Sepet body (ilk 200): {body_sepet[:200]}")

    # Click Siparis Ver
    siparis_btn = page.locator("button:has-text('Siparis Ver')").first
    if siparis_btn.count() > 0:
        print(f"\nSiparis Ver butonuna tiklaniyor...")
        siparis_btn.click()
        page.wait_for_timeout(4000)  # Wait for API + SweetAlert + redirect
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_masada_debug.png", full_page=True)

        print(f"Current URL: {page.url}")
        body = page.inner_text("body")
        print(f"Body text (ilk 300):\n{body[:300]}")

        # Check if SweetAlert is showing
        swal_visible = page.evaluate("() => { const el = document.querySelector('.swal2-container'); return el ? el.style.display !== 'none' : false; }")
        if swal_visible:
            swal_text = page.evaluate("() => { const t = document.querySelector('.swal2-title'); const h = document.querySelector('.swal2-html-container'); return (t ? t.textContent : '') + ' | ' + (h ? h.textContent : ''); }")
            print(f"SweetAlert: {swal_text}")

            # Click OK on SweetAlert
            swal_btn = page.locator(".swal2-confirm")
            if swal_btn.count() > 0:
                swal_btn.click()
                page.wait_for_timeout(2000)
                page.wait_for_load_state("networkidle")
                print(f"After SweetAlert URL: {page.url}")
    else:
        print("Siparis Ver butonu bulunamadi!")

    # Print API responses
    if api_responses:
        print(f"\n=== API RESPONSES ({len(api_responses)}) ===")
        for resp in api_responses:
            print(f"  {resp['status']} {resp['url']}")
            print(f"    {resp['body'][:200]}")

    # Print console messages
    errors = [m for m in console_msgs if "error" in m.lower() or "PAGE_ERR" in m]
    if errors:
        print(f"\n=== CONSOLE ERRORS ({len(errors)}) ===")
        for e in errors:
            print(f"  {e[:150]}")

    # If we're on odeme page, test Masada Ode
    if "odeme" in page.url:
        print(f"\n=== ODEME SAYFASINDA - Masada Ode test ===")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_masada_odeme_page.png", full_page=True)

        masada_btn = page.locator("#btnMasadaOdeme")
        if masada_btn.count() > 0 and masada_btn.is_visible():
            print("Masada Ode butonu gorunuyor - tiklaniyor...")
            masada_btn.click()
            page.wait_for_timeout(3000)
            page.wait_for_load_state("networkidle")
            page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_masada_sonuc.png", full_page=True)
            print(f"Sonuc URL: {page.url}")
            print(f"Sonuc Body: {page.inner_text('body')[:300]}")

    browser.close()
