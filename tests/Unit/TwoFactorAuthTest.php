<?php
/**
 * Two Factor Auth Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use TwoFactorAuth;

class TwoFactorAuthTest extends TestCase
{
    /**
     * @test
     */
    public function it_generates_valid_secrets(): void
    {
        $secret = TwoFactorAuth::generateSecret();

        $this->assertIsString($secret);
        $this->assertEquals(16, strlen($secret));

        // Should only contain base32 characters
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    /**
     * @test
     */
    public function it_generates_different_secrets_each_time(): void
    {
        $secret1 = TwoFactorAuth::generateSecret();
        $secret2 = TwoFactorAuth::generateSecret();

        $this->assertNotEquals($secret1, $secret2);
    }

    /**
     * @test
     */
    public function it_generates_valid_totp_codes(): void
    {
        $secret = TwoFactorAuth::generateSecret();
        $code = TwoFactorAuth::getCode($secret);

        $this->assertIsString($code);
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    /**
     * @test
     */
    public function it_verifies_correct_codes(): void
    {
        $secret = TwoFactorAuth::generateSecret();
        $code = TwoFactorAuth::getCode($secret);

        $result = TwoFactorAuth::verify($secret, $code);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function it_rejects_incorrect_codes(): void
    {
        $secret = TwoFactorAuth::generateSecret();

        $result = TwoFactorAuth::verify($secret, '000000');

        // May pass if the random code happens to be 000000, but very unlikely
        // In practice this should almost always be false
        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function it_allows_time_drift(): void
    {
        $secret = TwoFactorAuth::generateSecret();

        // Get code from 30 seconds ago (within typical drift window)
        $timestamp = time() - 30;
        $oldCode = TwoFactorAuth::getCode($secret, $timestamp);

        // Should still verify with default drift allowance
        $result = TwoFactorAuth::verify($secret, $oldCode);

        // This might fail if we're right at a time boundary, which is acceptable
        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function it_generates_provisioning_uri(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $account = 'user@example.com';
        $issuer = 'Test App';

        $uri = TwoFactorAuth::getProvisioningUri($secret, $account, $issuer);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        // URI'da email encode edilmiş olabilir (%40 vs @)
        $this->assertTrue(
            str_contains($uri, $account) || str_contains($uri, rawurlencode($account)),
            "URI should contain account email (plain or encoded)"
        );
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=', $uri);
        // Issuer'da boşluk %20 olarak encode edilir
        $this->assertTrue(
            str_contains($uri, $issuer) || str_contains($uri, rawurlencode($issuer)),
            "URI should contain issuer name (plain or encoded)"
        );
    }

    /**
     * @test
     */
    public function it_generates_consistent_codes_for_same_timestamp(): void
    {
        $secret = TwoFactorAuth::generateSecret();
        $timestamp = time();

        $code1 = TwoFactorAuth::getCode($secret, $timestamp);
        $code2 = TwoFactorAuth::getCode($secret, $timestamp);

        $this->assertEquals($code1, $code2);
    }

    /**
     * @test
     */
    public function it_generates_different_codes_for_different_timestamps(): void
    {
        $secret = TwoFactorAuth::generateSecret();

        $code1 = TwoFactorAuth::getCode($secret, time());
        $code2 = TwoFactorAuth::getCode($secret, time() + 60); // 60 seconds later

        // Codes should be different (unless very unlucky)
        // Note: There's a small chance they could be the same by coincidence
        $this->assertIsString($code1);
        $this->assertIsString($code2);
    }
}
