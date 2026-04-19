<?php

declare(strict_types=1);

/**
 * Pastane — CodeCanyon Submission Package Builder (P0-05)
 *
 * Üretir:
 *   dist/pastane-submission-v1.0.0/
 *     ├── main_files/          (kod — `git archive --worktree-attributes`)
 *     ├── documentation/       (documentation/ kopyası)
 *     ├── licensing/           (LICENSE.txt + CREDITS.txt + CHANGELOG.md)
 *     └── README.txt           (buyer'ı documentation'a yönlendirir)
 *   dist/pastane-submission-v1.0.0.zip (üst dizinin zip'i)
 *
 * .gitattributes'teki export-ignore kuralları uygulanır — node_modules,
 * .env, tests, docs, CLAUDE.md vs. main_files'a DAHİL EDILMEZ.
 *
 * KULLANIM:
 *   php bin/build-submission.php
 *   php bin/build-submission.php --version=1.0.1
 *
 * EXIT CODES:
 *   0 = başarı
 *   1 = gereksiz dosya sızdı (A3 audit fail)
 *   2 = git archive hatası
 *   3 = zip üretim hatası
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

$version = '1.0.0';
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--version=(.+)$/', $a, $m)) {
        $version = trim($m[1]);
    }
}

$buildName = "pastane-submission-v{$version}";
$buildRoot = $rootPath . '/dist/' . $buildName;
$zipPath = $rootPath . '/dist/' . $buildName . '.zip';

fwrite(STDOUT, "Building submission package v$version ...\n");

// ---- Cleanup prev ----
if (is_dir($buildRoot)) {
    rrmdir($buildRoot);
}
if (file_exists($zipPath)) {
    unlink($zipPath);
}

if (!is_dir($rootPath . '/dist')) {
    mkdir($rootPath . '/dist', 0775, true);
}

mkdir($buildRoot, 0775, true);
mkdir($buildRoot . '/main_files', 0775, true);
mkdir($buildRoot . '/documentation', 0775, true);
mkdir($buildRoot . '/licensing', 0775, true);

// ---- 1. main_files via git archive ----
fwrite(STDOUT, "  Step 1: git archive → main_files/ ...\n");
$archivePath = $rootPath . '/dist/' . $buildName . '-main.zip';

$cmd = sprintf(
    'git archive --format=zip --worktree-attributes -o %s HEAD',
    escapeshellarg($archivePath)
);
exec($cmd, $outArchive, $exitArchive);
if ($exitArchive !== 0) {
    fwrite(STDERR, "git archive failed (exit=$exitArchive):\n" . implode("\n", $outArchive) . "\n");
    exit(2);
}

// Extract
$zip = new ZipArchive();
if ($zip->open($archivePath) !== true) {
    fwrite(STDERR, "Cannot open $archivePath\n");
    exit(2);
}
$zip->extractTo($buildRoot . '/main_files');
$zip->close();
@unlink($archivePath);

// ---- 2. documentation ----
fwrite(STDOUT, "  Step 2: documentation/ copy ...\n");
if (is_dir($rootPath . '/documentation')) {
    rcopy($rootPath . '/documentation', $buildRoot . '/documentation');
} else {
    fwrite(STDERR, "WARNING: documentation/ not found.\n");
}

// ---- 3. licensing ----
fwrite(STDOUT, "  Step 3: licensing/ files ...\n");
foreach (['LICENSE.txt', 'CREDITS.txt', 'CHANGELOG.md'] as $f) {
    $src = $rootPath . '/' . $f;
    if (is_file($src)) {
        copy($src, $buildRoot . '/licensing/' . $f);
    } else {
        fwrite(STDERR, "WARNING: $f not found.\n");
    }
}

// ---- 4. README.txt ----
fwrite(STDOUT, "  Step 4: README.txt ...\n");
$readme = <<<TXT
Tatli Dusler — Butik Pasta & Dessert Ordering System
Version: $version

This package contains three folders:

  main_files/       → Application source code. Extract to your web root.
  documentation/    → Installation, configuration, admin guide, user guide, FAQ.
                      Open documentation/index.html in any browser to begin.
  licensing/        → Envato license summary, third-party credits, changelog.

QUICK START
  1. Open documentation/installation.html — follow the 9 setup steps.
  2. Read documentation/configuration.html for .env variable reference.
  3. Consult documentation/faq.html when stuck.

SUPPORT
  See documentation/index.html → Support section.

Copyright (c) 2026 Tatli Dusler. All rights reserved.
Licensed via CodeCanyon (envato.com) — see licensing/LICENSE.txt.
TXT;
file_put_contents($buildRoot . '/README.txt', $readme);

// ---- 5. Offender sweep (A3 audit) ----
fwrite(STDOUT, "  Step 5: offender sweep ...\n");
$offenders = [];
$patterns = [
    '#(^|/)node_modules(/|$)#',
    '#(^|/)\.git(/|$)#',
    '#(^|/)\.env$#',
    '#(^|/)tests/#',
    '#(^|/)tasks/#',
    '#(^|/)CLAUDE\.md$#',
    '#(^|/)Dockerfile$#',
    '#(^|/)docker-compose[^/]*\.ya?ml$#',
    '#(^|/)phpunit\.xml$#',
    '#(^|/)playwright\.config\.ts$#',
    '#(^|/)\.claude(/|$)#',
    '#(^|/)\.github(/|$)#',
    '#(^|/)composer\.lock$#',
    '#(^|/)package-lock\.json$#',
];

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildRoot . '/main_files', RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iter as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($buildRoot) + 1));
    foreach ($patterns as $p) {
        if (preg_match($p, $rel)) {
            $offenders[] = $rel;
            break;
        }
    }
}

if (count($offenders) > 0) {
    fwrite(STDERR, "A3 FAIL: Forbidden files leaked into main_files:\n");
    foreach ($offenders as $o) fwrite(STDERR, "  - $o\n");
    exit(1);
}
fwrite(STDOUT, "    clean — 0 offenders.\n");

// ---- 6. Final zip ----
fwrite(STDOUT, "  Step 6: zipping ...\n");
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create $zipPath\n");
    exit(3);
}

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $f) {
    $rel = substr($f->getPathname(), strlen($buildRoot) + 1);
    $rel = $buildName . '/' . str_replace('\\', '/', $rel);
    if ($f->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($f->getPathname(), $rel);
    }
}
$zip->close();

// ---- Summary ----
$size = filesize($zipPath);
fwrite(STDOUT, "\n--- SUMMARY ---\n");
fwrite(STDOUT, "Build root: $buildRoot\n");
fwrite(STDOUT, "Zip:        $zipPath\n");
fwrite(STDOUT, "Size:       " . number_format($size) . " bytes (" . round($size / 1024 / 1024, 2) . " MB)\n");

// Sanity check — expected structure inside zip
$zip = new ZipArchive();
$zip->open($zipPath);
$topLevel = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (preg_match('#^' . preg_quote($buildName, '#') . '/([^/]+)/?#', $n, $m)) {
        $topLevel[$m[1]] = true;
    }
}
$zip->close();
$required = ['main_files', 'documentation', 'licensing', 'README.txt'];
$missing = array_filter($required, static fn($r) => !isset($topLevel[$r]));
if (count($missing) > 0) {
    fwrite(STDERR, "A3 FAIL: missing top-level entries: " . implode(', ', $missing) . "\n");
    exit(1);
}
fwrite(STDOUT, "Structure:  OK (main_files/, documentation/, licensing/, README.txt)\n");

exit(0);

// ---- Helpers ----

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isDir()) @rmdir($f->getPathname());
        else @unlink($f->getPathname());
    }
    @rmdir($dir);
}

function rcopy(string $src, string $dst): void
{
    if (!is_dir($dst)) mkdir($dst, 0775, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($src) + 1);
        $target = $dst . DIRECTORY_SEPARATOR . $rel;
        if ($f->isDir()) {
            if (!is_dir($target)) mkdir($target, 0775, true);
        } else {
            copy($f->getPathname(), $target);
        }
    }
}
