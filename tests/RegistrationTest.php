<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class RegistrationTest extends TestCase
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
        
        $GLOBALS['pdo'] = $this->mockPdo;
        
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_POST = [];
        unset($GLOBALS['pdo']);
    }

    public function testValidStudentRegistrationData()
    {
        $_POST = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'student',
            'student_id' => 'STU001',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        require_once __DIR__ . '/../db/db.php';

        // Test data validation
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize($_POST['role']);

        $this->assertEquals('John Doe', $name);
        $this->assertEquals('john@example.com', $email);
        $this->assertEquals('password123', $password);
        $this->assertEquals('student', $role);

        // Test email validation
        $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);

        // Test required fields are not empty
        $this->assertNotEmpty($name);
        $this->assertNotEmpty($email);
        $this->assertNotEmpty($password);
        $this->assertNotEmpty($role);
    }

    public function testValidAdvisorRegistrationData()
    {
        $_POST = [
            'name' => 'Dr. Jane Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'password123',
            'role' => 'advisor',
            'employee_id' => 'EMP001',
            'course' => 'BSCS',
            'year_handled' => '4',
            'sections_handled' => 'A,B,C',
            'department' => 'Computer Science'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        require_once __DIR__ . '/../db/db.php';

        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $employee_id = sanitize($_POST['employee_id']);

        $this->assertEquals('Dr. Jane Smith', $name);
        $this->assertEquals('jane.smith@example.com', $email);
        $this->assertEquals('advisor', $role);
        $this->assertEquals('EMP001', $employee_id);

        // Test advisor-specific fields
        $this->assertNotEmpty($_POST['employee_id']);
        $this->assertNotEmpty($_POST['course']);
        $this->assertNotEmpty($_POST['year_handled']);
        $this->assertNotEmpty($_POST['sections_handled']);
    }

    public function testInvalidEmailFormat()
    {
        $_POST = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'role' => 'student'
        ];

        require_once __DIR__ . '/../db/db.php';

        $email = sanitize($_POST['email']);
        $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    public function testEmptyRequiredFields()
    {
        $_POST = [
            'name' => '',
            'email' => '',
            'password' => '',
            'role' => ''
        ];

        require_once __DIR__ . '/../db/db.php';

        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize($_POST['role']);

        $this->assertEmpty($name);
        $this->assertEmpty($email);
        $this->assertEmpty($password);
        $this->assertEmpty($role);
    }

    public function testPasswordHashing()
    {
        $password = 'testpassword123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $this->assertNotEquals($password, $hashedPassword);
        $this->assertTrue(password_verify($password, $hashedPassword));
    }

    public function testStudentSectionFormat()
    {
        $course = 'BSCS';
        $year_level = '4';
        $section = 'A';
        
        $full_section = $course . '-' . $year_level . $section;
        
        $this->assertEquals('BSCS-4A', $full_section);
    }

    public function testInvalidRole()
    {
        $_POST = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'invalid_role'
        ];

        require_once __DIR__ . '/../db/db.php';

        $role = sanitize($_POST['role']);
        $validRoles = ['student', 'advisor'];
        
        $this->assertNotContains($role, $validRoles);
    }

    public function testRegistrationWithInvalidMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Simulated exit or redirect for testing");
        
        include __DIR__ . '/../register.php';
    }
}
