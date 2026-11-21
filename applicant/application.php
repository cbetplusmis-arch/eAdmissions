<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_applicant();

$application_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$application_id || $application_id < 1) {
	http_response_code(400);
	exit("A valid application is required.");
}
$database = admissions_db();
$statement = $database->prepare(
	"SELECT a.*, v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE a.id = ? AND a.applicant_id = ?
	 LIMIT 1"
);
$applicant_id = (int) $_SESSION["applicant_id"];
$statement->bind_param("ii", $application_id, $applicant_id);
$statement->execute();
$application = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$application) {
	http_response_code(404);
	exit("The application was not found.");
}

$document_statement = $database->prepare(
	"SELECT id, document_type, original_name, uploaded_at
	 FROM ad_documents WHERE application_id = ? ORDER BY document_type"
);
$document_statement->bind_param("i", $application_id);
$document_statement->execute();
$documents = $document_statement->get_result();
$event_statement = $database->prepare(
	"SELECT event_type, old_status, new_status, created_at
	 FROM ad_application_events
	 WHERE application_id = ? AND actor_role IN ('Applicant', 'Headteacher', 'System')
	 ORDER BY created_at ASC"
);
$event_statement->bind_param("i", $application_id);
$event_statement->execute();
$events = $event_statement->get_result();

require_once __DIR__ . "/../includes/layout.php";
page_start("Application " . $application["reference_no"], "dashboard", "applicant");
?>
<div class="grid grid-2">
	<div class="card">
		<?php $application_status = application_status_label($application["status"]); ?>
		<div class="stat-card"><div><p class="eyebrow">APPLICATION REFERENCE</p><h2><?php echo h($application["reference_no"]); ?></h2></div><span class="badge <?php echo h(status_class($application_status)); ?>"><?php echo h($application_status); ?></span></div>
		<p class="muted small"><?php echo h($application["class_level"]); ?> entry · School year <?php echo h($application["school_year"]); ?> · Submitted <?php echo h(date("j M Y", strtotime($application["created_at"]))); ?></p>
		<div class="notice"><?php
			$messages = array(
				"Under review" => "The Headteacher is reviewing your application.",
				"Admitted" => "Admission has been offered. Your admission letter is ready to print.",
				"Rejected" => "The school could not offer a place for this intake."
			);
			echo h($messages[$application_status]);
		?></div>
		<?php if ($application["status"] === "Admitted") { ?><div style="margin-top:16px"><a class="btn" href="<?php echo h(app_url("applicant/admission-letter.php?id=" . (int) $application_id)); ?>">View / print admission letter</a></div><?php } ?>
		<?php if ($application["status"] === "Admitted" && $application["reporting_at"]) { ?>
			<div class="notice" style="margin-top:16px"><strong>Report to school:</strong> <?php echo h(date("l, j F Y, H:i", strtotime($application["reporting_at"]))); ?><br><strong>Venue / instructions:</strong> <?php echo nl2br(h($application["reporting_location"])); ?></div>
		<?php } ?>
	</div>
	<div class="card">
		<h3>Applicant details</h3>
		<dl class="detail-list">
			<dt>Student</dt><dd><?php echo h($application["first_name"] . " " . $application["last_name"]); ?></dd>
			<dt>Previous school</dt><dd><?php echo h($application["previous_school"]); ?></dd>
			<dt>Examination</dt><dd><?php echo h($application["exam_body"] . " · " . $application["exam_year"]); ?></dd>
			<dt>Aggregate</dt><dd><?php echo (int) $application["aggregate_score"]; ?></dd>
			<?php if ($application["subject_combination"]) { ?><dt>Combination</dt><dd><?php echo h($application["subject_combination"]); ?></dd><?php } ?>
			<dt>Guardian</dt><dd><?php echo h($application["guardian_name"] . " · " . $application["guardian_phone"]); ?></dd>
			<dt>Boarding</dt><dd><?php echo h($application["boarding_preference"]); ?></dd>
		</dl>
	</div>
</div>
<div class="section-title"><div><h2>Submitted documents</h2><p>Private files are available only to you and the Headteacher.</p></div></div>
<div class="card"><ul class="document-list">
<?php $documents_by_type = array(); while ($document = $documents->fetch_assoc()) { $documents_by_type[$document["document_type"]] = $document; } ?>
<?php foreach (document_labels($application["class_level"]) as $type => $label) { ?>
	<li><span><?php echo h($label); ?></span><?php if (isset($documents_by_type[$type])) { ?><a class="btn outline small" href="<?php echo h(app_url("applicant/document.php?id=" . (int) $documents_by_type[$type]["id"])); ?>">Download</a><?php } else { ?><span class="muted">Not found</span><?php } ?></li>
<?php } ?>
</ul></div>
<div class="section-title"><div><h2>Application updates</h2><p>Recorded decisions and status changes.</p></div></div>
<div class="card">
	<?php if ($events->num_rows === 0) { ?><p class="muted small">No updates have been recorded.</p><?php } else { ?>
	<ul class="document-list"><?php while ($event = $events->fetch_assoc()) { ?><li><span><strong><?php echo h($event["event_type"]); ?></strong><?php if ($event["new_status"]) { ?> · <?php echo h(application_status_label($event["new_status"])); ?><?php } ?></span><span class="muted"><?php echo h(date("j M Y, H:i", strtotime($event["created_at"]))); ?></span></li><?php } ?></ul>
	<?php } ?>
</div>
<?php
$document_statement->close();
$event_statement->close();
page_end("applicant");
