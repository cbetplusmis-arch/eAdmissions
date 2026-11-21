<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();

$application_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$statement = admissions_db()->prepare(
	"SELECT a.reference_no, a.first_name, a.last_name, a.guardian_name, a.created_at,
		a.reporting_at, a.reporting_location,
		v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE a.id = ? AND a.status = 'Admitted'
	 LIMIT 1"
);
if (!$application_id || $application_id < 1 || !$statement) {
	http_response_code(404);
	exit("An admission letter is not available for this application.");
}
$statement->bind_param("i", $application_id);
if (!$statement->execute()) {
	http_response_code(500);
	exit("Could not load the admitted application.");
}
$admission = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$admission) {
	http_response_code(404);
	exit("An admission letter is available only after an offer has been made.");
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Admission letter", "applications", "admin");
?>
<div class="letter-content">
	<div class="print-title"><strong><?php echo h(school_name()); ?></strong><span>OFFICIAL ADMISSION OFFER · SCHOOL YEAR <?php echo h($admission["school_year"]); ?></span></div>
	<p><?php echo h(date("j F Y")); ?></p>
	<p><strong>Reference:</strong> <?php echo h($admission["reference_no"]); ?></p>
	<p>Dear <?php echo h($admission["guardian_name"]); ?>,</p>
	<p>We are pleased to offer <strong><?php echo h($admission["first_name"] . " " . $admission["last_name"]); ?></strong> a place in <strong><?php echo h($admission["class_level"]); ?></strong> at <?php echo h(school_name()); ?> for the <?php echo h($admission["school_year"]); ?> school year.</p>
	<p>Please present this letter together with the original examination result, birth record and recommendation from the previous school during registration.</p>
	<?php if ($admission["reporting_at"]) { ?>
		<p><strong>Please report to school on <?php echo h(date("l, j F Y, H:i", strtotime($admission["reporting_at"]))); ?> at <?php echo h($admission["reporting_location"]); ?>.</strong></p>
	<?php } else { ?>
		<p>The Admissions Office will confirm the reporting date and any school requirements directly.</p>
	<?php } ?>
	<p>We look forward to welcoming your family to our school community.</p>
	<div class="signature">Yours sincerely,<br><br>The Headteacher<br><?php echo h(school_name()); ?></div>
	<div class="form-actions no-print"><button class="btn" type="button" onclick="window.print()">Print / save as PDF</button><a class="btn outline" href="<?php echo h(app_url("admin/review.php?id=" . (int) $application_id)); ?>">Back to application</a></div>
</div>
<?php page_end("admin"); ?>
