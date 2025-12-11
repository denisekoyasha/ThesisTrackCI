<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class SessionManagementTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear session data
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSessionDataStorage()
    {
        $userData = [
            'user_id' => 1,
            'role' => 'student',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        foreach ($userData as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $this->assertEquals(1, $_SESSION['user_id']);
        $this->assertEquals('student', $_SESSION['role']);
        $this->assertEquals('John Doe', $_SESSION['name']);
        $this->assertEquals('john@example.com', $_SESSION['email']);
    }

    public function testSessionRoleValidation()
    {
        $_SESSION['role'] = 'student';
        
        $allowedRoles = ['student', 'advisor'];
        $this->assertContains($_SESSION['role'], $allowedRoles);
        
        $restrictedRoles = ['admin', 'coordinator'];
        $this->assertNotContains($_SESSION['role'], $restrictedRoles);
    }

    public function testSessionUserIdValidation()
    {
        $_SESSION['user_id'] = 1;
        
        $this->assertIsInt($_SESSION['user_id']);
        $this->assertGreaterThan(0, $_SESSION['user_id']);
    }

    public function testSessionDataTypes()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['student_id'] = 'STU001';
        $_SESSION['year_level'] = '4';
        $_SESSION['requires_password_change'] = 0;

        $this->assertIsInt($_SESSION['user_id']);
        $this->assertIsString($_SESSION['student_id']);
        $this->assertIsString($_SESSION['year_level']);
        $this->assertIsInt($_SESSION['requires_password_change']);
    }

    public function testSessionClearing()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'student';
        
        $this->assertNotEmpty($_SESSION);
        
        $_SESSION = [];
        
        $this->assertEmpty($_SESSION);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('role', $_SESSION);
    }

    public function testPasswordChangeFlag()
    {
        $_SESSION['requires_password_change'] = 1;
        
        $this->assertTrue($_SESSION['requires_password_change'] == 1);
        
        $_SESSION['requires_password_change'] = 0;
        
        $this->assertFalse($_SESSION['requires_password_change'] == 1);
    }

    public function testStudentSpecificSessionData()
    {
        $studentSession = [
            'user_id' => 1,
            'role' => 'student',
            'student_id' => 'STU001',
            'course' => 'BSCS',
            'section' => '4A',
            'year_level' => '4'
        ];

        foreach ($studentSession as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $this->assertEquals('STU001', $_SESSION['student_id']);
        $this->assertEquals('BSCS', $_SESSION['course']);
        $this->assertEquals('4A', $_SESSION['section']);
        $this->assertEquals('4', $_SESSION['year_level']);
    }

    public function testAdvisorSpecificSessionData()
    {
        $advisorSession = [
            'user_id' => 2,
            'role' => 'advisor',
            'employee_id' => 'EMP001',
            'department' => 'Computer Science',
            'sections_handled' => 'A,B,C'
        ];

        foreach ($advisorSession as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $this->assertEquals('EMP001', $_SESSION['employee_id']);
        $this->assertEquals('Computer Science', $_SESSION['department']);
        $this->assertEquals('A,B,C', $_SESSION['sections_handled']);
    }
}
