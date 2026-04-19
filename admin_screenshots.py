"""
Admin Panel Visual Review - Screenshot Automation
Captures screenshots of all admin pages for visual evaluation
"""
from playwright.sync_api import sync_playwright
import os
import sys

# Fix encoding for Windows console
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

SCREENSHOT_DIR = r"C:\xampp\htdocs\pastane\screenshots"
os.makedirs(SCREENSHOT_DIR, exist_ok=True)

BASE_URL = "http://localhost/pastane/admin"

PAGES = [
    ("login", "/index.php", False),
    ("dashboard", "/dashboard.php", True),
    ("urunler", "/urunler.php", True),
    ("kategoriler", "/kategoriler.php", True),
    ("takvim", "/takvim.php", True),
    ("raporlar", "/raporlar.php", True),
    ("musteriler", "/musteriler.php", True),
    ("mesajlar", "/mesajlar.php", True),
]

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)

    # Desktop viewport
    context = browser.new_context(viewport={"width": 1440, "height": 900})
    page = context.new_page()

    # 1) Login page screenshot first
    print("[DESKTOP] Login page...")
    page.goto(f"{BASE_URL}/index.php")
    page.wait_for_load_state("networkidle")
    page.screenshot(path=os.path.join(SCREENSHOT_DIR, "01_login_desktop.png"), full_page=True)

    # 2) Actually login
    print("[LOGIN] Logging in...")
    page.fill('input[name="username"]', 'admin')
    page.fill('input[name="password"]', 'admin123')
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)

    # Check if login was successful
    if "dashboard" in page.url or "index" not in page.url:
        print("[OK] Login successful! URL:", page.url)
    else:
        print("[FAIL] Login may have failed, current URL:", page.url)
        page.screenshot(path=os.path.join(SCREENSHOT_DIR, "login_result.png"), full_page=True)

    # 3) Desktop screenshots of all pages
    for name, path_url, needs_login in PAGES:
        if not needs_login:
            continue
        print(f"[DESKTOP] {name}...")
        page.goto(f"{BASE_URL}{path_url}")
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(500)
        page.screenshot(
            path=os.path.join(SCREENSHOT_DIR, f"02_{name}_desktop.png"),
            full_page=True
        )

    context.close()

    # 4) Mobile screenshots (iPhone viewport)
    print("\n[MOBILE] Starting mobile screenshots...")
    mobile_context = browser.new_context(viewport={"width": 375, "height": 812})
    mobile_page = mobile_context.new_page()

    # Login on mobile
    mobile_page.goto(f"{BASE_URL}/index.php")
    mobile_page.wait_for_load_state("networkidle")
    mobile_page.screenshot(path=os.path.join(SCREENSHOT_DIR, "03_login_mobile.png"), full_page=True)

    mobile_page.fill('input[name="username"]', 'admin')
    mobile_page.fill('input[name="password"]', 'admin123')
    mobile_page.click('button[type="submit"]')
    mobile_page.wait_for_load_state("networkidle")
    mobile_page.wait_for_timeout(1000)

    for name, path_url, needs_login in PAGES:
        if not needs_login:
            continue
        print(f"[MOBILE] {name}...")
        mobile_page.goto(f"{BASE_URL}{path_url}")
        mobile_page.wait_for_load_state("networkidle")
        mobile_page.wait_for_timeout(500)
        mobile_page.screenshot(
            path=os.path.join(SCREENSHOT_DIR, f"04_{name}_mobile.png"),
            full_page=True
        )

    mobile_context.close()

    # 5) Tablet screenshots
    print("\n[TABLET] Starting tablet screenshots...")
    tablet_context = browser.new_context(viewport={"width": 768, "height": 1024})
    tablet_page = tablet_context.new_page()

    tablet_page.goto(f"{BASE_URL}/index.php")
    tablet_page.wait_for_load_state("networkidle")

    tablet_page.fill('input[name="username"]', 'admin')
    tablet_page.fill('input[name="password"]', 'admin123')
    tablet_page.click('button[type="submit"]')
    tablet_page.wait_for_load_state("networkidle")
    tablet_page.wait_for_timeout(1000)

    # Capture key pages on tablet
    for name in ["dashboard", "takvim", "raporlar"]:
        print(f"[TABLET] {name}...")
        tablet_page.goto(f"{BASE_URL}/{name}.php")
        tablet_page.wait_for_load_state("networkidle")
        tablet_page.wait_for_timeout(500)
        tablet_page.screenshot(
            path=os.path.join(SCREENSHOT_DIR, f"05_{name}_tablet.png"),
            full_page=True
        )

    tablet_context.close()
    browser.close()

print("\n[DONE] All screenshots saved to:", SCREENSHOT_DIR)
print("Files:", sorted(os.listdir(SCREENSHOT_DIR)))
