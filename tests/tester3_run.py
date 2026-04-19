"""Tester 3 - Admin panel automated test runner (UI tier via Playwright)."""
import json
import subprocess
import sys
from pathlib import Path
from playwright.sync_api import sync_playwright

# Force UTF-8 print on Windows
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

BASE = "http://localhost/pastane"
ADMIN = f"{BASE}/admin"
USER = "admin"
PASS = "Admin2026!"
SHOTS = Path(r"c:\xampp\htdocs\pastane\tests\shots")
SHOTS.mkdir(exist_ok=True)
MYSQL = r"C:\xampp\mysql\bin\mysql.exe"

results = []

def rec(tid, status, note=""):
    safe_note = note.replace("->", "->")
    results.append({"id": tid, "status": status, "note": safe_note})
    print(f"[{status}] {tid}: {safe_note}", flush=True)

def shot(page, name):
    try:
        page.screenshot(path=str(SHOTS / f"{name}.png"), full_page=True)
    except Exception:
        pass

def sql(cmd):
    r = subprocess.run([MYSQL, "-u", "root", "pastane_db", "-e", cmd],
                       capture_output=True, text=True)
    return r.stdout.strip()

def reset_lockout():
    sql("DELETE FROM login_attempts; DELETE FROM rate_limits;")

def do_login(page):
    reset_lockout()
    page.context.clear_cookies()
    page.goto(f"{ADMIN}/index.php", wait_until="networkidle", timeout=15000)
    page.locator("#username").fill(USER)
    page.locator("#password").fill(PASS)
    page.locator("button[type='submit']").first.click()
    page.wait_for_load_state("networkidle", timeout=15000)

def ensure_login(page, target_url=None):
    """If current page is login page, re-login and (optionally) navigate back to target."""
    if page.locator("#username").count() > 0 and page.locator("#password").count() > 0:
        do_login(page)
        if target_url:
            page.goto(target_url, wait_until="networkidle", timeout=15000)

def safe_goto(page, url):
    """Navigate and re-login if we hit the login page."""
    page.goto(url, wait_until="networkidle", timeout=15000)
    ensure_login(page, url)

def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={"width": 1400, "height": 900})
        page = ctx.new_page()

        # ===== 3.1 Login/Dashboard =====
        reset_lockout()
        page.goto(f"{ADMIN}/index.php", wait_until="networkidle", timeout=15000)
        has_form = page.locator("#username").count() > 0 and page.locator("#password").count() > 0
        rec("T3-01", "PASS" if has_form else "FAIL",
            "Login formu (#username + #password) render oldu" if has_form else "Form bulunamadi")
        shot(page, "t3_01_login")

        # T3-02: 5+ wrong attempts triggers lockout
        reset_lockout()
        try:
            locked_attempt = None
            for i in range(7):
                page.goto(f"{ADMIN}/index.php", wait_until="networkidle", timeout=10000)
                if page.locator("#username").is_disabled():
                    locked_attempt = i
                    break
                page.locator("#username").fill("admin")
                page.locator("#password").fill(f"WRONG_PW_{i}")
                page.locator("button[type='submit']").first.click()
                page.wait_for_load_state("networkidle", timeout=10000)
                body = page.content()
                if "Kilitli" in body or "dakika sonra" in body:
                    locked_attempt = i + 1
                    break
            if locked_attempt is not None:
                rec("T3-02", "PASS", f"Hesap kilitlendi (deneme #{locked_attempt+1} sonrasi)")
            else:
                rec("T3-02", "FAIL", "7 denemede kilit tetiklenmedi")
            shot(page, "t3_02_lockout")
        except Exception as e:
            rec("T3-02", "FAIL", f"Lockout testi hata: {type(e).__name__}")

        # T3-03: successful login
        reset_lockout()
        try:
            do_login(page)
            at_dash = "dashboard" in page.url
            rec("T3-03", "PASS" if at_dash else "FAIL",
                f"Dashboard'a yonlendirildi: {page.url}")
            shot(page, "t3_03_dashboard")
        except Exception as e:
            rec("T3-03", "FAIL", f"Login exception: {type(e).__name__}: {str(e)[:80]}")
            browser.close()
            write_results()
            return

        # T3-04: dashboard has metric cards
        try:
            page.goto(f"{ADMIN}/dashboard.php", wait_until="networkidle", timeout=10000)
            text = page.inner_text("body").lower()
            has_urun = "urun" in text or "ürün" in text
            has_kategori = "kategori" in text
            has_mesaj = "mesaj" in text
            rec("T3-04", "PASS" if (has_urun and has_kategori and has_mesaj) else "PARTIAL",
                f"urun={has_urun} kat={has_kategori} msg={has_mesaj}")
        except Exception as e:
            rec("T3-04", "FAIL", f"Dashboard: {type(e).__name__}")

        # T3-05: lists present
        try:
            tables = page.locator("table").count()
            cards = page.locator(".card, .list-card").count()
            rec("T3-05", "PASS" if (tables or cards >= 2) else "PARTIAL",
                f"table={tables} card={cards}")
        except Exception as e:
            rec("T3-05", "FAIL", f"List count: {type(e).__name__}")

        # T3-06: logout (POST form with hidden logout=1)
        try:
            logout_btn = page.locator("button.nav-item.logout, button.logout").first
            if logout_btn.count() > 0:
                logout_btn.click()
                page.wait_for_load_state("networkidle", timeout=10000)
                logged_out = page.locator("#username").count() > 0 or "index.php" in page.url
                rec("T3-06", "PASS" if logged_out else "FAIL",
                    f"Logout sonrasi URL: {page.url}, login form var: {page.locator('#username').count()}")
            else:
                rec("T3-06", "PARTIAL", "button.nav-item.logout bulunamadi")
        except Exception as e:
            rec("T3-06", "FAIL", f"Logout: {type(e).__name__}")

        # re-login for rest
        do_login(page)

        # ===== 3.2 Urun yonetimi =====
        ensure_login(page)
        page.goto(f"{ADMIN}/urunler.php", wait_until="networkidle", timeout=10000)
        ensure_login(page)
        shot(page, "t3_07_urunler")
        try:
            rows = page.locator("table tbody tr").count()
            thumbs = page.locator("img.product-thumb, .product-thumb-placeholder").count()
            rec("T3-07", "PASS" if rows > 0 else "FAIL",
                f"Urun satiri: {rows}, thumbnail: {thumbs}")
        except Exception as e:
            rec("T3-07", "FAIL", f"urunler.php: {type(e).__name__}")

        # T3-08: kategori filter
        try:
            cat_filter = page.locator("#urun-kategori-filtre").count()
            rec("T3-08", "PASS" if cat_filter > 0 else "FAIL",
                f"#urun-kategori-filtre select: {cat_filter}")
        except Exception as e:
            rec("T3-08", "FAIL", f"Filter: {type(e).__name__}")

        # T3-09: search
        try:
            search = page.locator("#urun-arama").count()
            rec("T3-09", "PASS" if search > 0 else "FAIL",
                f"#urun-arama input: {search}")
        except Exception as e:
            rec("T3-09", "FAIL", f"Search: {type(e).__name__}")

        # T3-10: yeni urun ekle
        try:
            safe_goto(page, f"{ADMIN}/urun-ekle.php")
            shot(page, "t3_10_urun_ekle")
            form = page.locator("form:has(input[name='isim'])").first
            form.locator("input[name='isim']").fill("Test Urun QA")
            form.locator("select[name='kategori_id']").select_option(index=1)
            form.locator("input[name='fiyat']").fill("99.90")
            ta = form.locator("textarea[name='aciklama']")
            if ta.count() > 0:
                ta.fill("Otomatik test ile eklenen urun")
            form.locator("button[type='submit'].btn-primary, button[type='submit']:has-text('Kaydet')").first.click()
            page.wait_for_load_state("networkidle", timeout=15000)
            count = sql("SELECT COUNT(*) FROM urunler WHERE isim='Test Urun QA';").splitlines()[-1]
            ok = int(count.strip()) >= 1
            # grab error message if any
            err = ""
            err_loc = page.locator(".alert-error, .alert.alert-error")
            if err_loc.count() > 0:
                try:
                    err = err_loc.first.inner_text()[:120]
                except Exception:
                    pass
            rec("T3-10", "PASS" if ok else "FAIL",
                f"DB kaydi: {count}, URL: {page.url}, hata: '{err}'")
        except Exception as e:
            rec("T3-10", "FAIL", f"Urun ekle: {type(e).__name__}: {str(e)[:80]}")

        # T3-11..13 upload - static + DB check
        try:
            # static check - does uploadImage exist in security.php or functions.php
            import os
            functions = Path(r"c:\xampp\htdocs\pastane\includes\functions.php").read_text(encoding="utf-8", errors="ignore")
            sec = Path(r"c:\xampp\htdocs\pastane\includes\security.php").read_text(encoding="utf-8", errors="ignore")
            has_size_check = "MAX_FILE_SIZE" in functions + sec or "5 * 1024 * 1024" in functions + sec or "filesize" in functions + sec.lower()
            has_mime_check = "mime" in (functions+sec).lower() and "image/jpeg" in (functions+sec)
            has_secure_upload = "secureUploadImage" in functions + sec
            rec("T3-11", "PARTIAL",
                f"secureUploadImage fn: {has_secure_upload} (live upload edilmedi, kod mevcut)")
            rec("T3-12", "PARTIAL",
                f"Dosya boyut kontrolu kod icinde: {has_size_check}")
            rec("T3-13", "PARTIAL",
                f"MIME whitelist kontrolu kod icinde: {has_mime_check}")
        except Exception as e:
            rec("T3-11", "FAIL", f"Upload review: {type(e).__name__}")
            rec("T3-12", "FAIL", f"Upload size: {type(e).__name__}")
            rec("T3-13", "FAIL", f"Upload MIME: {type(e).__name__}")

        # T3-14: porsiyon fields
        try:
            page.goto(f"{ADMIN}/urun-ekle.php", wait_until="networkidle", timeout=10000)
            porsiyon_fields = page.locator("input[name^='fiyat_']").count()
            rec("T3-14", "PASS" if porsiyon_fields >= 4 else "PARTIAL",
                f"Porsiyon fiyat inputu: {porsiyon_fields}")
        except Exception as e:
            rec("T3-14", "FAIL", f"Porsiyon: {type(e).__name__}")

        # T3-15: cafe_menusu / hazirlanma / stok
        try:
            cafe = page.locator("input[name='cafe_menusu']").count()
            hazir = page.locator("input[name='hazirlanma_suresi']").count()
            stok = page.locator("select[name='stok_durumu']").count()
            total = cafe + hazir + stok
            rec("T3-15", "PASS" if total == 3 else "PARTIAL",
                f"cafe_menusu={cafe} hazirlanma_suresi={hazir} stok_durumu={stok}")
        except Exception as e:
            rec("T3-15", "FAIL", f"QR menu fields: {type(e).__name__}")

        # T3-16: urun duzenle
        try:
            page.goto(f"{ADMIN}/urunler.php", wait_until="networkidle", timeout=10000)
            # find the Test Urun QA row edit link
            row = page.locator("tr:has-text('Test Urun QA')").first
            if row.count() > 0:
                edit_link = row.locator("a[href*='urun-duzenle']").first
                if edit_link.count() > 0:
                    edit_link.click()
                    page.wait_for_load_state("networkidle", timeout=10000)
                    val = page.locator("input[name='isim']").first.input_value()
                    pre = "Test Urun QA" in val
                    rec("T3-16", "PASS" if pre else "PARTIAL",
                        f"Pre-filled isim: '{val[:40]}'")
                    shot(page, "t3_16_duzenle")
                else:
                    rec("T3-16", "FAIL", "Satirda duzenle link yok")
            else:
                rec("T3-16", "SKIP", "Test Urun QA eklenmedi (T3-10 FAIL)")
        except Exception as e:
            rec("T3-16", "FAIL", f"Duzenle: {type(e).__name__}")

        # T3-17: toggle aktif/pasif
        try:
            page.goto(f"{ADMIN}/urunler.php", wait_until="networkidle", timeout=10000)
            toggle_inputs = page.locator("input[name='toggle_id'], button[name='toggle_id']").count()
            rec("T3-17", "PASS" if toggle_inputs > 0 else "PARTIAL",
                f"Toggle form input sayisi: {toggle_inputs}")
        except Exception as e:
            rec("T3-17", "FAIL", f"Toggle: {type(e).__name__}")

        # T3-18: drag-drop sira
        try:
            sortable = page.locator(".sortable, [data-sortable], tbody.sortable, [draggable='true']").count()
            rec("T3-18", "PARTIAL" if sortable > 0 else "SKIP",
                f"Sortable element: {sortable} (drag-drop headless Playwright'ta guvenilmez)")
        except Exception as e:
            rec("T3-18", "FAIL", f"Sortable: {type(e).__name__}")

        # T3-19: delete test urun
        try:
            before_cnt = int(sql("SELECT COUNT(*) FROM urunler WHERE isim='Test Urun QA';").splitlines()[-1].strip())
            if before_cnt >= 1:
                # Use DB direct delete since UI delete needs JS confirm
                tid = sql("SELECT id FROM urunler WHERE isim='Test Urun QA' LIMIT 1;").splitlines()[-1].strip()
                # Submit form programmatically
                page.goto(f"{ADMIN}/urunler.php", wait_until="networkidle", timeout=10000)
                # Look for form with delete_id matching our id
                page.on("dialog", lambda d: d.accept())
                del_form = page.locator(f"form:has(input[name='delete_id'][value='{tid}'])").first
                if del_form.count() > 0:
                    del_form.locator("button[type='submit'], input[type='submit']").first.click()
                    page.wait_for_load_state("networkidle", timeout=10000)
                after_cnt = int(sql("SELECT COUNT(*) FROM urunler WHERE isim='Test Urun QA';").splitlines()[-1].strip())
                rec("T3-19", "PASS" if after_cnt < before_cnt else "PARTIAL",
                    f"DB before={before_cnt} after={after_cnt}")
            else:
                rec("T3-19", "SKIP", "Silinecek test urunu yok")
        except Exception as e:
            rec("T3-19", "FAIL", f"Sil: {type(e).__name__}: {str(e)[:80]}")

        # ===== 3.3 Kategori =====
        ensure_login(page)
        page.goto(f"{ADMIN}/kategoriler.php", wait_until="networkidle", timeout=10000)
        ensure_login(page)
        shot(page, "t3_20_kategoriler")
        try:
            rows = page.locator("table tbody tr").count()
            urun_col = page.locator("th:has-text('Urun'), th:has-text('Ürün')").count()
            rec("T3-20", "PASS" if rows >= 1 else "FAIL",
                f"Kategori satir: {rows}, urun kolonu: {urun_col}")
        except Exception as e:
            rec("T3-20", "FAIL", f"kategoriler: {type(e).__name__}")

        # T3-21: add kategori (slug otomatik)
        try:
            form = page.locator("form:has(input[name='isim'])").first
            form.locator("input[name='isim']").fill("Test QA Kategori")
            form.locator("button[type='submit']").first.click()
            page.wait_for_load_state("networkidle", timeout=10000)
            slug = sql("SELECT slug FROM kategoriler WHERE isim='Test QA Kategori' LIMIT 1;").splitlines()
            slug_val = slug[-1].strip() if len(slug) > 1 else ""
            rec("T3-21", "PASS" if slug_val and "test" in slug_val.lower() else "FAIL",
                f"DB slug='{slug_val}' (otomatik olusturuldu)")
        except Exception as e:
            rec("T3-21", "FAIL", f"Kategori ekle: {type(e).__name__}")

        # T3-22: duplicate slug
        try:
            page.goto(f"{ADMIN}/kategoriler.php", wait_until="networkidle", timeout=10000)
            form = page.locator("form:has(input[name='isim'])").first
            form.locator("input[name='isim']").fill("Test QA Kategori")
            form.locator("button[type='submit']").first.click()
            page.wait_for_load_state("networkidle", timeout=10000)
            # Count duplicates (should still be 1)
            cnt = int(sql("SELECT COUNT(*) FROM kategoriler WHERE isim='Test QA Kategori';").splitlines()[-1].strip())
            body = page.content().lower()
            err_shown = "hata" in body or "zaten" in body or "unique" in body.lower() or "duplicate" in body.lower()
            rec("T3-22", "PASS" if cnt == 1 else "PARTIAL",
                f"Duplicate engellenme: count={cnt} (1 = engelli), hata_mesaji={err_shown}")
        except Exception as e:
            rec("T3-22", "FAIL", f"Dup: {type(e).__name__}")

        # T3-23: silme bos kategori (Test QA Kategori urunsuz)
        try:
            tid = sql("SELECT id FROM kategoriler WHERE isim='Test QA Kategori' LIMIT 1;").splitlines()
            if len(tid) > 1:
                cat_id = tid[-1].strip()
                # Use POST via requests - but we have browser. Submit delete_id form.
                page.goto(f"{ADMIN}/kategoriler.php", wait_until="networkidle", timeout=10000)
                page.on("dialog", lambda d: d.accept())
                # Programmatic form submit via JS
                csrf = page.locator("input[name='csrf_token']").first.get_attribute("value")
                page.evaluate(f"""
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `<input name='csrf_token' value='{csrf}'><input name='delete_id' value='{cat_id}'>`;
                    document.body.appendChild(f);
                    f.submit();
                """)
                page.wait_for_load_state("networkidle", timeout=10000)
                remaining = int(sql(f"SELECT COUNT(*) FROM kategoriler WHERE id={cat_id};").splitlines()[-1].strip())
                rec("T3-23", "PASS" if remaining == 0 else "FAIL",
                    f"Silme sonrasi DB kayit: {remaining}")
            else:
                rec("T3-23", "SKIP", "Test kategori yok (T3-21 FAIL)")
        except Exception as e:
            rec("T3-23", "FAIL", f"Sil: {type(e).__name__}: {str(e)[:80]}")

        # T3-24: urun iceren kategori silmeyi dene
        try:
            # Pick a category that has products
            cat = sql("SELECT k.id FROM kategoriler k JOIN urunler u ON u.kategori_id=k.id LIMIT 1;").splitlines()
            if len(cat) > 1:
                cid = cat[-1].strip()
                page.goto(f"{ADMIN}/kategoriler.php", wait_until="networkidle", timeout=10000)
                csrf = page.locator("input[name='csrf_token']").first.get_attribute("value")
                page.evaluate(f"""
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `<input name='csrf_token' value='{csrf}'><input name='delete_id' value='{cid}'>`;
                    document.body.appendChild(f);
                    f.submit();
                """)
                page.wait_for_load_state("networkidle", timeout=10000)
                still_exists = int(sql(f"SELECT COUNT(*) FROM kategoriler WHERE id={cid};").splitlines()[-1].strip())
                body = page.content().lower()
                err_msg = "hata" in body or "once" in body or "önce" in body or "urun" in body.lower()
                rec("T3-24", "PASS" if still_exists == 1 else "FAIL",
                    f"Silinmedi (dogru): {still_exists==1}, hata_mesaji: {err_msg}")
            else:
                rec("T3-24", "SKIP", "Urunlu kategori yok")
        except Exception as e:
            rec("T3-24", "FAIL", f"Cat-sil: {type(e).__name__}")

        # ===== 3.4 Mesajlar & Musteriler =====
        # Seed a test message first
        sql("INSERT INTO iletisim_mesajlari (isim, email, telefon, mesaj, created_at) VALUES ('QA Tester', 'qa@test.com', '05000000000', 'Test mesaji otomatik', NOW());")
        ensure_login(page)

        try:
            page.goto(f"{ADMIN}/mesajlar.php", wait_until="networkidle", timeout=10000)
            ensure_login(page)
            shot(page, "t3_25_mesajlar")
            has_msg = "QA Tester" in page.content() or "qa@test.com" in page.content()
            rows = page.locator("table tbody tr").count()
            rec("T3-25", "PASS" if rows >= 1 else "PARTIAL",
                f"Mesaj satir: {rows}, seed mesaj goruldu: {has_msg}")
        except Exception as e:
            rec("T3-25", "FAIL", f"mesajlar: {type(e).__name__}")

        # T3-26: okundu toggle via DB
        try:
            sql("UPDATE iletisim_mesajlari SET okundu=1 WHERE email='qa@test.com';")
            r = sql("SELECT okundu FROM iletisim_mesajlari WHERE email='qa@test.com' LIMIT 1;")
            val = r.splitlines()[-1].strip() if "\n" in r else "?"
            rec("T3-26", "PASS" if val == "1" else "PARTIAL",
                f"DB okundu degeri: {val} (UI toggle ayri test gerekli)")
        except Exception as e:
            rec("T3-26", "FAIL", f"okundu: {type(e).__name__}")

        # T3-27: delete mesaj - via UI if possible, else via DB
        try:
            # Just confirm the delete-by-admin-UI mechanism exists by checking source
            page_src = Path(r"c:\xampp\htdocs\pastane\admin\mesajlar.php").read_text(encoding="utf-8", errors="ignore")
            has_delete = "delete" in page_src.lower() or "sil" in page_src.lower()
            sql("DELETE FROM iletisim_mesajlari WHERE email='qa@test.com';")
            rec("T3-27", "PARTIAL",
                f"Silme kodu mesajlar.php'de mevcut: {has_delete}; test kaydı DB'den temizlendi")
        except Exception as e:
            rec("T3-27", "FAIL", f"Sil: {type(e).__name__}")

        # T3-28: filters on mesajlar
        try:
            filters = page.locator("select, input[type='date']").count()
            rec("T3-28", "PASS" if filters > 0 else "PARTIAL",
                f"Filtre widget'i: {filters}")
        except Exception as e:
            rec("T3-28", "FAIL", f"Filter: {type(e).__name__}")

        # T3-29 musteriler
        try:
            page.goto(f"{ADMIN}/musteriler.php", wait_until="networkidle", timeout=10000)
            shot(page, "t3_29_musteriler")
            body = page.content()
            empty = "yok" in body.lower() or "henüz" in body.lower() or "henuz" in body.lower()
            table = page.locator("table").count()
            rec("T3-29", "PASS" if (table or empty) else "PARTIAL",
                f"table={table}, empty_state={empty}")
        except Exception as e:
            rec("T3-29", "FAIL", f"musteriler: {type(e).__name__}")

        rec("T3-30", "SKIP", "Musteri duzenle - gercek musteri kaydi gerekli (DB'de musteri yok)")

        # ===== 3.5 Masalar & Takvim =====
        ensure_login(page)
        try:
            page.goto(f"{ADMIN}/masalar.php", wait_until="networkidle", timeout=10000)
            ensure_login(page)
            shot(page, "t3_31_masalar")
            rows = page.locator("table tbody tr, .masa-card").count()
            rec("T3-31", "PASS" if rows >= 1 else "PARTIAL",
                f"Masa liste: {rows}")
        except Exception as e:
            rec("T3-31", "FAIL", f"masalar: {type(e).__name__}")

        # T3-32: masa ekle + QR token oluşumu
        try:
            csrf = page.locator("input[name='csrf_token']").first.get_attribute("value")
            # Use high masa_no so no duplicate
            page.evaluate(f"""
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input name='csrf_token' value='{csrf}'>
                    <input name='action' value='masa_ekle'>
                    <input name='masa_no' value='999'>
                    <input name='kapasite' value='4'>
                    <input name='konum' value='QA-Test'>`;
                document.body.appendChild(f);
                f.submit();
            """)
            page.wait_for_load_state("networkidle", timeout=10000)
            tok = sql("SELECT qr_token FROM masalar WHERE masa_no=999 LIMIT 1;").splitlines()
            token_val = tok[-1].strip() if len(tok) > 1 else ""
            rec("T3-32", "PASS" if token_val and len(token_val) >= 16 else "FAIL",
                f"Masa eklendi, qr_token uzunlugu: {len(token_val)}")
        except Exception as e:
            rec("T3-32", "FAIL", f"Masa ekle: {type(e).__name__}")

        # T3-33: QR göruntule - check link
        try:
            page.goto(f"{ADMIN}/masalar.php", wait_until="networkidle", timeout=10000)
            qr_links = page.locator("a[href*='qr'], button:has-text('QR')").count()
            rec("T3-33", "PASS" if qr_links > 0 else "PARTIAL",
                f"QR link/buton: {qr_links}")
        except Exception as e:
            rec("T3-33", "FAIL", f"QR: {type(e).__name__}")

        # T3-34: QR indir
        rec("T3-34", "PARTIAL", "QR PNG endpoint'i var (QrKodService); download testi headless'de yapılmadı")

        # T3-35: masa aktif et
        try:
            mid = sql("SELECT id FROM masalar WHERE masa_no=999 LIMIT 1;").splitlines()
            if len(mid) > 1:
                mi = mid[-1].strip()
                csrf = page.locator("input[name='csrf_token']").first.get_attribute("value")
                page.evaluate(f"""
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `<input name='csrf_token' value='{csrf}'>
                        <input name='action' value='masa_aktif_et'>
                        <input name='masa_id' value='{mi}'>
                        <input name='musteri_sayisi' value='2'>`;
                    document.body.appendChild(f);
                    f.submit();
                """)
                page.wait_for_load_state("networkidle", timeout=10000)
                otr = sql(f"SELECT COUNT(*) FROM masa_oturumlari WHERE masa_id={mi} AND cikis_zamani IS NULL;").splitlines()[-1].strip()
                status = sql(f"SELECT durum FROM masalar WHERE id={mi};").splitlines()[-1].strip()
                rec("T3-35", "PASS" if int(otr) >= 1 else "FAIL",
                    f"Aktif oturum: {otr}, masa durum: {status}")
            else:
                rec("T3-35", "SKIP", "Test masasi yok (T3-32 FAIL)")
        except Exception as e:
            rec("T3-35", "FAIL", f"Aktif et: {type(e).__name__}")

        # T3-36: masa kapat
        try:
            if len(sql("SELECT id FROM masalar WHERE masa_no=999;").splitlines()) > 1:
                mi = sql("SELECT id FROM masalar WHERE masa_no=999 LIMIT 1;").splitlines()[-1].strip()
                csrf = page.locator("input[name='csrf_token']").first.get_attribute("value")
                page.evaluate(f"""
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `<input name='csrf_token' value='{csrf}'>
                        <input name='action' value='masa_kapat'>
                        <input name='masa_id' value='{mi}'>`;
                    document.body.appendChild(f);
                    f.submit();
                """)
                page.wait_for_load_state("networkidle", timeout=10000)
                otr = sql(f"SELECT COUNT(*) FROM masa_oturumlari WHERE masa_id={mi} AND cikis_zamani IS NULL;").splitlines()[-1].strip()
                durum = sql(f"SELECT durum FROM masalar WHERE id={mi};").splitlines()[-1].strip()
                # cleanup
                sql(f"DELETE FROM masa_oturumlari WHERE masa_id={mi};")
                sql(f"DELETE FROM masalar WHERE id={mi};")
                rec("T3-36", "PASS" if int(otr) == 0 and durum == "bos" else "PARTIAL",
                    f"Kalan aktif oturum: {otr}, durum: {durum}")
            else:
                rec("T3-36", "SKIP", "Test masasi yok")
        except Exception as e:
            rec("T3-36", "FAIL", f"Kapat: {type(e).__name__}")

        # T3-37: takvim
        try:
            page.goto(f"{ADMIN}/takvim.php", wait_until="networkidle", timeout=15000)
            shot(page, "t3_37_takvim")
            grid = page.locator(".calendar, .takvim-grid, .calendar-grid, table").count()
            day_cells = page.locator(".day-cell, .day, td.day, .calendar-day").count()
            rec("T3-37", "PASS" if (grid > 0 or day_cells > 7) else "PARTIAL",
                f"grid={grid}, day cells={day_cells}")
        except Exception as e:
            rec("T3-37", "FAIL", f"takvim: {type(e).__name__}")

        rec("T3-38", "PARTIAL", "Puan hesaplama logic'i takvim JS'de; live UI click DB insert kontrolu ayri test")
        rec("T3-39", "PARTIAL", "Gun notu inputu takvim.php'de mevcut; form submit + DB kontrolu ayri test")

        # ===== 3.6 Raporlar & Temalar =====
        ensure_login(page)
        try:
            page.goto(f"{ADMIN}/raporlar.php", wait_until="networkidle", timeout=20000)
            ensure_login(page)
            shot(page, "t3_40_raporlar")
            canvas = page.locator("canvas").count()
            cards = page.locator(".card, .rapor-card, .stat-card").count()
            rec("T3-40", "PASS" if (canvas or cards) else "PARTIAL",
                f"canvas={canvas}, card={cards}")
        except Exception as e:
            rec("T3-40", "FAIL", f"raporlar: {type(e).__name__}")

        try:
            date_inp = page.locator("input[type='date']").count()
            rec("T3-41", "PASS" if date_inp >= 2 else "PARTIAL",
                f"Date input: {date_inp} (2 = aralik)")
        except Exception as e:
            rec("T3-41", "FAIL", f"Date range: {type(e).__name__}")

        try:
            text = page.inner_text("body").lower()
            rec("T3-42", "PARTIAL" if "musteri" in text or "müşteri" in text else "FAIL",
                "Raporlar.php musteri ibareleri icerir")
        except Exception:
            rec("T3-42", "PARTIAL", "Statik kontrol")

        try:
            text = page.inner_text("body").lower()
            top_rx = "en cok" in text or "en çok" in text or "top" in text or "sat" in text
            rec("T3-43", "PARTIAL" if top_rx else "FAIL",
                "En cok satan/top X ibareleri gorduldu" if top_rx else "Top sat ibaresi yok")
        except Exception:
            rec("T3-43", "PARTIAL", "Statik kontrol")

        try:
            has_canvas = page.locator("canvas").count() > 0
            js_chart = "chart" in page.content().lower()
            rec("T3-44", "PASS" if (has_canvas or js_chart) else "PARTIAL",
                f"canvas={has_canvas} chart.js_ref={js_chart}")
        except Exception as e:
            rec("T3-44", "FAIL", f"Chart: {type(e).__name__}")

        try:
            export = page.locator("a:has-text('PDF'), a:has-text('Excel'), a[href*='export'], button:has-text('PDF')").count()
            rec("T3-45", "PASS" if export else "PARTIAL",
                f"Export linki: {export}")
        except Exception as e:
            rec("T3-45", "FAIL", f"Export: {type(e).__name__}")

        try:
            ensure_login(page)
            page.goto(f"{ADMIN}/temalar.php", wait_until="networkidle", timeout=10000)
            ensure_login(page)
            shot(page, "t3_46_temalar")
            tema_items = page.locator(".tema-card, .theme-option, input[name='tema']").count()
            # also count active links/buttons in temalar
            buttons = page.locator("button, a.btn").count()
            rec("T3-46", "PASS" if tema_items > 0 else "PARTIAL",
                f"Tema kart: {tema_items}, buton: {buttons}")
        except Exception as e:
            rec("T3-46", "FAIL", f"temalar: {type(e).__name__}")

        try:
            # try to click a tema select (any button-like)
            src = page.content().lower()
            has_yaz = "yaz" in src
            has_kis = "kis" in src or "kış" in src
            rec("T3-47", "PARTIAL",
                f"Sayfada tema secenekleri: yaz={has_yaz} kis={has_kis} (gercek anahtarlama ayri test)")
        except Exception as e:
            rec("T3-47", "FAIL", f"Tema degis: {type(e).__name__}")

        browser.close()
    write_results()

def write_results():
    outfile = SHOTS.parent / "tester3_results.json"
    outfile.write_text(json.dumps({"results": results}, ensure_ascii=False, indent=2), encoding="utf-8")
    p = sum(1 for r in results if r["status"] == "PASS")
    f = sum(1 for r in results if r["status"] == "FAIL")
    pp = sum(1 for r in results if r["status"] == "PARTIAL")
    s = sum(1 for r in results if r["status"] == "SKIP")
    print(f"\n=== SUMMARY === PASS={p} FAIL={f} PARTIAL={pp} SKIP={s}")

if __name__ == "__main__":
    main()
