<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

$tokensFile = dirname(__DIR__) . '/peloton_tokens.json';
$stored     = file_exists($tokensFile) ? json_decode(file_get_contents($tokensFile)) : null;

$auth = new PelotonAuth(
    PELOTON_USER,
    PELOTON_PASS,
    $stored->access_token  ?? null,
    $stored->refresh_token ?? null
);

$tokens = $auth->getTokenData();

file_put_contents($tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));

// Get classes for the next 2 weeks
$startTime = strtotime(date('Y-m-d H:00:00')); // floor to exact hour of scheduled run
$endTime   = $startTime + 1209600;

$url = "https://api.onepeloton.com/api/v3/ride/live?exclude_complete=true&content_provider=studio"
     . "&exclude_live_in_studio_only=true&ignore_class_language_preferences=true"
     . "&start={$startTime}&end={$endTime}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $tokens->access_token,
        'Peloton-Platform: web',
    ],
]);

$classes = json_decode(curl_exec($ch), true);

$calendarFile = dirname(__DIR__) . '/calendar.json';
file_put_contents($calendarFile, json_encode($classes));

// Read file of current peloton classes
if (file_exists($calendarFile)) {
    $result  = file_get_contents($calendarFile);
    $classes = json_decode($result, true);
    if (count($classes['data']) > 0) {
        $pdo->exec("DELETE FROM classes");
    } else {
        exit;
    }
} else {
    exit;
}

foreach ($classes['data'] as $class) {

    // Get start time
    $startTime = $class['scheduled_start_time'];

    // Live / Premiere / Encore
    $classType = "Live";
    if ($class['live_class_category'] == "premiere") {
        $classType = "Premiere";
    } elseif ($class['is_encore'] === true) {
        $classType = "Encore";
    }

    // Match ride details
    $found = false;
    foreach ($classes['rides'] as $ride) {

        if ($class['ride_id'] !== $ride['id']) {
            continue;
        }

        $found = true;

        $classId = strip_tags(trim($class['id']));

        // Language
        if ($ride['origin_locale'] == "de-DE") {
            $language = "German";
        } elseif ($ride['origin_locale'] == "es-ES") {
            $language = "Spanish";
        } else {
            $language = "English";
        }

        // Instructor
        $instructorName = "Unknown";
        $instructorId   = "";
        foreach ($classes['instructors'] as $instructor) {
            if ($ride['instructor_id'] == $instructor['id']) {
                $instructorName = strip_tags(trim($instructor['name']));
                $instructorId   = strip_tags(trim($instructor['id']));
                break;
            }
        }

        // Duration
        $duration = $ride['duration'];
        if (!is_int($duration) || $duration < 1) {
            continue 2;
        }

        $difficulty        = strip_tags(trim((string)($ride['difficulty_level'] ?? '')));
        $fitnessDiscipline = strip_tags(trim($ride['fitness_discipline_display_name']));
        $title             = strip_tags(trim($ride['title']));
        $description       = strip_tags(trim($ride['description']));
        $explicit          = ($ride['is_explicit'] === true) ? "1" : "0";
    }

    if ($found === false) {
        continue;
    }

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO instructors (instructor_id, instructor_name, last_seen) VALUES (?, ?, datetime('now'))");
    $stmt->execute([$instructorId, $instructorName]);

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO disciplines (discipline_name, last_seen) VALUES (?, datetime('now'))");
    $stmt->execute([$fitnessDiscipline]);

    $stmt = $pdo->prepare("INSERT INTO classes (class_id, start_time, duration, class_type, difficulty, title, instructor_name, instructor_id, fitness_discipline, description, language, explicit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$classId, $startTime, $duration, $classType, $difficulty, $title, $instructorName, $instructorId, $fitnessDiscipline, $description, $language, $explicit]);
}

// Remove instructors not seen in 3 months
$pdo->exec("DELETE FROM instructors WHERE last_seen < datetime('now', '-3 months')");

// Remove disciplines not seen in 6 months
$pdo->exec("DELETE FROM disciplines WHERE last_seen < datetime('now', '-6 months')");

// Delete old cache files
foreach (glob(dirname(__DIR__) . '/calendars/*.ics') as $file) {
    unlink($file);
}
