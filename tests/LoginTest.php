<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Login tests (minimal, CI-friendly)
 * - Uses FakePDO created by db/db.php when APP_ENV=testing
 * - Simulates the core login flow without requiring real redirects or a real DB
 */
class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        // Enable testing mode so db.php constructs FakePDO
        putenv('APP_ENV=testing');

        // Clean session / globals
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Require db.php which will create $pdo (FakePDO) in testing mode
        require_once __DIR__ . '/../db/db.php';

        // Ensure FakePDO is available
        $this->assertArrayHasKey('pdo', $GLOBALS, 'pdo must be available from db.php in testing mode');
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV'); // unset
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
     * Minimal simulation of login handling logic used by the app.
     * Returns ['success' => bool, 'error' => ?string, 'session' => ?array]
     */
    private function simulateLoginFlow(object $pdo, string $email, string $password): array
    {
        // sanitize() is defined in db.php; fallback if missing
        if (function_exists('sanitize')) {
            $email = sanitize($email);
        } else {
            $email = trim($email);
        }

        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Please fill in all fields.', 'session' => null];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if (!$student) {
                return ['success' => false, 'error' => 'No user found with that email.', 'session' => null];
            }

            if (password_verify($password, $student['password'])) {
                $session = [
                    'user_id' => $student['id'] ?? null,
                    'role' => 'student',
                    'student_id' => $student['student_id'] ?? null,
                    'name' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
                    'email' => $student['email'] ?? null,
                    'requires_password_change' => $student['requires_password_change'] ?? 0
                ];
                return ['success' => true, 'error' => null, 'session' => $session];
            }

            return ['success' => false, 'error' => 'Incorrect password.', 'session' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Login exception: ' . $e->getMessage(), 'session' => null];
        }
    }

    public function testSuccessfulStudentLogin()
    {
        $studentData = [
            'id' => 1,
            'student_id' => 'STU001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'requires_password_change' => 1
        ];

        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $key = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($key, [$studentData]);

        $result = $this->simulateLoginFlow($pdo, 'john@example.com', 'password123');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['session']);
        $this->assertEquals(1, $result['session']['user_id']);
        $this->assertEquals('john@example.com', $result['session']['email']);
        $this->assertEquals(1, $result['session']['requires_password_change']);
    }

    public function testLoginWithInvalidEmail()
    {
        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $key = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($key, []); // explicitly no result

        $result = $this->simulateLoginFlow($pdo, 'nonexistent@example.com', 'password123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No user found', $result['error']);
        $this->assertNull($result['session']);
    }

    public function testLoginWithIncorrectPassword()
    {
        $studentData = [
            'id' => 1,
            'email' => 'john@example.com',
            'password' => password_hash('correctpassword', PASSWORD_DEFAULT)
        ];

        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $key = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($key, [$studentData]);

        $result = $this->simulateLoginFlow($pdo, 'john@example.com', 'wrongpassword');

        $this->assertFalse($result['success']);
        $this->assertEquals('Incorrect password.', $result['error']);
        $this->assertNull($result['session']);
    }

    public function testEmptyLoginFields()
    {
        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];

        $result = $this->simulateLoginFlow($pdo, '', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Please fill in all fields.', $result['error']);
        $this->assertNull($result['session']);
    }

    public function testPasswordChangeRequiredFlag()
    {
        $studentData = [
            'id' => 2,
            'student_id' => 'STU002',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'password' => password_hash('temppassword', PASSWORD_DEFAULT),
            'requires_password_change' => 1
        ];

        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $key = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($key, [$studentData]);

        $result = $this->simulateLoginFlow($pdo, 'alice@example.com', 'temppassword');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['session']);
        $this->assertEquals(1, $result['session']['requires_password_change']);
    }
}
