# Pre-Commit Hooks Kurulumu (CaptainHook + PHP-CS-Fixer)

Bu rehber, projede commit öncesi otomatik kod kalite kontrollerini aktif etmek
için gereken adımları anlatır. Hedef: **lokal commit → PSR-12 uyumlu, debug
kodsuz, PHPStan clean, Conventional Commits başlığı**.

> Çalıştığı ortamlar: Windows (Git Bash / WSL / PowerShell), macOS, Linux.
> PHP 8.2+ gereklidir.

---

## 1. Tek Seferlik Kurulum

```bash
# 1. Dev bağımlılıkları yükle
composer require --dev friendsofphp/php-cs-fixer captainhook/captainhook captainhook/plugin-composer

# 2. CaptainHook hook'larını .git/hooks dizinine bağla
vendor/bin/captainhook install -f

# 3. Doğrula
vendor/bin/captainhook configuration:validate
```

Başarılı kurulumda `.git/hooks/pre-commit`, `.git/hooks/commit-msg`,
`.git/hooks/pre-push`, `.git/hooks/post-merge` dosyaları yaratılır ve
`captainhook.json`'u işaret eder.

> `captainhook/plugin-composer` eklendiğinde `composer install` sonrası hook'lar
> otomatik kurulur — yeni developer `git clone` + `composer install` yaparak
> sıfır config ile aktif hook'lara sahip olur.

---

## 2. Hook'ların Davranışı

| Hook                 | Eylem                                                          | Çıktı                                    |
|----------------------|----------------------------------------------------------------|------------------------------------------|
| `pre-commit`         | 1. `var_dump / print_r / dd / dump` taraması                   | Bulursa commit reddedilir                |
|                      | 2. `php -l` ile syntax kontrol (sadece staged .php)             | Hata varsa reddedilir                    |
|                      | 3. `phpcs --standard=PSR12` (sadece staged .php)                | Violation → reddedilir                   |
|                      | 4. `php-cs-fixer fix --dry-run --diff` (sadece staged .php)     | Fark varsa reddedilir                    |
|                      | 5. `phpstan analyse` (sadece staged .php)                       | Yeni error → reddedilir                  |
| `commit-msg`         | Conventional Commits regex kontrolü                             | `feat(scope): mesaj` şartı               |
| `pre-push`           | `phpunit --testsuite=Unit` (hızlı test suite)                   | Unit testler geçmeli                     |
| `post-merge`         | `composer.lock` / `package-lock.json` değiştiyse install        | Bağımlılıkları otomatik güncelle         |

---

## 3. Conventional Commits Formatı

```
<type>(<scope>)?: <subject>

body opsiyonel

footer opsiyonel (BREAKING CHANGE: ..., Refs #123)
```

**Type (zorunlu):**
- `feat` — yeni özellik
- `fix` — bug düzeltmesi
- `docs` — dokümantasyon
- `style` — format, boşluk (kod mantığı değişmez)
- `refactor` — davranış aynı, yapı değişti
- `perf` — performans iyileştirmesi
- `test` — test eklendi/düzeltildi
- `build` — build sistemi, bağımlılıklar
- `ci` — CI/CD pipeline
- `chore` — bakım, misc
- `revert` — önceki commit'i geri al

**Scope (opsiyonel):** `admin`, `api`, `auth`, `masa`, `siparis`, `cache`, `qr`, `e2e`, ...

**Örnekler (geçerli):**
```
feat(masa): masa oturumu inaktivite temizliği eklendi
fix(auth): rate limit header hesabı düzeltildi
docs: onboarding rehberi eklendi
refactor(siparis)!: SiparisRepository API imzası değişti
test(e2e): health endpoint 10 senaryo eklendi
chore: composer dev deps güncellendi
```

**Reddedilir:**
```
updated stuff              → type yok
feat:                      → subject eksik
feature(admin): ...        → "feat" kullan
FIX(auth): ...             → küçük harf şart (regex `i` ile esneklik var ama konvansiyon küçük harf)
```

---

## 4. Hook'u Geçici Atlamak (Acil Durum)

Hook hata veriyor ama commit'i zorla atman gerekiyorsa **sadece sen biliyorsan
güvenli**:

```bash
git commit --no-verify -m "fix: acil hotfix"
```

> CLAUDE.md kuralı: `--no-verify` **kullanıcı explicit izin vermediği sürece
> yasak**. Pre-commit hata veriyorsa önce **fix et**, sonra commit at — aksi
> halde CI'da yakalanır.

---

## 5. Sorun Giderme

### `vendor/bin/captainhook` bulunamadı
```bash
composer install
```

### `phpcs: command not found`
```bash
composer require --dev squizlabs/php_codesniffer
```

### Hook çok yavaş (özellikle PHPStan)
- `captainhook.json`'daki pre-commit action'larında `{$STAGED_FILES}` placeholder
  kullanılıyor — sadece staged dosyalar analiz edilir. Tüm proje tarandığında
  dakikalarca sürer.
- PHPStan için ayrıca `phpstan.neon`'a `tmpDir` ekleyerek cache'i hızlandırabilirsin.

### Windows'ta hook çalışmıyor
```bash
# Git Bash'te çalıştır (PowerShell değil)
vendor/bin/captainhook install -f
```
`.git/hooks/pre-commit` dosyası executable olmalı — Git Bash otomatik ayarlar.

### "php-cs-fixer diff found" hatası
Düzeltmeyi otomatik uygula:
```bash
composer fix
git add -u
git commit --amend --no-edit    # (sadece bu commit'i amend ederken güvenli)
```

### `declare_strict_types` hata vermiyor
Repo'da hâlâ legacy dosyalar var — `.php-cs-fixer.dist.php` içinde
`'declare_strict_types' => false` olarak tutulmuştur. Aşamalı geçişte
istediğin dosya başına `declare(strict_types=1);` ekleyebilirsin.

---

## 6. CI Entegrasyonu

CI aynı kontrolleri **paralel** ve **tüm kod tabanında** çalıştırır:

- `.github/workflows/ci.yml` → `lint` job
- `vendor/bin/php-cs-fixer fix --dry-run --diff` — bozulmuş stil → build fail
- `vendor/bin/phpcs --standard=PSR12` — violation → build fail
- `vendor/bin/phpstan analyse --no-progress` — level yükselene kadar tolerance yok

Lokal hook **birinci savunma hattı**, CI **son kapı**. İkisini de geç.

---

## 7. IDE Entegrasyonu (opsiyonel)

**VS Code:**
- Eklenti: `junstyle.php-cs-fixer`
- `.vscode/settings.json`:
  ```json
  {
    "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
    "php-cs-fixer.config": ".php-cs-fixer.dist.php",
    "php-cs-fixer.onsave": true,
    "editor.formatOnSave": true,
    "[php]": {
      "editor.defaultFormatter": "junstyle.php-cs-fixer"
    }
  }
  ```

**PhpStorm:**
- Settings → PHP → Quality Tools → PHP CS Fixer → binary: `vendor/bin/php-cs-fixer`
- "Fix on Save" aktif

---

## 8. Manuel Çalıştırma Komutları

```bash
# Tüm kod tabanında kontrol et (değiştirme)
composer cs-check

# Tüm kod tabanını düzelt
composer cs-fix

# Sadece bir dosya
vendor/bin/php-cs-fixer fix src/Services/UrunService.php

# CaptainHook hook'unu manuel tetikle
vendor/bin/captainhook hook:pre-commit
```

---

## 9. Referans

- [PHP-CS-Fixer docs](https://cs.symfony.com/)
- [CaptainHook docs](https://captainhook.info/)
- [Conventional Commits](https://www.conventionalcommits.org/tr/v1.0.0/)
- Proje: `.php-cs-fixer.dist.php`, `captainhook.json`
