from playwright.sync_api import sync_playwright

console_errors = []
page_errors = []

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1280, "height": 800})

    page.on("console", lambda msg: console_errors.append(f"[{msg.type}] {msg.text}") if msg.type in ("error", "warning") else None)
    page.on("pageerror", lambda err: page_errors.append(str(err)))

    # Login
    page.goto("http://localhost/pastane/admin/", wait_until="networkidle")
    if page.locator("input[name='username']").count() > 0:
        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")
        page.wait_for_load_state("networkidle")

    # Navigate to masalar
    page.goto("http://localhost/pastane/admin/masalar.php", wait_until="networkidle")
    page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_masalar.png", full_page=True)
    print(f"URL: {page.url}")

    # Check for PHP errors
    body_text = page.inner_text("body")
    for keyword in ["Fatal", "Exception", "Warning", "Parse error", "Geçersiz tablo"]:
        if keyword in body_text:
            print(f"\n!!! PHP ERROR: found '{keyword}' in page !!!")
            # Print surrounding context
            idx = body_text.find(keyword)
            print(body_text[max(0,idx-100):idx+200])
            break

    # Check modal CSS
    modal = page.locator("#masaFormModal")
    print(f"\nModal element exists: {modal.count() > 0}")
    if modal.count() > 0:
        style = page.evaluate("() => { const el = document.getElementById('masaFormModal'); const cs = getComputedStyle(el); return { display: cs.display, visibility: cs.visibility, opacity: cs.opacity, zIndex: cs.zIndex }; }")
        print(f"Modal computed style BEFORE click: {style}")

    # Click first visible button with masaEkleModal onclick
    print("\n=== Clicking masaEkleModal button ===")
    page.evaluate("masaEkleModal()")
    page.wait_for_timeout(500)

    if modal.count() > 0:
        style_after = page.evaluate("() => { const el = document.getElementById('masaFormModal'); const cs = getComputedStyle(el); return { display: cs.display, visibility: cs.visibility, opacity: cs.opacity, zIndex: cs.zIndex, classList: el.className }; }")
        print(f"Modal computed style AFTER click: {style_after}")
        visible = modal.is_visible()
        print(f"Modal is_visible: {visible}")

    page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_after_click.png", full_page=True)

    # Try to fill form and submit
    if modal.is_visible():
        print("\n=== Modal is open, filling form ===")
        page.fill("#masa_no", "99")
        page.fill("#kapasite", "4")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_form_filled.png", full_page=True)
        page.click("#masaForm button[type='submit']")
        page.wait_for_load_state("networkidle")
        page.screenshot(path="c:/xampp/htdocs/pastane/tests/ss_after_submit.png", full_page=True)
        print(f"After submit URL: {page.url}")
        result_text = page.inner_text("body")
        if "basariyla" in result_text:
            print("SUCCESS: Masa eklendi!")
        elif "hata" in result_text.lower() or "error" in result_text.lower():
            print(f"ERROR in response: {result_text[:500]}")
    else:
        print("\n!!! Modal did NOT become visible after masaEkleModal() call !!!")

    if console_errors:
        print(f"\n=== CONSOLE ERRORS ({len(console_errors)}) ===")
        for err in console_errors:
            print(err)

    if page_errors:
        print(f"\n=== PAGE ERRORS ({len(page_errors)}) ===")
        for err in page_errors:
            print(err)

    browser.close()
