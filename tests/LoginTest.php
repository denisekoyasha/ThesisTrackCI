<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class LoginTest extends TestCase
{
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        // Mock global PDO
        $GLOBALS['pdo'] = $this->mockPdo;
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        
        // Mock POST data
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($GLOBALS['pdo']);
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
            'course' => 'BSCS',
            'section' => '4A',
            'year_level' => '4',
            'profile_picture' => null,
            'requires_password_change' => 0
        ];

        $_POST = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->mockStmt->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [['john@example.com']],
                [[1]]
            );

        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($studentData);

        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->mockStmt);

        // Include the sanitize function
        require_once __DIR__ . '/../db/db.php';

        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $this->assertNotEmpty($email);
        $this->assertNotEmpty($password);
        
        // Simulate successful password verification
        $this->assertTrue(password_verify($password, $studentData['password']));
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Redirect to student dashboard after login");
        
        // Include student login to trigger redirect
        include __DIR__ . '/../student_login.php';
    }

    public function testLoginWithInvalidEmail()
    {
        $_POST = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([['nonexistent@example.com']]);

        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // No user found

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        require_once __DIR__ . '/../db/db.php';

        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $this->assertNotEmpty($email);
        $this->assertNotEmpty($password);
    }

    public function testLoginWithIncorrectPassword()
    {
        $studentData = [
            'id' => 1,
            'email' => 'john@example.com',
            'password' => password_hash('correctpassword', PASSWORD_DEFAULT)
        ];

        $_POST = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        $this->assertFalse(password_verify($_POST['password'], $studentData['password']));
    }

    public function testEmptyLoginFields()
    {
        $_POST = [
            'email' => '',
            'password' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        require_once __DIR__ . '/../db/db.php';

        $email = sanitize($_POST['email']);
        $password = $_POST['password'];

        $this->assertEmpty($email);
        $this->assertEmpty($password);
    }

    public function testPasswordChangeRequired()
    {
        $studentData = [
            'id' => 1,
            'email' => 'john@example.com',
            'password' => password_hash('temppassword', PASSWORD_DEFAULT),
            'requires_password_change' => 1
        ];

        $_POST = [
            'email' => 'john@example.com',
            'password' => 'temppassword'
        ];

        $this->assertTrue(password_verify($_POST['password'], $studentData['password']));
        $this->assertEquals(1, $studentData['requires_password_change']);
    }

    public function testPasswordChangeProcess()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['requires_password_change'] = 1;
        
        $_POST = [
            'new_password' => 'newpassword123',
            'confirm_password' => 'newpassword123'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([password_hash('newpassword123', PASSWORD_DEFAULT), 1]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with("UPDATE students SET password = ?, requires_password_change = 0 WHERE id = ?")
            ->willReturn($this->mockStmt);

        require_once __DIR__ . '/../db/db.php';

        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $this->assertEquals($new_password, $confirm_password);
        $this->assertNotEmpty($new_password);
    }
}
