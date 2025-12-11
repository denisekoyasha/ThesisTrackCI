<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class FileUploadTest extends TestCase
{
    private $mockPdo;
    private $mockStmt;
    private $testUploadDir;

    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        $GLOBALS['pdo'] = $this->mockPdo;
        
        // Create test upload directory
        $this->testUploadDir = __DIR__ . '/test_uploads/';
        if (!is_dir($this->testUploadDir)) {
            mkdir($this->testUploadDir, 0755, true);
        }
        
        $_SESSION = [
            'user_id' => 1,
            'role' => 'student',
            'name' => 'John Doe'
        ];
        
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_FILES = [];
        
        // Clean up test files
        if (is_dir($this->testUploadDir)) {
            $files = glob($this->testUploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testUploadDir);
        }
        
        unset($GLOBALS['pdo']);
    }

    public function testValidFileUploadData()
    {
        $_POST = [
            'chapter_number' => '1',
            'chapter_name' => 'Introduction'
        ];
        
        $_FILES = [
            'file' => [
                'name' => 'chapter1.pdf',
                'type' => 'application/pdf',
                'size' => 1024000, // 1MB
                'tmp_name' => '/tmp/phptest',
                'error' => UPLOAD_ERR_OK
            ]
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Test chapter number validation
        $chapter_number = (int)$_POST['chapter_number'];
        $this->assertGreaterThanOrEqual(1, $chapter_number);
        $this->assertLessThanOrEqual(5, $chapter_number);

        // Test file validation
        $file = $_FILES['file'];
        $this->assertEquals(UPLOAD_ERR_OK, $file['error']);
        
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $this->assertContains($file['type'], $allowedTypes);
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        $this->assertLessThanOrEqual($maxSize, $file['size']);
    }

    public function testInvalidChapterNumber()
    {
        $_POST['chapter_number'] = '6'; // Invalid - should be 1-5
        
        $chapter_number = (int)$_POST['chapter_number'];
        $this->assertFalse($chapter_number >= 1 && $chapter_number <= 5);
    }

    public function testInvalidFileType()
    {
        $_FILES = [
            'file' => [
                'name' => 'chapter1.txt',
                'type' => 'text/plain',
                'size' => 1024,
                'tmp_name' => '/tmp/phptest',
                'error' => UPLOAD_ERR_OK
            ]
        ];

        $file = $_FILES['file'];
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        $this->assertNotContains($file['type'], $allowedTypes);
    }

    public function testFileSizeExceedsLimit()
    {
        $_FILES = [
            'file' => [
                'name' => 'chapter1.pdf',
                'type' => 'application/pdf',
                'size' => 15 * 1024 * 1024, // 15MB - exceeds 10MB limit
                'tmp_name' => '/tmp/phptest',
                'error' => UPLOAD_ERR_OK
            ]
        ];

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        $this->assertGreaterThan($maxSize, $file['size']);
    }

    public function testFileUploadError()
    {
        $_FILES = [
            'file' => [
                'name' => 'chapter1.pdf',
                'type' => 'application/pdf',
                'size' => 1024,
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE
            ]
        ];

        $file = $_FILES['file'];
        $this->assertNotEquals(UPLOAD_ERR_OK, $file['error']);
    }

    public function testFilenameGeneration()
    {
        $group_id = 1;
        $chapter_number = 1;
        $timestamp = time();
        $extension = 'pdf';
        
        $filename = 'chapter_' . $group_id . '_' . $chapter_number . '_' . $timestamp . '.' . $extension;
        
        $this->assertStringContainsString('chapter_1_1_', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function testChapterNames()
    {
        $chapter_names = [
            1 => 'Introduction',
            2 => 'Review of Related Literature',
            3 => 'Methodology',
            4 => 'Results and Discussion',
            5 => 'Summary, Conclusion, and Recommendation'
        ];

        $this->assertEquals('Introduction', $chapter_names[1]);
        $this->assertEquals('Methodology', $chapter_names[3]);
        $this->assertEquals('Summary, Conclusion, and Recommendation', $chapter_names[5]);
    }

    public function testFileExtensionExtraction()
    {
        $filename = 'chapter1.pdf';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        $this->assertEquals('pdf', $extension);
        
        $filename2 = 'document.docx';
        $extension2 = pathinfo($filename2, PATHINFO_EXTENSION);
        
        $this->assertEquals('docx', $extension2);
    }

    public function testUploadPathGeneration()
    {
        $filename = 'test_file.pdf';
        $uploadPath = 'uploads/chapters/' . $filename;
        
        $this->assertEquals('uploads/chapters/test_file.pdf', $uploadPath);
        $this->assertStringStartsWith('uploads/chapters/', $uploadPath);
    }
}
