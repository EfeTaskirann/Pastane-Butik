"""
REVIEWER AGENT - ITERASYON 2 - Masalar Sayfasi Kapsamli Test
CSP fix sonrasi - addEventListener tabanli butonlari test eder
"""
from playwright.sync_api import sync_playwright
import json
import sys
sys.stdout.reconfigure(encoding='utf-8')

results = {}
console_errors = []
page_errors = []

def log(msg):
    print(msg)

def test_result(name, passed, detail=""):
    status = "PASS" if passed else "FAIL"
    results[name] = {"status": status, "detail": detail}
    icon = "OK" if passed else "XX"
    log(f"  [{status}] {icon} {name}: {detail}")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1280, "height": 800})

    page.on("console", lambda msg: console_errors.append(f"[{msg.type}] {msg.text}") if msg.type in ("error", "warning") else None)
    page.on("pageerror", lambda err: page_errors.append(str(err)))

    # ============ LOGIN ============
    log("\n=== 1. LOGIN ===")
    page.goto("http://localhost/pastane/admin/index.php", wait_until="networkidle")
    page.fill("input[name='username']", "admin")
    page.fill("input[name='password']", "admin123")
    page.click("button[type='submit']")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(500)
    logged_in = "dashboard" in page.url
    test_result("Login", logged_in, page.url)
    if not logged_in:
        browser.close()
        sys.exit(1)

    # ============ MASALAR SAYFASI ============
    log("\n=== 2. MASALAR SAYFASI ===")
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.wait_for_timeout(300)

    body_text = page.inner_text("body")
    test_result("Sayfa Yukleme", "Masa Yonetimi" in body_text, "OK")

    # ============ TEST: YENI MASA EKLE (click actual button) ============
    log("\n=== 3. YENI MASA EKLE ===")

    btn_ekle = page.locator("#btnYeniMasaEkle")
    test_result("Ekle Butonu Mevcut", btn_ekle.count() > 0, f"count: {btn_ekle.count()}")

    if btn_ekle.count() > 0:
        btn_ekle.click()
        page.wait_for_timeout(400)
        modal_vis = page.locator("#masaFormModal").is_visible()
        test_result("Ekle - Modal Acildi", modal_vis, f"visible: {modal_vis}")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_02_ekle_modal.png", full_page=True)

        if modal_vis:
            import random
            test_masa_no = str(random.randint(200, 999))
            page.fill("#masa_no", test_masa_no)
            page.fill("#kapasite", "2")
            page.select_option("#konum", "teras")
            page.click("#masaForm button[type='submit']")
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(300)

            body2 = page.inner_text("body")
            success = "basariyla" in body2.lower()
            has_err = any(kw in body2 for kw in ["Fatal", "Exception", "hata", "Geçersiz"])
            test_result("Ekle - Submit", success and not has_err, f"success={success}, err={has_err}")
            page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_03_ekle_sonuc.png", full_page=True)
            if has_err:
                for kw in ["Fatal", "Exception", "hata", "Geçersiz"]:
                    if kw in body2:
                        idx = body2.find(kw)
                        log(f"  HATA: {body2[max(0,idx-50):idx+200]}")
        else:
            test_result("Ekle - Submit", False, "Modal acilmadi")

    # Reload
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.wait_for_timeout(300)

    # ============ TEST: DUZENLE ============
    log("\n=== 4. DUZENLE ===")
    duzenle_btn = page.locator(".js-duzenle").first
    test_result("Duzenle Butonu Mevcut", duzenle_btn.count() > 0, f"count: {duzenle_btn.count()}")

    if duzenle_btn.count() > 0:
        duzenle_btn.click()
        page.wait_for_timeout(400)
        modal_vis = page.locator("#masaFormModal").is_visible()
        action_val = page.evaluate("document.getElementById('masaFormAction')?.value")
        test_result("Duzenle - Modal Acildi", modal_vis and action_val == "masa_guncelle",
                   f"visible={modal_vis}, action={action_val}")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_04_duzenle_modal.png", full_page=True)

        if modal_vis:
            page.fill("#kapasite", "10")
            page.click("#masaForm button[type='submit']")
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(300)

            body3 = page.inner_text("body")
            success = "guncellendi" in body3.lower() or "basariyla" in body3.lower()
            has_err = any(kw in body3 for kw in ["Fatal", "Exception", "hata"])
            test_result("Duzenle - Submit", success and not has_err, f"success={success}, err={has_err}")
            page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_05_duzenle_sonuc.png", full_page=True)

    # Reload
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.wait_for_timeout(300)

    # ============ TEST: QR KOD ============
    log("\n=== 5. QR KOD ===")
    qr_btn = page.locator(".js-qr-kod").first
    test_result("QR Butonu Mevcut", qr_btn.count() > 0, f"count: {qr_btn.count()}")

    if qr_btn.count() > 0:
        qr_btn.click()
        page.wait_for_timeout(500)
        qr_vis = page.locator("#qrModal").is_visible()
        test_result("QR - Modal Acildi", qr_vis, f"visible={qr_vis}")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_06_qr_modal.png", full_page=True)

        if qr_vis:
            img_src = page.evaluate("document.getElementById('qrKodImg')?.src || ''")
            test_result("QR - Image Src", "qrserver" in img_src, f"src={img_src[:80]}")

            # Close QR modal
            page.locator(".js-qr-modal-kapat").first.click()
            page.wait_for_timeout(200)
    else:
        test_result("QR - Modal Acildi", False, "QR butonu yok")

    # Reload
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.wait_for_timeout(300)

    # ============ TEST: SIL ============
    log("\n=== 6. SIL ===")
    sil_btn = page.locator(".js-sil").first
    test_result("Sil Butonu Mevcut", sil_btn.count() > 0, f"count: {sil_btn.count()}")

    if sil_btn.count() > 0:
        sil_btn.click()
        page.wait_for_timeout(400)
        sil_vis = page.locator("#silModal").is_visible()
        test_result("Sil - Modal Acildi", sil_vis, f"visible={sil_vis}")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_07_sil_modal.png", full_page=True)

        if sil_vis:
            # Kapat (silme onay modali calisiyor, onaylama yapmadan kapat)
            page.locator(".js-sil-modal-kapat").first.click()
            page.wait_for_timeout(200)
            sil_kapandi = not page.locator("#silModal").is_visible()
            test_result("Sil - Modal Kapandi", sil_kapandi, f"kapandi={sil_kapandi}")
            page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_08_sil_sonuc.png", full_page=True)

    # Reload for aktif et test
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.wait_for_timeout(300)

    # ============ TEST: AKTIF ET ============
    log("\n=== 7. AKTIF ET ===")
    # Aktif Et is a form submit button, not onclick - should work with CSP
    aktif_btn = page.locator("button:has-text('Aktif Et')").first
    if aktif_btn.count() > 0:
        aktif_btn.click()
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(300)

        body5 = page.inner_text("body")
        success = "aktif" in body5.lower() or "basariyla" in body5.lower()
        has_err = any(kw in body5 for kw in ["Fatal", "Exception", "hata"])
        test_result("Aktif Et", not has_err, f"success={success}, err={has_err}")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_09_aktif_et.png", full_page=True)

        # Check if masa shows as aktif now
        page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
        aktif_count = page.evaluate("document.querySelectorAll('.masa-card--aktif').length")
        test_result("Aktif Masa Gorunuyor", aktif_count > 0, f"aktif kart: {aktif_count}")

        # Test KAPAT button (only visible on aktif masa)
        log("\n=== 8. KAPAT ===")
        kapat_btn = page.locator("button:has-text('Kapat')").first
        if kapat_btn.count() > 0:
            kapat_btn.click()
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(300)
            body6 = page.inner_text("body")
            success = "kapat" in body6.lower() or "basariyla" in body6.lower()
            has_err = any(kw in body6 for kw in ["Fatal", "Exception", "hata"])
            test_result("Kapat", not has_err, f"success={success}, err={has_err}")
        else:
            test_result("Kapat", False, "Kapat butonu bulunamadi")
    else:
        test_result("Aktif Et", False, "Aktif Et butonu yok")

    # ============ CONSOLE / PAGE ERRORS ============
    log("\n=== 9. HATALAR ===")
    csp_errors = [e for e in console_errors if "Content Security Policy" in e]
    other_errors = [e for e in console_errors if "Content Security Policy" not in e and "[error]" in e]

    if csp_errors:
        for err in csp_errors:
            log(f"  ! CSP: {err[:120]}")
    test_result("CSP Hatasi Yok", len(csp_errors) == 0, f"{len(csp_errors)} CSP hatasi")

    if other_errors:
        for err in other_errors:
            log(f"  ! {err[:120]}")
    test_result("Diger JS Hatasi Yok", len(other_errors) == 0, f"{len(other_errors)} hata")

    if page_errors:
        for err in page_errors:
            log(f"  ! PAGE: {err[:120]}")
    test_result("Page Error Yok", len(page_errors) == 0, f"{len(page_errors)} hata")

    browser.close()

# ============ RAPOR ============
log("\n" + "=" * 60)
log("REVIEWER RAPORU - ITERASYON 2")
log("=" * 60)
passed = sum(1 for r in results.values() if r["status"] == "PASS")
failed = sum(1 for r in results.values() if r["status"] == "FAIL")
log(f"\nToplam: {len(results)} test | PASS: {passed} | FAIL: {failed}")
log(f"Skor: {passed}/{len(results)}")

if failed > 0:
    log(f"\n--- FAIL ---")
    for name, r in results.items():
        if r["status"] == "FAIL":
            log(f"  XX {name}: {r['detail']}")

log(f"\nSTATUS: {'APPROVED' if failed == 0 else 'NEEDS_WORK'}")
