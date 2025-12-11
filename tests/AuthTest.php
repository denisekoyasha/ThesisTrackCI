<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class AuthTest extends TestCase
{
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        
        // Mock PDO and PDOStatement
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);

        // Set up global $pdo
        $GLOBALS['pdo'] = $this->mockPdo;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($GLOBALS['pdo']);
    }

    public function testRequireRoleWithValidRole()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'student';

        require_once __DIR__ . '/../auth.php';

        $this->expectNotToPerformAssertions();
        requireRole(['student', 'advisor']);
    }

    public function testRequireRoleWithInvalidRole()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'student';

        require_once __DIR__ . '/../auth.php';

        $this->expectException(\Exception::class);
        requireRole(['advisor']);
    }

    public function testRequireRoleWithoutSession()
    {
        require_once __DIR__ . '/../auth.php';

        $this->expectException(\Exception::class);
        requireRole(['student']);
    }

    public function testGetCurrentUserWithValidSession()
    {
        $_SESSION['user_id'] = 1;

        $userData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'student'
        ];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($userData);
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with("SELECT * FROM users WHERE id = ?")
            ->willReturn($this->mockStmt);

        require_once __DIR__ . '/../auth.php';
        $result = getCurrentUser();
        $this->assertEquals($userData, $result);
    }

    public function testGetCurrentUserWithoutSession()
    {
        require_once __DIR__ . '/../auth.php';
        $this->assertNull(getCurrentUser());
    }

    public function testGetUserGroup()
    {
        $groupData = [
            'id' => 1,
            'name' => 'Group A',
            'advisor_id' => 2,
            'advisor_name' => 'Dr. Smith'
        ];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($groupData);
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        require_once __DIR__ . '/../auth.php';
        $this->assertEquals($groupData, getUserGroup(1));
    }

    public function testGetGroupMembers()
    {
        $membersData = [
            ['id' => 1, 'name' => 'John Doe', 'role_in_group' => 'leader'],
            ['id' => 2, 'name' => 'Jane Smith', 'role_in_group' => 'member']
        ];

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($membersData);
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        require_once __DIR__ . '/../auth.php';
        $this->assertEquals($membersData, getGroupMembers(1));
    }

    public function testFormatFileSize()
    {
        require_once __DIR__ . '/../auth.php';
        $this->assertEquals('1.00 KB', formatFileSize(1024));
        $this->assertEquals('1.00 MB', formatFileSize(1048576));
        $this->assertEquals('1.00 GB', formatFileSize(1073741824));
        $this->assertEquals('500 bytes', formatFileSize(500));
        $this->assertEquals('1.50 KB', formatFileSize(1536));
    }

    public function testSessionDestroy()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'student';
        
        $this->assertNotEmpty($_SESSION);
        
        // Test logout functionality
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Redirect to portal after logout");
        
        include __DIR__ . '/../logout.php';
    }
}
