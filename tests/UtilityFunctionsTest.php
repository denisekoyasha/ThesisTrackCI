<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class UtilityFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        
        require_once __DIR__ . '/../db/db.php';
    }

    public function testSanitizeXSSPrevention()
    {
        $maliciousInput = '<script>alert("XSS")</script>';
        $sanitized = sanitize($maliciousInput);
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
    }

    public function testSanitizeSQLInjectionPrevention()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        $sanitized = sanitize($maliciousInput);
        
        $this->assertStringNotContainsString("DROP TABLE", $sanitized);
        $this->assertStringContainsString("&#039;", $sanitized); // Single quote encoded
    }

    public function testSanitizeWhitespaceHandling()
    {
        $input = "   test data   ";
        $sanitized = sanitize($input);
        
        $this->assertEquals("test data", $sanitized);
        $this->assertStringStartsNotWith(" ", $sanitized);
        $this->assertStringEndsNotWith(" ", $sanitized);
    }

    public function testValidateEmailEdgeCases()
    {
        // Valid emails
        $this->assertTrue(validateEmail('test@domain.com'));
        $this->assertTrue(validateEmail('user.name@domain.co.uk'));
        $this->assertTrue(validateEmail('user+tag@domain.org'));
        
        // Invalid emails
        $this->assertFalse(validateEmail('plainaddress'));
        $this->assertFalse(validateEmail('@missingdomain.com'));
        $this->assertFalse(validateEmail('missing@.com'));
        $this->assertFalse(validateEmail('spaces @domain.com'));
    }

    public function testGeneratePasswordSecurity()
    {
        $password1 = generatePassword(16);
        $password2 = generatePassword(16);
        
        // Passwords should be different
        $this->assertNotEquals($password1, $password2);
        
        // Should contain mixed case, numbers, and special characters
        $this->assertMatchesRegularExpression('/[a-z]/', $password1);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password1);
        $this->assertMatchesRegularExpression('/[0-9]/', $password1);
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $password1);
    }

    public function testGeneratePasswordLength()
    {
        for ($length = 8; $length <= 32; $length += 4) {
            $password = generatePassword($length);
            $this->assertEquals($length, strlen($password));
        }
    }

    public function testSanitizePreservesValidContent()
    {
        $validInput = "This is normal text with numbers 123 and symbols !@#";
        $sanitized = sanitize($validInput);
        
        $this->assertStringContainsString("This is normal text", $sanitized);
        $this->assertStringContainsString("123", $sanitized);
    }

    public function testSanitizeHandlesUnicodeCharacters()
    {
        $unicodeInput = "Café résumé naïve";
        $sanitized = sanitize($unicodeInput);
        
        // Should preserve Unicode characters
        $this->assertStringContainsString("Café", $sanitized);
        $this->assertStringContainsString("résumé", $sanitized);
        $this->assertStringContainsString("naïve", $sanitized);
    }

    public function testEmailValidationWithInternationalDomains()
    {
        // Test international domain names
        $this->assertTrue(validateEmail('test@example.org'));
        $this->assertTrue(validateEmail('user@university.edu'));
        $this->assertTrue(validateEmail('contact@company.co.uk'));
    }
}
