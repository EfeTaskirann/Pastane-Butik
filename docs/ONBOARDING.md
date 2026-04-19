# Pastane — Developer Onboarding

Yeni ekip arkadaşı için 0-dan 1'e rehber. Hedef: **30 dakika içinde yerel ortamı
kur, admin paneline giriş yap, ilk feature branch'i aç**.

---

## 0. Özet (TL;DR)

```bash
git clone git@github.com:<org>/pastane.git
cd pastane
make setup           # composer + npm + .env + migrate
make serve           # localhost:8000 aç
# → admin: http://localhost:8000/admin/  (admin / admin123)
```

Toplam süre: **~20-30 dk** (bağımlılık download'ı dahil).

---

## 1. Prerequisites

| Araç                 | Minimum Sürüm | Kontrol                             |
|----------------------|---------------|-------------------------------------|
| PHP                  | 8.2+          | `php -v`                            |
| Composer             | 2.7+          | `composer --version`                |
| Node.js              | 20.0+         | `node -v`                           |
| NPM                  | 10.0+         | `npm -v`                            |
| MySQL / MariaDB      | 8.0 / 10.11+  | `mysql --version`                   |
| Git                  | 2.40+         | `git --version`                     |
| Docker (opsiyonel)   | 24+           | `docker --version`                  |
| Make                 | 4.0+          | `make --version`                    |

**Windows'ta:**
- XAMPP (PHP 8.2 + Apache + MySQL): https://www.apachefriends.org/tr/
- Git for Windows (Git Bash dahil): https://git-scm.com/download/win
- Node.js LTS: https://nodejs.org/tr
- Make: `choco install make` (Chocolatey) veya Git Bash ile birlikte gelir

**macOS'ta:**
```bash
brew install php@8.2 composer node@20 mysql make
```

**Linux'ta (Ubuntu 22.04):**
```bash
sudo apt install php8.2 php8.2-{mbstring,pdo-mysql,curl,xml,gd,zip} \
  composer nodejs npm mysql-server make git
```

---

## 2. Hızlı Başlangıç (10 Adım)

### 1. Repo'yu klonla
```bash
git clone git@github.com:<org>/pastane.git
cd pastane
```

### 2. Composer bağımlılıkları
```bash
composer install
```
Başarısız olursa PHP eklentilerini kontrol et: `mbstring`, `pdo_mysql`, `json`, `gd`.

### 3. NPM bağımlılıkları
```bash
npm ci
```

### 4. `.env` oluştur
```bash
cp .env.example .env
```
**Kritik değerleri güncelle:**
- `APP_URL=http://localhost:8000`
- `DB_DATABASE=pastane`
- `DB_USERNAME=root`
- `DB_PASSWORD=` (XAMPP default boş)
- `APP_KEY=` → `php -r "echo bin2hex(random_bytes(32));"` çıktısını yapıştır

### 5. Veritabanı oluştur
```sql
-- phpMyAdmin veya CLI
CREATE DATABASE pastane CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Migration + seed
```bash
make migrate
# veya:
php bin/migrate
mysql -u root pastane < database.sql
```

### 7. Storage klasörlerini hazırla
```bash
mkdir -p storage/{cache,logs,backups,sessions} uploads
```
Windows'ta otomatik oluşur; Linux/macOS'ta yazma izni gerekebilir:
```bash
chmod -R 775 storage uploads
```

### 8. Frontend asset build (opsiyonel dev için)
```bash
npm run build        # production
# veya
npm run dev          # Vite hot reload
```

### 9. PHP server başlat
```bash
make serve           # localhost:8000
# veya
php -S localhost:8000 -t .
```

### 10. Admin paneline giriş yap
Tarayıcıda:
- **Ana site:** http://localhost:8000/
- **Admin:** http://localhost:8000/admin/ (default: `admin` / `admin123` — **production'da derhal değiştir**)
- **Health:** http://localhost:8000/api/health.php
- **QR Menü:** http://localhost:8000/menu/

---

## 3. Klasör Yapısı

```
pastane/
├── admin/                   # Admin panel sayfaları (PHP)
│   ├── ayarlar/             # Settings UI (site/mail/sms/2fa/backup)
│   ├── includes/            # Shared admin auth + header/footer
│   └── *.php                # dashboard, urunler, masalar, vs.
├── api/                     # Public API endpoint'leri
│   ├── health.php           # /api/health (k8s probe'lar)
│   ├── metrics.php          # Prometheus metrics
│   └── v1/                  # Versiyonlu REST API
│       ├── index.php        # Router
│       └── routes/          # Endpoint handler'ları
├── assets/                  # Frontend kaynakları
│   ├── css/                 # CSS (+ components/, themes/)
│   └── js/                  # JS (+ components/, themes/)
├── bin/                     # CLI araçları
│   ├── cron/                # db-backup, log-rotate, backup-monitor
│   └── migrate              # Migration runner
├── config/                  # Config dosyaları (mail, sms, odeme, permissions)
├── database/
│   └── migrations/          # SQL migration'lar (sıralı dosyalar)
├── database.sql             # Full schema + seed (dev bootstrap)
├── docker/                  # Docker compose overrides + helper scripts
├── docs/                    # Teknik doküman (bu klasör)
├── includes/                # Legacy includes + security helpers
│   ├── bootstrap.php        # App bootstrap (tek giriş noktası)
│   ├── Cache.php, Logger.php, Sentry.php, HealthCheck.php
│   └── helpers.php          # Global helper fonksiyonlar
├── menu/                    # QR menü (müşteri-facing) sayfaları
├── public/                  # Built asset output (vite dist)
├── src/                     # PSR-4 autoload, modern OOP kod
│   ├── Controllers/Admin/   # Admin controller'ları (pilot: UrunController)
│   ├── Repositories/        # DB access katmanı
│   ├── Services/            # Business logic
│   └── Validators/          # Request validation
├── storage/                 # Runtime artefact (.gitignore'da)
│   ├── cache/, logs/, sessions/, backups/
├── tasks/                   # Agent task rehberleri (CLAUDE.md ile entegre)
├── tests/
│   ├── Unit/                # İzole birim testler
│   ├── Feature/             # Feature testler
│   ├── Integration/         # DB'li integration testler
│   └── e2e/                 # Playwright E2E testler
├── uploads/                 # Kullanıcı yüklemeleri (ürün resmi vs.)
├── vendor/                  # Composer packages (.gitignore)
├── node_modules/            # NPM packages (.gitignore)
├── views/                   # Admin controller view'lar (Sprint 2 pilot)
├── .env.example             # Environment template
├── .php-cs-fixer.dist.php   # PSR-12 + proje stil kuralları
├── captainhook.json         # Git hooks (pre-commit + commit-msg)
├── composer.json, composer.lock
├── package.json, package-lock.json
├── phpstan.neon, phpcs.xml, phpunit.xml
├── playwright.config.ts     # E2E konfig
├── Makefile                 # Developer komutları
└── CLAUDE.md                # Proje hafızası (agent instructions)
```

---

## 4. Git Workflow

### Branch stratejisi

- `main` — production-ready, korumalı. Sadece PR ile merge.
- `develop` — entegrasyon branch'i (opsiyonel).
- `feature/<kısa-açıklama>` — yeni özellik
- `fix/<kısa-açıklama>` — bug fix
- `refactor/<kısa-açıklama>` — davranış değişmeden yapı değişikliği

### PR gereksinimleri

1. **CI yeşil:** lint + test + build + security audit
2. **PSR-12 uyumlu:** `make lint` ve `composer cs-check` clean
3. **Yeni kod → yeni test:** PHPUnit veya Playwright E2E
4. **CHANGELOG / CLAUDE.md güncel:** öğrenilen ders veya mimari değişiklik varsa ekle
5. **En az 1 reviewer onayı**

### Commit mesaj formatı

Conventional Commits: `<type>(<scope>)?: <subject>`

```
feat(masa): qr kod indirme butonu eklendi
fix(auth): rate limit reset timestamp hatasi
docs(onboarding): setup adimlari guncellendi
test(e2e): sipariş crud senaryolari eklendi
```

Detay: [`docs/PRE_COMMIT_SETUP.md`](./PRE_COMMIT_SETUP.md)

### Günlük akış

```bash
# Ana'dan güncel branch çek
git checkout main && git pull

# Feature branch aç
git checkout -b feature/kategori-import

# Çalış, test et
make test
make lint

# Commit (hook otomatik validate eder)
git add .
git commit -m "feat(kategori): csv import endpoint eklendi"

# Push + PR aç
git push -u origin feature/kategori-import
gh pr create --fill
```

---

## 5. Test Yazma Rehberi

### PHP Unit Test (tipik)
```php
// tests/Unit/Services/UrunServiceTest.php
namespace Pastane\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Pastane\Services\UrunService;

class UrunServiceTest extends TestCase
{
    public function testOlusturma(): void
    {
        $repo = $this->createMock(\Pastane\Repositories\UrunRepository::class);
        $repo->expects($this->once())
             ->method('create')
             ->willReturn(42);

        $service = new UrunService($repo);
        $id = $service->create(['isim' => 'Test', 'fiyat' => 25.5]);

        $this->assertSame(42, $id);
    }
}
```

```bash
make test              # hepsi
make test-unit         # sadece Unit suite
vendor/bin/phpunit --filter UrunServiceTest
```

### E2E Test (Playwright)
```typescript
// tests/e2e/XX-feature.spec.ts
import { test, expect } from './fixtures/admin-auth';

test('yeni ürün ekleme', async ({ adminPage }) => {
  await adminPage.goto('/admin/urun-ekle.php');
  await adminPage.locator('[name="isim"]').fill('Test Pasta');
  await adminPage.locator('[name="fiyat"]').fill('99.90');
  await adminPage.locator('form button[type="submit"]').click();
  await expect(adminPage).toHaveURL(/urunler\.php/);
});
```

```bash
make test-e2e                    # tüm tarayıcılar
npm run test:e2e -- --ui         # interaktif UI
```

**Pattern referansları:**
- Admin controller pattern: [`docs/ADMIN_CONTROLLER_PATTERN.md`](./ADMIN_CONTROLLER_PATTERN.md)
- CSP event binding: [`docs/CSP_EVENT_PATTERN.md`](./CSP_EVENT_PATTERN.md)
- Service mock pattern: `tests/Unit/Services/SiparisServiceTest.php`

---

## 6. Hata Ayıklama

### Log yolları
- `storage/logs/app.log` — uygulama hataları
- `storage/logs/cron-*.log` — cron job'lar
- `storage/logs/security.log` — güvenlik eventleri
- PHP error log: `xampp/php/logs/php_error_log` (XAMPP) veya systemd journal (Linux)

### Log seviyesini artır
`.env`:
```
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=debug
```

### Sentry (production)
`.env`:
```
SENTRY_DSN=https://<public-key>@o<org>.ingest.sentry.io/<project-id>
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1
```
Test: `php bin/test-sentry.php`

### Health endpoint
```bash
curl -s http://localhost:8000/api/health.php | jq
curl -s http://localhost:8000/api/health.php/live
curl -s http://localhost:8000/api/health.php/ready
```

### Aktivite log (admin panel)
http://localhost:8000/admin/activity-log.php — tüm kullanıcı eylemleri,
filtre + pagination + JSON detay.

### PHPStan / PHPCS hataları
```bash
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs --standard=PSR12 src/
vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## 7. Yaygın Sorun Giderme

| Sorun                                              | Çözüm                                                      |
|----------------------------------------------------|------------------------------------------------------------|
| `composer install` → permission denied             | `sudo chown -R $USER vendor/`                              |
| `npm ci` → EACCES                                  | `sudo chown -R $USER node_modules/`                        |
| Admin login → "500 Internal Error"                 | `storage/logs/app.log`'a bak; session klasörü izinleri     |
| `.env: command not found`                          | `cp .env.example .env` (dosya ismi `.env`)                 |
| `DB_PASSWORD` boş hata veriyor                     | `.env`'de tırnak koyma: `DB_PASSWORD=`                     |
| `8000 port zaten kullanımda`                       | `make serve SERVE_PORT=8001` veya process'i kill et        |
| Playwright `browser not found`                     | `npx playwright install --with-deps`                       |
| Migration fail: `Table already exists`             | `make migrate-fresh` (destructive, dev-only)               |
| `InvalidArgumentException: table not whitelisted`  | `includes/db.php` → `ALLOWED_TABLES` listesine ekle        |
| `CSRF verification failed`                         | `secureSessionStart()` çağrısı kontrol, session yolu       |
| `Pre-commit hook çok yavaş`                        | Hook sadece staged dosyayı analiz ediyor — normal          |
| CI'da CRLF false positive'ler                      | `.gitattributes` çalışıyor mu? `git add --renormalize .`   |
| `Playwright test PHP server başlamıyor`            | `lsof -i :8000` ile çakışma kontrol, `SERVE_PORT` değiştir |

---

## 8. Öğrenme Kaynakları

- **Proje dokümantasyonu:** `docs/` altındaki tüm `.md` dosyalar
- **CLAUDE.md:** Öğrenilen dersler + tekrarlanan hatalar (her PR öncesi oku)
- **API şeması:** `api/v1/openapi.yaml`
- **Staging setup:** `docs/STAGING_SETUP.md`
- **N+1 audit:** `docs/N1_AUDIT_SPRINT1.md`
- **Secret rotation:** `docs/SECRET_ROTATION_RUNBOOK.md`

### Dış referanslar
- PHP 8.2 manuali: https://www.php.net/manual/tr/
- PSR standartları: https://www.php-fig.org/psr/
- Playwright: https://playwright.dev/
- MySQL 8.0: https://dev.mysql.com/doc/refman/8.0/en/

---

## 9. İletişim

- **Issue:** GitHub Issues (template'leri kullan)
- **PR review:** `@code-owners` etiketi otomatik assign edilir
- **Acil:** takım Slack kanalı / proje yöneticisi
- **Güvenlik açığı:** `security@<domain>` (public issue açma)

---

## 10. İlk PR Checklist

- [ ] Lokal `make setup` çalıştı, ortam hazır
- [ ] `make lint` clean
- [ ] `make test` yeşil
- [ ] Yeni feature için test yazıldı
- [ ] Commit mesajı Conventional Commits uyumlu
- [ ] CLAUDE.md öğrenilen ders varsa eklendi
- [ ] Reviewer atanmış
- [ ] CI tüm job'lar yeşil

Hoş geldin! Sorularını çekinme sor — hiçbir soru aptalca değil.
