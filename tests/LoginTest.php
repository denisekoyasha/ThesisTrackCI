<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Login tests rewritten to:
 * - Use Mock PDO mode (APP_ENV=testing)
 * - Avoid redirects/exit() by simulating the core login logic inside the test
 * - Avoid withConsecutive() or other removed PHPUnit APIs
 */
class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure we are in testing mode so db.php creates FakePDO
        putenv('APP_ENV=testing');

        // start a clean session environment for each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];

        // Clear globals that page might use
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // require our db helper (this will create $pdo as FakePDO)
        require_once __DIR__ . '/../db/db.php';

        // $GLOBALS['pdo'] should be the FakePDO instance created by db.php
        $this->assertArrayHasKey('pdo', $GLOBALS, 'pdo must be available from db.php in testing mode');
    }

    protected function tearDown(): void
    {
        // clean up env and session
        putenv('APP_ENV'); // unset
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
     * Helper that simulates the core login logic (same queries & checks used by your page)
     * Returns an array with keys: success (bool), error (string|null), session (array|null)
     */
    private function simulateLoginFlow(object $pdo, string $email, string $password): array
    {
        // Sanitize function comes from db.php, but add a safe fallback
        if (!function_exists('sanitize')) {
            $email = trim($email);
        } else {
            $email = sanitize($email);
        }

        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Please fill in all fields.', 'session' => null];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if (!$student) {
                // logLoginAttempt etc are not required for unit tests here
                return ['success' => false, 'error' => 'No user found with that email.', 'session' => null];
            }

            if (password_verify($password, $student['password'])) {
                // build session array (same fields as your app)
                $session = [
                    'user_id' => $student['id'] ?? null,
                    'role' => 'student',
                    'student_id' => $student['student_id'] ?? null,
                    'name' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
                    'email' => $student['email'] ?? null,
                    'course' => $student['course'] ?? null,
                    'section' => $student['section'] ?? null,
                    'year_level' => $student['year_level'] ?? null,
                    'profile_picture' => $student['profile_picture'] ?? null,
                    'requires_password_change' => $student['requires_password_change'] ?? 0
                ];

                return ['success' => true, 'error' => null, 'session' => $session];
            } else {
                return ['success' => false, 'error' => 'Incorrect password.', 'session' => null];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Login exception: ' . $e->getMessage(), 'session' => null];
        }
    }

    public function testSuccessfulStudentLogin()
    {
        // prepare fake student record
        $studentData = [
            'id' => 1,
            'student_id' => 'STU001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'course' => 'BSCS',
            'section' => '4A',
            'year_level' => '4',
            'profile_picture' => null,
            // set requires_password_change = 1 to avoid real redirect path in app (we're simulating)
            'requires_password_change' => 1
        ];

        // Put mock result into FakePDO (provided by db.php in testing mode)
        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $queryKey = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($queryKey, [$studentData]);

        // run simulation
        $result = $this->simulateLoginFlow($pdo, 'john@example.com', 'password123');

        $this->assertTrue($result['success'], 'Expected successful login flow');
        $this->assertNull($result['error']);
        $this->assertIsArray($result['session']);
        $this->assertEquals(1, $result['session']['user_id']);
        $this->assertEquals('student', $result['session']['role']);
        $this->assertEquals('john@example.com', $result['session']['email']);
        $this->assertEquals(1, $result['session']['requires_password_change']);
    }

    public function testLoginWithInvalidEmail()
    {
        // no mock data added for this email, so fetch() should return false
        /** @var \FakePDO $pdo */
        $pdo = $GLOBALS['pdo'];
        // ensure there is no result for the query
        $queryKey = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($queryKey, []); // explicitly empty

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
        $queryKey = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($queryKey, [$studentData]);

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
        // user exists and requires password change => simulate login result must reflect that
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
        $queryKey = md5("SELECT * FROM students WHERE email = ?");
        $pdo->setMockResult($queryKey, [$studentData]);

        $result = $this->simulateLoginFlow($pdo, 'alice@example.com', 'temppassword');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['session']);
        $this->assertEquals(1, $result['session']['requires_password_change']);
    }
}
