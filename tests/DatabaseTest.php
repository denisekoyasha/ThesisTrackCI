<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        // Include the database file for testing utility functions
        require_once __DIR__ . '/../db/db.php';
    }

    public function testSanitizeFunction()
    {
        $input = "  <script>alert('xss')</script>  ";
        $expected = "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;";
        
        $result = sanitize($input);
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeWithNull()
    {
        $result = sanitize(null);
        $this->assertNull($result);
    }

    public function testSanitizeWithEmptyString()
    {
        $result = sanitize("");
        $this->assertEquals("", $result);
    }

    public function testSanitizeWithSpecialCharacters()
    {
        $input = "Test & \"quotes\" and 'apostrophes'";
        $expected = "Test &amp; &quot;quotes&quot; and &#039;apostrophes&#039;";
        
        $result = sanitize($input);
        $this->assertEquals($expected, $result);
    }

    public function testValidateEmailWithValidEmail()
    {
        $this->assertTrue(validateEmail('test@example.com'));
        $this->assertTrue(validateEmail('user.name+tag@domain.co.uk'));
    }

    public function testValidateEmailWithInvalidEmail()
    {
        $this->assertFalse(validateEmail('invalid-email'));
        $this->assertFalse(validateEmail('test@'));
        $this->assertFalse(validateEmail('@domain.com'));
        $this->assertFalse(validateEmail(''));
    }

    public function testGeneratePassword()
    {
        $password = generatePassword();
        
        // Test default length
        $this->assertEquals(12, strlen($password));
        
        // Test custom length
        $customPassword = generatePassword(8);
        $this->assertEquals(8, strlen($customPassword));
        
        // Test that passwords are different
        $password2 = generatePassword();
        $this->assertNotEquals($password, $password2);
    }

    public function testGeneratePasswordCharacterSet()
    {
        $password = generatePassword(100); // Generate long password to test character variety
        
        // Should contain at least one lowercase letter
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        
        // Should contain at least one uppercase letter
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        
        // Should contain at least one digit
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }
}
