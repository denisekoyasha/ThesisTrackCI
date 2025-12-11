<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class UtilityFunctionsTest extends TestCase
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

    public function testSanitizeRemovesDangerousContent()
    {
        $raw = "  <script>alert('x')</script> O'Reilly \\ ";
        $san = sanitize($raw);
        $this->assertIsString($san);
        // HTML special chars encoded
        $this->assertStringNotContainsString("<script>", $san);
        // backslashes removed
        $this->assertStringNotContainsString("\\", $san);
        // leading/trailing trimmed
        $this->assertEquals(trim($san), $san);
    }

    public function testValidateEmail()
    {
        $this->assertTrue(validateEmail('test.user+label@example.co'));
        $this->assertFalse(validateEmail('bad@@example'));
    }
}
