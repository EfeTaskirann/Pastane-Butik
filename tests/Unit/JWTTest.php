<?php
/**
 * JWT Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use JWT;

class JWTTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_valid_jwt_tokens(): void
    {
        $payload = [
            'user_id' => 1,
            'username' => 'testuser',
            'role' => 'admin',
        ];

        $token = JWT::create($payload);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT format: header.payload.signature
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    /**
     * @test
     */
    public function it_verifies_valid_tokens(): void
    {
        $payload = [
            'user_id' => 1,
            'username' => 'testuser',
        ];

        $token = JWT::create($payload);
        $decoded = JWT::verify($token);

        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('testuser', $decoded['username']);
    }

    /**
     * @test
     */
    public function it_rejects_invalid_tokens(): void
    {
        $result = JWT::verify('invalid.token.here');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function it_rejects_tampered_tokens(): void
    {
        $token = JWT::create(['user_id' => 1]);

        // Tamper with the payload
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['user_id'] = 999;
        $parts[1] = base64_encode(json_encode($payload));
        $tamperedToken = implode('.', $parts);

        $result = JWT::verify($tamperedToken);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function it_includes_expiration_in_token(): void
    {
        $token = JWT::create(['user_id' => 1], 3600);
        $decoded = JWT::verify($token);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }

    /**
     * @test
     */
    public function it_extracts_bearer_token_from_header(): void
    {
        $token = JWT::create(['user_id' => 1]);

        // Simulate Authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";

        $extracted = JWT::getTokenFromHeader();

        $this->assertEquals($token, $extracted);

        // Cleanup
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * @test
     */
    public function it_returns_null_for_missing_auth_header(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $extracted = JWT::getTokenFromHeader();

        $this->assertNull($extracted);
    }

    /**
     * @test
     */
    public function it_includes_issued_at_timestamp(): void
    {
        $before = time();
        $token = JWT::create(['user_id' => 1]);
        $after = time();

        $decoded = JWT::verify($token);

        $this->assertArrayHasKey('iat', $decoded);
        $this->assertGreaterThanOrEqual($before, $decoded['iat']);
        $this->assertLessThanOrEqual($after, $decoded['iat']);
    }
}
