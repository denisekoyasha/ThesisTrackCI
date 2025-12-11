<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=testing');
        require_once __DIR__ . '/../db/db.php';
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
    }

    public function testGeneratePasswordLengthAndCharacters()
    {
        $pw = generatePassword(12);
        $this->assertIsString($pw);
        $this->assertEquals(12, strlen($pw));
        // basic checks for characters presence
        $this->assertMatchesRegularExpression('/[A-Z]/', $pw, 'Password should contain uppercase');
        $this->assertMatchesRegularExpression('/[a-z]/', $pw, 'Password should contain lowercase');
        $this->assertMatchesRegularExpression('/[0-9]/', $pw, 'Password should contain a digit');
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $pw, 'Password should contain a special char');
    }

    public function testValidateEmailWorks()
    {
        $this->assertTrue(validateEmail('user@example.com'));
        $this->assertFalse(validateEmail('not-an-email'));
    }
}
