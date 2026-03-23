<?php
require_once dirname(__DIR__) . '/config.php';

// check form submission options
$selectedCheckAll = [];
$selectedInstructorIds = [];
$selectedFitnessDisciplines = [];
$selectedDurations = [];
$selectedLanguages = [];
$encore = 0;

if (isset($_POST["check_all"])) {
	$selectedCheckAll = $_POST["check_all"];
}
if (isset($_POST["instructor_ids"])) {
	$selectedInstructorIds = $_POST["instructor_ids"];
}
if (isset($_POST["fitness_disciplines"])) {
	$selectedFitnessDisciplines = $_POST["fitness_disciplines"];
}
if (isset($_POST["durations"])) {
	$selectedDurations = $_POST["durations"];
}
if (isset($_POST["languages"])) {
	$selectedLanguages = $_POST["languages"];
} else {
	$selectedLanguages[] = "English";
}
if (isset($_POST["encore"])) {
	$encore = 1;
}

// create calendar database entry
$calendarUrl = "";
if (!empty($selectedInstructorIds) && !empty($selectedFitnessDisciplines) && !empty($selectedDurations)) {
	$concatInstructorIds = "";
	$concatFitnessDisciplines = "";
	$concatDurations = "";	
	$concatLanguages = "";	
	foreach ($selectedInstructorIds as $key => $val) {
	    $concatInstructorIds .= strip_tags(trim($val));
	    if ($key !== array_key_last($selectedInstructorIds)) {
	        $concatInstructorIds .= ",";
	    }
	}
	foreach ($selectedFitnessDisciplines as $key => $val) {
	    $concatFitnessDisciplines .= strip_tags(trim($val));
	    if ($key !== array_key_last($selectedFitnessDisciplines)) {
	        $concatFitnessDisciplines .= ",";
	    }
	}
	foreach ($selectedDurations as $key => $val) {
	    $concatDurations .= strip_tags(trim($val));
	    if ($key !== array_key_last($selectedDurations)) {
	        $concatDurations .= ",";
	    }
	}
	foreach ($selectedLanguages as $key => $val) {
	    $concatLanguages .= strip_tags(trim($val));
	    if ($key !== array_key_last($selectedLanguages)) {
	        $concatLanguages .= ",";
	    }
	}
	$stmt = $pdo->prepare("INSERT INTO calendars (instructors, fitness_disciplines, durations, languages, encore) VALUES (?, ?, ?, ?, ?)");
	$result = $stmt->execute([$concatInstructorIds, $concatFitnessDisciplines, $concatDurations, $concatLanguages, $encore]);
	if ($result) {
	    $calendarId = $pdo->lastInsertId();
	    $host = $_SERVER['HTTP_HOST'];
	    $calendarUrl = "webcal://" . $host . BASE_URL . "/calendar.ics?id=" . $calendarId;
	}
}
require PRIVATE_PATH . '/header.php';
?>
		<p>Choose your options below, then create your bespoke Peloton classes calendar.</p>
		<script>
			$(document).ready(function(){
				
				// instructors
				$("#checkAllInstructor").change(function () {
				    $("input.instructor:checkbox").prop('checked', $(this).prop("checked"));
				});
				
				$(".instructor").change(function () {
				  if($(".instructor").length==$(".instructor:checked").length)
				    $("#checkAllInstructor").prop('checked', true);
				  else
				    $("#checkAllInstructor").prop('checked', false);
				});
	
				// fitness disciplines
				$("#checkAllFitness").change(function () {
				    $("input.fitness:checkbox").prop('checked', $(this).prop("checked"));
				});
				
				$(".fitness").change(function () {
				  if($(".fitness").length==$(".fitness:checked").length)
				    $("#checkAllFitness").prop('checked', true);
				  else
				    $("#checkAllFitness").prop('checked', false);
				});
			
				// durations
				$("#checkAllDuration").change(function () {
				    $("input.duration:checkbox").prop('checked', $(this).prop("checked"));
				});
				
				$(".duration").change(function () {
				  if($(".duration").length==$(".duration:checked").length)
				    $("#checkAllDuration").prop('checked', true);
				  else
				    $("#checkAllDuration").prop('checked', false);
				});
				
				// move to calendar link if present
				const $anchor = $('#calendarAnchor');
				if ($anchor.length) {
					$('html, body').animate({
	        			scrollTop: $('#calendarAnchor').offset().top
	   			 	}, 'slow');
	   			}
	
			});
		</script>
		<form method="post" action="<?php echo BASE_URL; ?>/">
			<fieldset>
				<legend><strong>Instructor:</strong></legend>
<?php
$stmt = $pdo->query("SELECT instructor_id, instructor_name FROM instructors ORDER BY instructor_name ASC");
if ($stmt) { // check there are any results
?>
				<label><input type="checkbox" id="checkAllInstructor" name="check_all[]" value="instructor"<?php if (in_array("instructor", $selectedCheckAll)) { echo " checked"; } ?>>&nbsp;<strong>ALL/NONE</strong></label>
				</br><br>
<?php
	while ($row = $stmt->fetch()) {
?>
		  		<label><input type="checkbox" class="instructor" name="instructor_ids[]" value="<?php echo $row['instructor_id']; ?>"<?php if (in_array($row['instructor_id'], $selectedInstructorIds)) { echo " checked"; } ?>>&nbsp;<?php echo $row['instructor_name']; ?></label>
		  		<br>
<?php
	}
}
?>
		   	</fieldset>
			<fieldset>
				<legend><strong>Fitness Discipline:</strong></legend>
<?php
$stmt = $pdo->query("SELECT discipline_name FROM disciplines ORDER BY discipline_name ASC");
if ($stmt) { // check there are any results
?>
				<label><input type="checkbox" id="checkAllFitness" name="check_all[]" value="fitness"<?php if (in_array("fitness", $selectedCheckAll)) { echo " checked"; } ?>>&nbsp;<strong>ALL/NONE</strong></label>
				<br><br>
<?php
	while ($row = $stmt->fetch()) {
?>
				<label><input type="checkbox" class="fitness" name="fitness_disciplines[]" value="<?php echo $row['discipline_name']; ?>"<?php if (in_array($row['discipline_name'], $selectedFitnessDisciplines)) { echo " checked"; } ?>>&nbsp;<?php echo $row['discipline_name']; ?></label>
		  		<br>
<?php
	}
}
?>
		   	</fieldset>
			<fieldset>
				<legend><strong>Duration:</strong></legend>
				<label><input type="checkbox" id="checkAllDuration" name="check_all[]" value="duration"<?php if (in_array("duration", $selectedCheckAll)) { echo " checked"; } ?>>&nbsp;<strong>ALL/NONE</strong></label>
				</br></br>
<?php
foreach (CLASS_DURATIONS as $minutes) {
    $seconds = $minutes * 60;
?>
			    <label><input type="checkbox" class="duration" name="durations[]" value="<?php echo $seconds; ?>"<?php if (in_array($seconds, $selectedDurations)) { echo " checked"; } ?>>&nbsp;<?php echo $minutes; ?></label>
		  		<br>
<?php
}
?>
		   	</fieldset>
			<fieldset>
				<legend><strong>Language:</strong></legend>
		  		<label><input type="checkbox" name="languages[]" value="English"<?php if (in_array("English", $selectedLanguages)) { echo " checked"; } ?>>&nbsp;English</label>
		  		<br>
		  		<label><input type="checkbox" name="languages[]" value="German"<?php if (in_array("German", $selectedLanguages)) { echo " checked"; } ?>>&nbsp;German</label>
		  		<br>
		  		<label><input type="checkbox" name="languages[]" value="Spanish"<?php if (in_array("Spanish", $selectedLanguages)) { echo " checked"; } ?>>&nbsp;Spanish</label>
		   	</fieldset>
			<fieldset>
				<legend><strong>Encore/Premiere:</strong></legend>
		  		<label><input type="checkbox" name="encore" value="1"<?php if ($encore == "1") { echo " checked"; } ?>>&nbsp;Include Encore/Premiere</label>
		  		<br>
		   	</fieldset>
			<fieldset>
				<input type="submit" value="Create Calendar..." style="width: 100%;">
		   	</fieldset>
<?php
if ($calendarUrl != "") {
	echo "			<fieldset id=\"calendarAnchor\">\n";
} else {
	echo "			<fieldset>\n";
}
?>
		   		<legend>Calendar Link:</legend>
<?php
if ($calendarUrl != "") {
	echo "				<div style=\"text-align: center;\"><a href=\"" . $calendarUrl . "\">Your Calendar Link!</a></div>\n";
} else {
	echo "				<div style=\"text-align: center;\">Calendar Link Will Appear Here!</div>\n";
}
?>
		   	</fieldset>
		</form>
<?php
require PRIVATE_PATH . '/footer.php';
