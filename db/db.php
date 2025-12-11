<?php

// If testing mode → use Fake PDO instead of real DB
if (getenv('APP_ENV') === 'testing') {

    class FakeStatement {
        private $results;
        private $executed = false;

        public function __construct($results = []) {
            $this->results = $results;
        }

        public function execute($params = []) {
            $this->executed = true;
            return true;
        }

        public function fetch() {
            return $this->results[0] ?? false;
        }

        public function fetchAll() {
            return $this->results;
        }

        public function rowCount() {
            return count($this->results);
        }
    }

    class FakePDO {
        private $fakeResponses = [];

        // Allows tests to register responses
        public function setMockResult($queryKey, $resultArray) {
            $this->fakeResponses[$queryKey] = $resultArray;
        }

        public function prepare($query) {
            $key = md5($query);
            $results = $this->fakeResponses[$key] ?? [];
            return new FakeStatement($results);
        }

        public function query($query) {
            $key = md5($query);
            $results = $this->fakeResponses[$key] ?? [];
            return new FakeStatement($results);
        }
    }

    // Create global fake PDO object
    $pdo = new FakePDO();
    $GLOBALS['pdo'] = $pdo;  // ← ADD THIS LINE
    $conn = null; // not used in tests

} else {

    // Normal live mode (your original code)
    $host = 'sql.freedb.tech';
    $port = 3306;
    $dbname = 'freedb_thesistrack';
    $username = 'freedb_thesisuser';
    $password = 'T737dspm$?FzWzR';

    // MySQLi
    $conn = new mysqli($host, $username, $password, $dbname, $port);

    try {
        // Create PDO connection
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);

        // Set PDO attributes
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $pdo->query('SELECT 1');

        // Make PDO available globally for consistency
        $GLOBALS['pdo'] = $pdo;  // ← ADD THIS LINE TOO (optional but recommended)

    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());

        if (strpos($e->getMessage(), 'Access denied') !== false) {
            die("Database access denied. Please check username and password.");
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            die("Database '$dbname' not found. Please create the database first.");
        } else {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

function sanitize($data) {
    if ($data === null) {
        return null;
    }
    $data = stripslashes($data);
    $data = trim($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generatePassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charactersLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }

    return $password;
}

?>