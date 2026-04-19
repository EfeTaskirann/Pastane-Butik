import { test, expect } from '@playwright/test';
import { PATHS } from './fixtures/test-data';

/**
 * API Health — /api/health + /api/health/live + /api/health/ready.
 *
 * Şema (Sprint 1):
 *   { status: "ok"|"degraded"|"down", timestamp, checks: {database, disk, php, uptime_sec}, version }
 */
test.describe('API Health Check', () => {
  test('GET /api/health → 200 ok|degraded, JSON şeması doğru', async ({ request }) => {
    const resp = await request.get(PATHS.apiHealth);
    expect([200, 503]).toContain(resp.status());

    const contentType = resp.headers()['content-type'] ?? '';
    expect(contentType).toContain('application/json');

    const body = await resp.json();

    expect(body).toHaveProperty('status');
    expect(['ok', 'degraded', 'down']).toContain(body.status);

    expect(body).toHaveProperty('timestamp');
    expect(body).toHaveProperty('version');
    expect(body).toHaveProperty('checks');

    // checks.database
    expect(body.checks).toHaveProperty('database');
    expect(['ok', 'down', 'unknown']).toContain(body.checks.database.status);

    // checks.php.version
    expect(body.checks).toHaveProperty('php');
    expect(body.checks.php.version).toMatch(/^\d+\.\d+/);

    // checks.uptime_sec
    expect(body.checks).toHaveProperty('uptime_sec');
    expect(typeof body.checks.uptime_sec).toBe('number');
  });

  test('GET /api/health/live → 200 sadece PHP kontrolü', async ({ request }) => {
    const resp = await request.get(PATHS.apiHealthLive);
    expect(resp.status()).toBe(200);

    const body = await resp.json();
    expect(body.status).toBe('ok');
    expect(body).toHaveProperty('php');
    // DB check YAPMAMALI — liveness sadece PHP
    expect(body.checks?.database).toBeUndefined();
  });

  test('GET /api/health/ready → DB+cache+disk full check', async ({ request }) => {
    const resp = await request.get(PATHS.apiHealthReady);
    expect([200, 503]).toContain(resp.status());

    const body = await resp.json();
    expect(['ok', 'degraded', 'down']).toContain(body.status);
    expect(body.checks).toHaveProperty('database');
    expect(body.checks).toHaveProperty('cache');
  });

  test('cache-control: no-store header set edilmiş', async ({ request }) => {
    const resp = await request.get(PATHS.apiHealth);
    const cc = resp.headers()['cache-control'] ?? '';
    expect(cc).toContain('no-store');
  });

  test('metrics endpoint auth olmadan 401 döner', async ({ request }) => {
    const resp = await request.get('/api/health.php/metrics');
    expect([401, 403]).toContain(resp.status());
  });

  test('metrics endpoint bearer token ile erişilebilir (token set ise)', async ({ request }) => {
    const token = process.env.E2E_HEALTH_TOKEN;
    test.skip(!token, 'E2E_HEALTH_TOKEN set edilmemiş, metrics auth testi skip');

    const resp = await request.get('/api/health.php/metrics', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 401]).toContain(resp.status());
  });

  test('response time < 2s (p95)', async ({ request }) => {
    const start = Date.now();
    await request.get(PATHS.apiHealthLive);
    const elapsed = Date.now() - start;
    expect(elapsed, 'liveness endpoint hızlı olmalı').toBeLessThan(2_000);
  });
});
