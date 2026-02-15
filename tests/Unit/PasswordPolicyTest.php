<?php
/**
 * Password Policy Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use PasswordPolicy;

class PasswordPolicyTest extends TestCase
{
    /**
     * @test
     */
    public function it_rejects_short_passwords(): void
    {
        $result = PasswordPolicy::validate('Short1!');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * @test
     */
    public function it_requires_uppercase_letters(): void
    {
        $result = PasswordPolicy::validate('verylongpassword123!');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'büyük harf')
        );
    }

    /**
     * @test
     */
    public function it_requires_lowercase_letters(): void
    {
        $result = PasswordPolicy::validate('VERYLONGPASSWORD123!');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'küçük harf')
        );
    }

    /**
     * @test
     */
    public function it_requires_numbers(): void
    {
        $result = PasswordPolicy::validate('VeryLongPassword!@#');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'rakam')
        );
    }

    /**
     * @test
     */
    public function it_requires_special_characters(): void
    {
        $result = PasswordPolicy::validate('VeryLongPassword123');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'özel karakter')
        );
    }

    /**
     * @test
     */
    public function it_accepts_strong_passwords(): void
    {
        $result = PasswordPolicy::validate('MyStr0ng@Passw0rd!');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * @test
     */
    public function it_rejects_common_passwords(): void
    {
        $result = PasswordPolicy::validate('Password123!@#');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'yaygın')
        );
    }

    /**
     * @test
     */
    public function it_rejects_passwords_containing_username(): void
    {
        $result = PasswordPolicy::validate('MyUsername123!@#', 'username');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'kullanıcı adını')
        );
    }

    /**
     * @test
     */
    public function it_rejects_sequential_characters(): void
    {
        $result = PasswordPolicy::validate('MyP@ssw0rd1234');

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'ardışık')
        );
    }

    /**
     * @test
     */
    public function it_calculates_password_strength(): void
    {
        // Weak password
        $weak = PasswordPolicy::calculateStrength('weak');
        $this->assertLessThanOrEqual(4, $weak['score']);

        // Strong password
        $strong = PasswordPolicy::calculateStrength('MyV3ryStr0ng!P@ssword');
        $this->assertGreaterThan(6, $strong['score']);
    }

    /**
     * @test
     */
    public function it_generates_strong_passwords(): void
    {
        $password = PasswordPolicy::generate(16);

        $this->assertEquals(16, strlen($password));

        // Generated password should be valid
        $result = PasswordPolicy::validate($password);
        $this->assertTrue($result['valid']);
    }

    /**
     * @test
     */
    public function it_returns_policy_requirements(): void
    {
        $requirements = PasswordPolicy::getRequirements();

        $this->assertIsArray($requirements);
        $this->assertNotEmpty($requirements);
    }

    /**
     * Helper method to check if errors contain specific text
     *
     * @param array $errors
     * @param string $text
     * @return bool
     */
    private function containsError(array $errors, string $text): bool
    {
        foreach ($errors as $error) {
            if (str_contains(strtolower($error), strtolower($text))) {
                return true;
            }
        }
        return false;
    }
}
