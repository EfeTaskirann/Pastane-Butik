from playwright.sync_api import sync_playwright
import os

SCREENSHOTS_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_URL = 'http://localhost/pastane'

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={'width': 1440, 'height': 900})

    # 1. Default theme
    print("1. Default theme screenshots...")
    page.goto(f'{BASE_URL}/', wait_until='networkidle')
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '01_default_hero.png'))

    page.evaluate('window.scrollTo(0, document.querySelector("#products")?.offsetTop || 800)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '02_default_products.png'))

    page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '03_default_footer.png'))

    # 2. Login to admin
    print("2. Admin login...")
    page.goto(f'{BASE_URL}/admin/', wait_until='networkidle')
    username_input = page.locator('input[type="text"]').first
    password_input = page.locator('input[type="password"]').first
    if username_input.is_visible() and password_input.is_visible():
        username_input.fill('admin')
        password_input.fill('admin123')
        page.locator('button[type="submit"], input[type="submit"]').first.click()
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(500)

    # 3. Temalar page
    print("3. Temalar page...")
    page.goto(f'{BASE_URL}/admin/temalar.php', wait_until='networkidle')
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '04_admin_temalar.png'), full_page=True)

    # 4. Activate WINTER theme via JS form submit
    print("4. Activating winter theme...")
    page.evaluate("""
        const forms = document.querySelectorAll('form');
        for (const form of forms) {
            const action = form.querySelector('input[name="action"]');
            const temaId = form.querySelector('input[name="tema_id"]');
            if (action && action.value === 'activate' && temaId && temaId.value === '1') {
                form.submit();
                break;
            }
        }
    """)
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(500)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '05_admin_kis_aktif.png'), full_page=True)

    # 5. Winter theme on site
    print("5. Winter theme on site...")
    page.goto(f'{BASE_URL}/', wait_until='networkidle')
    page.wait_for_timeout(2500)
    body_attr = page.evaluate('document.body.getAttribute("data-theme")')
    print(f"   data-theme: {body_attr}")
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '06_kis_hero.png'))

    page.evaluate('window.scrollTo(0, document.querySelector("#products")?.offsetTop || 800)')
    page.wait_for_timeout(1500)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '07_kis_products.png'))

    page.evaluate('window.scrollTo(0, document.querySelector("#contact")?.offsetTop || 3000)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '08_kis_contact.png'))

    page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '09_kis_footer.png'))

    # 6. Activate SUMMER theme
    print("6. Activating summer theme...")
    page.goto(f'{BASE_URL}/admin/temalar.php', wait_until='networkidle')
    page.evaluate("""
        const forms = document.querySelectorAll('form');
        for (const form of forms) {
            const action = form.querySelector('input[name="action"]');
            const temaId = form.querySelector('input[name="tema_id"]');
            if (action && action.value === 'activate' && temaId && temaId.value === '2') {
                form.submit();
                break;
            }
        }
    """)
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(500)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '10_admin_yaz_aktif.png'), full_page=True)

    # 7. Summer theme on site
    print("7. Summer theme on site...")
    page.goto(f'{BASE_URL}/', wait_until='networkidle')
    page.wait_for_timeout(2500)
    body_attr = page.evaluate('document.body.getAttribute("data-theme")')
    print(f"   data-theme: {body_attr}")
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '11_yaz_hero.png'))

    page.evaluate('window.scrollTo(0, document.querySelector("#products")?.offsetTop || 800)')
    page.wait_for_timeout(1500)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '12_yaz_products.png'))

    page.evaluate('window.scrollTo(0, document.querySelector("#contact")?.offsetTop || 3000)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '13_yaz_contact.png'))

    page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(1000)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '14_yaz_footer.png'))

    # 8. Deactivate
    print("8. Deactivating themes...")
    page.goto(f'{BASE_URL}/admin/temalar.php', wait_until='networkidle')
    page.evaluate("""
        const forms = document.querySelectorAll('form');
        for (const form of forms) {
            const action = form.querySelector('input[name="action"]');
            if (action && action.value === 'deactivate') {
                form.submit();
                break;
            }
        }
    """)
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(500)
    page.screenshot(path=os.path.join(SCREENSHOTS_DIR, '15_admin_final.png'), full_page=True)

    browser.close()
    print("\nDone! All screenshots saved.")
