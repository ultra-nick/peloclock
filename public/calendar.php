<?php
require_once dirname(__DIR__) . '/config.php';

// init vars
$cache = false;
$ics = "";

// get id and options
if (isset($_GET['id'])) {
	$id = $_GET['id'];
	// get id details
	$stmt = $pdo->prepare("SELECT instructors, fitness_disciplines, durations, languages, encore FROM calendars WHERE calendar_id = ?");
	$stmt->execute([(int) $id]);
	$row = $stmt->fetch();
	if (!$row) {
	    exit;
	}
	$instructors        = $row['instructors'];
	$fitnessDisciplines = $row['fitness_disciplines'];
	$durations          = $row['durations'];
	$languages          = $row['languages'];
	$encore             = $row['encore'];
} else { // exit if id not set
	exit;
}

// check for cache file
if (file_exists(CACHE_PATH . '/' . (int) $id . '.ics')) {
    $cache = true;
}

// calendar headers
header("Content-Type: text/calendar; charset=utf-8");

// output cache file if exists
if ($cache === true) {
	$ics = file_get_contents(CACHE_PATH . '/' . (int) $id . '.ics');
	// output calendar
	echo $ics;
	exit;
}

// explode stored comma-separated values into arrays
$instructorArr        = explode(',', $instructors);
$durationArr          = explode(',', $durations);
$fitnessDisciplineArr = explode(',', $fitnessDisciplines);
$languageArr          = explode(',', $languages);

// build IN() placeholders dynamically
$inInstructors        = implode(',', array_fill(0, count($instructorArr), '?'));
$inDurations          = implode(',', array_fill(0, count($durationArr), '?'));
$inFitnessDisciplines = implode(',', array_fill(0, count($fitnessDisciplineArr), '?'));
$inLanguages          = implode(',', array_fill(0, count($languageArr), '?'));

$params = array_merge($instructorArr, $durationArr, $fitnessDisciplineArr, $languageArr);

if ($encore == 0) {
    $sql = "SELECT * FROM classes WHERE instructor_id IN($inInstructors) AND duration IN($inDurations) AND fitness_discipline IN($inFitnessDisciplines) AND language IN($inLanguages) AND class_type = 'Live' ORDER BY start_time ASC";
} else {
    $sql = "SELECT * FROM classes WHERE instructor_id IN($inInstructors) AND duration IN($inDurations) AND fitness_discipline IN($inFitnessDisciplines) AND language IN($inLanguages) ORDER BY start_time ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// set default timezone to utc or dateToCal function will use server timezone
date_default_timezone_set('UTC');

// converts a unix timestamp to an ics-friendly format and adds Z to end for utc so user's calendar adjusts to their local timezone
function dateToCal($timestamp) {
	return date('Ymd\THis\Z', $timestamp);
}

// create calendar if no cache file
$ics .= "BEGIN:VCALENDAR
CALSCALE:GREGORIAN
METHOD:PUBLISH
PRODID:-//pel//iCal 1.0//EN
VERSION:2.0
REFRESH-INTERVAL;VALUE=DURATION:PT6H
X-APPLE-CALENDAR-COLOR:#000000
X-WR-CALNAME;VALUE=TEXT:Peloton Class Schedule\n";
$results = $stmt->fetchAll();
if ($results) {
    foreach ($results as $row) {
   		$ics .= "BEGIN:VEVENT\n";	
		$ics .= "TRANSP:TRANSPARENT\n";
		$ics .= "LOCATION:" . mb_strtoupper($row['instructor_name']) . " · " . mb_strtoupper($row['fitness_discipline']) . "\n";
		$ics .= "UID:" . $row['class_id'] . "@pel\n";
		$ics .= "SUMMARY:" . mb_strtoupper($row['class_type']) . " · " . $row['title'] . "\n";
		$ics .= "DTSTAMP:" .  dateToCal(time()) . "\n";
		$ics .= "DTSTART:" . dateToCal($row['start_time']) . "\n";
		$ics .= "DTEND:" . dateToCal($row['start_time'] + $row['duration'] ) . "\n";
		$explicit = "";
		if ($row['explicit'] == "1") {
			$explicit =  " · EXPLICIT";
		}
		$description = str_replace(array("\r","\n"), '', $row['description']); // remove empty lines from some descriptions
		if (strlen($description) < 1) {
			$description = "No description.";
		}
		$description = "DESCRIPTION:" . mb_strtoupper($row['language']) . $explicit . "\\n" . $description;
		// description field is folded as > 75 characters
		$descriptionArray = mb_str_split($description, 70, "UTF-8"); // multibyte safe split - use 70 instead of 75 just to be safe
		$description = ""; // empty $description
		foreach ($descriptionArray as $value) {
			$description .= $value . "\n ";
		}
		$description = trim($description);
		$ics .= $description . "\n";
		$ics .= "END:VEVENT\n";
	}
}	

// calendar footers
$ics .= "END:VCALENDAR";

// fix validator crlf issues
$ics = str_replace("\n", "\r\n", $ics);

// create cache file
file_put_contents(CACHE_PATH . '/' . (int) $id . '.ics', $ics);

// output calendar
echo $ics;
