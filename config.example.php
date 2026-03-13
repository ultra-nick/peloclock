<?php
define('BASE_PATH',    __DIR__);
define('PRIVATE_PATH', __DIR__);
define('PUBLIC_PATH',  __DIR__ . '/public');
define('BASE_URL',     '');                  // Leave blank if site is at the domain root
                                             // Example: '/peloclock' for https://example.com/peloclock/

define('DB_PATH',    __DIR__ . '/peloclock.sqlite');

define('CACHE_PATH', __DIR__ . '/calendars');  // Must be writable, outside web root

define('PELOTON_USER', 'your@email.com');
define('PELOTON_PASS', 'your-password');

define('CLASS_DURATIONS', [5, 10, 15, 20, 30, 45, 60, 75, 90, 120, 150]);

try {
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    exit;
}
