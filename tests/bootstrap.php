<?php
/**
 * PHPUnit Bootstrap File
 * 
 * This file is loaded before any tests run.
 * It sets up the test environment and defines constants.
 */

// Define test environment constant
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set default timezone to avoid warnings
date_default_timezone_set('Asia/Manila');

// Ensure session is not auto-started in CLI
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');

// Create test directories if they don't exist
$testDirs = [
    __DIR__ . '/test_uploads',
    __DIR__ . '/../uploads/chapters',
    __DIR__ . '/../.phpunit.cache'
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Helper function to mock $_SERVER superglobal for tests
function setupTestServer(array $overrides = []): void
{
    $_SERVER = array_merge([
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'localhost',
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => 80,
        'REQUEST_URI' => '/',
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1'
    ], $overrides);
}

// Initialize test server environment
setupTestServer();
