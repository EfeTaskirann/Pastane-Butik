# Sprint 3 — QA+DevOps Todo

## 1. Playwright E2E
- [x] package.json script ekle (`test:e2e`, `test:e2e:ui`, `test:e2e:report`, `test:e2e:headed`, `test:e2e:install`)
- [x] playwright.config.ts (chromium+firefox, retries, html+list+junit reporter, webServer, mobile opt-in)
- [x] tests/e2e/fixtures/admin-auth.ts (login helper + adminPage fixture)
- [x] tests/e2e/fixtures/test-data.ts (PATHS, SAMPLE_URUN, TIMEOUTS sabitleri)
- [x] 10 spec dosyası (01-10) — toplam 59 test case
- [x] .github/workflows/e2e.yml (matrix chromium/firefox + MySQL service + PHP server)

## 2. PHP-CS-Fixer + CaptainHook
- [x] .php-cs-fixer.dist.php (~180 satır, PSR-12 + PhpCsFixer rules)
- [x] composer.json scripts güncellendi (cs-fix, cs-check, lint, fix, captainhook:install) + `suggest` bloğu
- [x] captainhook.json (pre-commit + commit-msg + pre-push + post-merge)
- [x] docs/PRE_COMMIT_SETUP.md (9 bölüm, 209 satır)

## 3. Makefile + Onboarding
- [x] Makefile (41 PHONY target, Windows/Unix dual shell bash)
- [x] docs/ONBOARDING.md (10 bölüm, 408 satır, ~30 dk setup tahmini)

## 4. .gitattributes
- [x] text=auto eol=lf + .bat/.cmd/.ps1 crlf + binary markers + export-ignore

## 5. CLAUDE.md dersleri
- [x] Sprint 3 ek 7 yeni ders (Playwright selector, test yazımı, CaptainHook placeholder, Makefile Windows, Conventional Commits regex, .gitattributes)

## Doğrulama
- [x] PHP syntax: `.php-cs-fixer.dist.php` No syntax errors
- [x] JSON valid: captainhook.json, package.json, composer.json
- [x] YAML valid: .github/workflows/e2e.yml
- [x] Tüm dosyalar yazıldı (2319 satır total)

## Sonuç
- Playwright: 10 spec dosyası, 59 test case, 2 fixture dosyası
- Makefile: 41 target, Windows/Unix dual uyumluluk
- Onboarding: 10 bölüm, ~30 dk setup
- PHP-CS-Fixer: ~35 rule konfigürasyonu
- CaptainHook: 5 hook (pre-commit, commit-msg, pre-push, post-merge + prepare-commit-msg disabled)
