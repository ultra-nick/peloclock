<?php

/**
 * PeloClock Setup
 *
 * Run this script once in a browser to create the SQLite database and tables
 * required by PeloClock, and to write config.php automatically.
 *
 * DELETE THIS FILE once setup is complete.
 */

$configFile = dirname(__DIR__) . '/config.php';
$errors     = [];
$success    = false;

// Block re-runs if config already exists
if (file_exists($configFile) && filesize($configFile) > 0) {
    die('<p style="font-family:monospace;color:red;">config.php already exists. Delete it before running setup again, or delete setup.php if setup is complete.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitise input
    $dbPath    = rtrim(trim($_POST['db_path']    ?? ''), '/');
    $baseUrl   = rtrim(trim($_POST['base_url']   ?? ''), '/');
    $cachePath = rtrim(trim($_POST['cache_path'] ?? ''), '/');
    $peloUser  = trim($_POST['pelo_user'] ?? '');
    $peloPass  = $_POST['pelo_pass']      ?? '';

    // Basic validation
    if (!$dbPath)    $errors[] = 'Database path is required.';
    if (!$cachePath) $errors[] = 'Calendar cache path is required.';
    if (!$peloUser)  $errors[] = 'Peloton username is required.';
    if (!$peloPass)  $errors[] = 'Peloton password is required.';

    if (empty($errors)) {

        // Check db folder is writable
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir) || !is_writable($dbDir)) {
            $errors[] = 'Database folder does not exist or is not writable: ' . $dbDir;
        }

        // Check cache folder is writable
        if (!is_dir($cachePath) || !is_writable($cachePath)) {
            $errors[] = 'Calendar cache folder does not exist or is not writable: ' . $cachePath;
        }

    }

    if (empty($errors)) {

        // Connect to SQLite - creates the file if it doesn't exist
        try {
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $errors[] = 'Could not create SQLite database: ' . $e->getMessage();
        }

    }

    if (empty($errors)) {

        $tables = [

            'classes' => "CREATE TABLE IF NOT EXISTS classes (
                class_id           TEXT    NOT NULL,
                start_time         INTEGER NOT NULL,
                duration           INTEGER NOT NULL,
                class_type         TEXT    NOT NULL,
                difficulty         TEXT    NOT NULL,
                title              TEXT    NOT NULL,
                instructor_name    TEXT    NOT NULL,
                instructor_id      TEXT    NOT NULL,
                fitness_discipline TEXT    NOT NULL,
                description        TEXT    NOT NULL,
                language           TEXT    NOT NULL,
                explicit           INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (class_id)
            )",

            'instructors' => "CREATE TABLE IF NOT EXISTS instructors (
                instructor_id   TEXT NOT NULL,
                instructor_name TEXT NOT NULL,
                last_seen       TEXT NOT NULL,
                PRIMARY KEY (instructor_id)
            )",

            'disciplines' => "CREATE TABLE IF NOT EXISTS disciplines (
                discipline_name TEXT NOT NULL,
                last_seen       TEXT NOT NULL,
                PRIMARY KEY (discipline_name)
            )",

            'calendars' => "CREATE TABLE IF NOT EXISTS calendars (
                calendar_id         INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                instructors         TEXT    NOT NULL,
                fitness_disciplines TEXT    NOT NULL,
                durations           TEXT    NOT NULL,
                languages           TEXT    NOT NULL,
                encore              INTEGER NOT NULL DEFAULT 0,
                creation_time       TEXT    NOT NULL DEFAULT (datetime('now'))
            )",

        ];

        foreach ($tables as $name => $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                $errors[] = "Failed to create table '$name': " . $e->getMessage();
            }
        }

    }

    if (empty($errors)) {

        // Write config.php
        $config = <<<PHP
<?php
define('BASE_PATH',    __DIR__);
define('PRIVATE_PATH', __DIR__);
define('PUBLIC_PATH',  __DIR__ . '/public');
define('BASE_URL',     '$baseUrl');

define('DB_PATH',    '$dbPath');

define('CACHE_PATH', '$cachePath');

define('PELOTON_USER', '$peloUser');
define('PELOTON_PASS', '$peloPass');

define('CLASS_DURATIONS', [5, 10, 15, 20, 30, 45, 60, 75, 90, 120, 150]);

try {
    \$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\\PDOException \$e) {
    error_log('Database connection failed: ' . \$e->getMessage());
    exit;
}
PHP;

        if (file_put_contents($configFile, $config) === false) {
            $errors[] = 'Failed to write config.php - check the folder is writable.';
        } else {
            $success = true;
        }

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PELOCLOCK Setup</title>
    <style>
        body       { font-family: monospace; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        h1         { font-size: 1.4em; }
        label      { display: block; margin-top: 12px; font-weight: bold; }
        small      { display: block; font-weight: normal; margin-bottom: 2px; color: #555; }
        input[type=text],
        input[type=password] { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; }
        input[type=submit]   { margin-top: 20px; padding: 10px 20px; cursor: pointer; }
        .errors  { color: red; margin-top: 16px; }
        .success { color: green; margin-top: 16px; }
        .warning { color: red; font-weight: bold; }
        fieldset { margin-top: 20px; border: 1px solid #ccc; padding: 12px; }
        legend   { font-weight: bold; padding: 0 6px; }
    </style>
</head>
<body>

<h1>PELOCLOCK Setup</h1>

<?php if ($success): ?>

    <p class="success">&#10003; Setup complete.</p>
    <ul>
        <li>SQLite database created at <strong><?php echo htmlspecialchars($dbPath); ?></strong></li>
        <li>Tables created: classes, instructors, disciplines, calendars</li>
        <li>config.php written</li>
    </ul>
    <p class="warning">&#9888; Delete setup.php now. Leaving it on your server is a security risk.</p>

<?php else: ?>

    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">

        <fieldset>
            <legend>Database</legend>
            <label>SQLite Database Path & File Name
                <small>Absolute path and filename where the database file will be created.</small>
                <small>Must be outside the web root and in a writable folder.</small>
                <small>Example: /var/www/peloclock/peloclock.sqlite</small>
                <input type="text" name="db_path" value="<?php echo htmlspecialchars($_POST['db_path'] ?? ''); ?>">
            </label>
        </fieldset>

        <fieldset>
            <legend>Calendar Cache</legend>
            <label>Cache Folder Path
                <small>Absolute path to a writable folder where generated .ics calendar files will be stored.</small>
                <small>Must be outside the web root.</small>
                <small>Example: /var/www/peloclock/calendars</small>
                <input type="text" name="cache_path" value="<?php echo htmlspecialchars($_POST['cache_path'] ?? ''); ?>">
            </label>
        </fieldset>

        <fieldset>
            <legend>App Settings</legend>
            <label>Base URL
                <small>Leave blank if the site is at the domain root.</small>
                <small>Example: blank for https://example.com/ or /peloclock for https://example.com/peloclock/</small>
                <input type="text" name="base_url" value="<?php echo htmlspecialchars($_POST['base_url'] ?? ''); ?>">
            </label>
        </fieldset>

        <fieldset>
            <legend>Peloton Account</legend>
            <label>Username (email)
                <input type="text" name="pelo_user" value="<?php echo htmlspecialchars($_POST['pelo_user'] ?? ''); ?>">
            </label>
            <label>Password
                <input type="password" name="pelo_pass">
            </label>
        </fieldset>

        <input type="submit" value="Run Setup">

    </form>

<?php endif; ?>

</body>
</html>
