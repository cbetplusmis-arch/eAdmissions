<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();

$application_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$statement = admissions_db()->prepare(
	"SELECT a.reference_no, a.first_name, a.last_name, a.interview_at, a.interview_location,
		v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE a.id = ? AND a.status = 'Shortlisted' AND a.interview_at IS NOT NULL
	 LIMIT 1"
);
if (!$application_id || $application_id < 1 || !$statement) {
	http_response_code(404);
	exit("An interview pass is not available for this application.");
}
$statement->bind_param("i", $application_id);
if (!$statement->execute()) {
	http_response_code(500);
	exit("Could not load the shortlisted application.");
}
$interview = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$interview) {
	http_response_code(404);
	exit("The Headteacher has not issued an interview pass for this application.");
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Interview pass slip", "applications", "admin");
?>
<div class="letter-content">
	<div class="print-title"><strong><?php echo h(school_name()); ?></strong><span>ADMISSIONS INTERVIEW PASS · SCHOOL YEAR <?php echo h($interview["school_year"]); ?></span></div>
	<p><strong>Application reference:</strong> <?php echo h($interview["reference_no"]); ?></p>
	<p><strong>Applicant:</strong> <?php echo h($interview["first_name"] . " " . $interview["last_name"]); ?></p>
	<p><strong>Entry class:</strong> <?php echo h($interview["class_level"]); ?></p>
	<div class="notice"><strong>Interview:</strong> <?php echo h(date("l, j F Y · H:i", strtotime($interview["interview_at"]))); ?><br><strong>Venue:</strong> <?php echo h($interview["interview_location"]); ?></div>
	<p>Please arrive 15 minutes before the appointment. Bring this pass and the original examination and identity documents listed in the application.</p>
	<div class="form-actions no-print"><button class="btn gold" type="button" onclick="window.print()">Print pass slip</button><a class="btn outline" href="<?php echo h(app_url("admin/review.php?id=" . (int) $application_id)); ?>">Back to application</a></div>
</div>
<?php page_end("admin"); ?>
