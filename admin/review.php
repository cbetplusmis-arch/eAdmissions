<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_once __DIR__ . "/../includes/mailer.php";
require_headteacher();

$application_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$application_id || $application_id < 1) {
	http_response_code(400);
	exit("A valid application is required.");
}
$database = admissions_db();
$statuses = array("Under review", "Admitted", "Rejected");
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	verify_csrf();
	$new_status = isset($_POST["status"]) ? $_POST["status"] : "";
	$reporting_input = trim(isset($_POST["reporting_at"]) ? $_POST["reporting_at"] : "");
	$reporting_location = trim(isset($_POST["reporting_location"]) ? $_POST["reporting_location"] : "");
	$reporting_at = null;
	if ($reporting_input !== "") {
		if (!valid_date_input($reporting_input, "!Y-m-d\TH:i", "Y-m-d\TH:i")) {
			$error = "Enter a valid reporting date and time.";
		} else {
			$parsed_reporting = DateTime::createFromFormat("!Y-m-d\TH:i", $reporting_input);
			$reporting_at = $parsed_reporting->format("Y-m-d H:i:s");
		}
	}
	if (!in_array($new_status, $statuses, true)) {
		$error = "Choose a valid application decision.";
	} elseif (strlen($reporting_location) > 190) {
		$error = "The reporting location is too long.";
	} elseif ($new_status === "Admitted" && ($reporting_at === null || $reporting_location === "")) {
		$error = "Set a reporting date, time and location before admitting the applicant.";
	}

	if ($error === "") {
		$current_statement = $database->prepare(
			"SELECT a.status, a.vacancy_id, a.reference_no, a.first_name, a.last_name,
				a.guardian_name, a.guardian_email, a.reporting_at, a.reporting_location,
				v.class_level, v.school_year
			 FROM ad_applications a
			 JOIN ad_vacancies v ON v.id = a.vacancy_id
			 WHERE a.id = ? LIMIT 1"
		);
		if (!$current_statement) {
			$error = "Could not load the current application status.";
		} else {
			$current_statement->bind_param("i", $application_id);
			if (!$current_statement->execute()) {
				$error = "Could not load the current application status.";
			} else {
				$current = $current_statement->get_result()->fetch_assoc();
			}
			$current_statement->close();
		}
		if ($error === "" && !$current) {
			http_response_code(404);
			exit("The application was not found.");
		}
		if ($error === "") {
			$old_status = $current["status"];
			$vacancy_id = (int) $current["vacancy_id"];
		}

		if ($error === "" && $new_status === "Admitted" && $old_status !== "Admitted") {
			$capacity_statement = $database->prepare(
				"SELECT v.available_places, SUM(a.status = 'Admitted') AS admitted_count
				 FROM ad_vacancies v LEFT JOIN ad_applications a ON a.vacancy_id = v.id
				 WHERE v.id = ? GROUP BY v.id"
			);
			$capacity_statement->bind_param("i", $vacancy_id);
			$capacity_statement->execute();
			$capacity = $capacity_statement->get_result()->fetch_assoc();
			$capacity_statement->close();
			if (!$capacity || (int) $capacity["admitted_count"] >= (int) $capacity["available_places"]) {
				$error = "The class vacancy is full. Increase its capacity before admitting another applicant.";
			}
		}

		if ($error === "") {
			$update = $database->prepare(
				"UPDATE ad_applications
				 SET status = ?, reporting_at = ?, reporting_location = ?
				 WHERE id = ?"
			);
			if (!$update) {
				http_response_code(500);
				die("Could not prepare the application update.");
			}
			$update->bind_param("sssi", $new_status, $reporting_at, $reporting_location, $application_id);
			if (!$database->begin_transaction()) {
				$error = "Could not start the decision update. Please try again.";
			} elseif (!$update->execute()) {
				$database->rollback();
				$error = "Could not save this decision. Please try again.";
			} else {
				$headteacher_id = (int) $_SESSION["headteacher_id"];
				$event_type = $new_status === $old_status ? "Review updated" : "Decision recorded";
				$schedule_changed = $new_status === "Admitted"
					&& ($reporting_at !== $current["reporting_at"]
						|| $reporting_location !== (string) $current["reporting_location"]);
				$send_notification = $new_status !== $old_status || $schedule_changed;
				$event = $database->prepare(
					"INSERT INTO ad_application_events
						(application_id, actor_hmd_id, actor_role, event_type, old_status, new_status)
					 VALUES (?, ?, 'Headteacher', ?, ?, ?)"
				);
				$event->bind_param("iisss", $application_id, $headteacher_id, $event_type, $old_status, $new_status);
				if (!$event->execute() || !$database->commit()) {
					$database->rollback();
					$error = "The decision history could not be saved. No update was applied.";
				} else {
					$event->close();
					$update->close();
					if ($send_notification) {
						$notification = $current;
						$notification["reporting_at"] = $reporting_at;
						$notification["reporting_location"] = $reporting_location;
						$mail_error = "";
						if (send_admissions_status_email($notification, $new_status, $mail_error)) {
							flash_message("Application update saved and an email was sent to " . $current["guardian_email"] . ".");
						} else {
							flash_message(
								"Application update was saved, but the email could not be sent: " . $mail_error,
								"error"
							);
						}
					} else {
						flash_message("Application review updated. No status or relevant schedule change required an email.");
					}
					redirect_to("admin/review.php?id=" . (int) $application_id);
				}
				$event->close();
			}
			$update->close();
		}
	}
}

$detail_statement = $database->prepare(
	"SELECT a.*, v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE a.id = ? LIMIT 1"
);
$detail_statement->bind_param("i", $application_id);
$detail_statement->execute();
$application = $detail_statement->get_result()->fetch_assoc();
$detail_statement->close();
if (!$application) {
	http_response_code(404);
	exit("The application was not found.");
}
$document_statement = $database->prepare("SELECT id, document_type, original_name FROM ad_documents WHERE application_id = ? ORDER BY document_type");
$document_statement->bind_param("i", $application_id);
$document_statement->execute();
$documents = $document_statement->get_result();
$reporting_value = $application["reporting_at"] ? date("Y-m-d\TH:i", strtotime($application["reporting_at"])) : "";
$reporting_location_value = $application["reporting_location"];
$status_value = application_status_label($application["status"]);
if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$reporting_value = isset($_POST["reporting_at"]) ? trim((string) $_POST["reporting_at"]) : $reporting_value;
	$reporting_location_value = isset($_POST["reporting_location"]) ? trim((string) $_POST["reporting_location"]) : $reporting_location_value;
	if (isset($_POST["status"]) && in_array($_POST["status"], $statuses, true)) {
		$status_value = $_POST["status"];
	}
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Review application", "applications", "admin");
?>
<div class="grid grid-2">
	<div class="card">
		<p class="eyebrow"><?php echo h($application["reference_no"]); ?> · <?php echo h($application["class_level"]); ?> ENTRY</p>
		<h2><?php echo h($application["first_name"] . " " . $application["last_name"]); ?></h2>
		<p class="muted small">Submitted <?php echo h(date("j M Y, H:i", strtotime($application["created_at"]))); ?></p>
		<dl class="detail-list">
			<dt>Date of birth</dt><dd><?php echo h(date("j M Y", strtotime($application["date_of_birth"]))); ?></dd>
			<dt>Gender / district</dt><dd><?php echo h($application["gender"] . " · " . $application["district"]); ?></dd>
			<dt>Previous school</dt><dd><?php echo h($application["previous_school"]); ?></dd>
			<dt>Exam / aggregate</dt><dd><?php echo h($application["exam_body"] . " " . $application["exam_year"] . " · " . $application["aggregate_score"]); ?></dd>
			<?php if ($application["subject_combination"]) { ?><dt>S.5 combination</dt><dd><?php echo h($application["subject_combination"]); ?></dd><?php } ?>
			<dt>Guardian</dt><dd><?php echo h($application["guardian_name"] . " · " . $application["guardian_relationship"]); ?></dd>
			<dt>Applicant / guardian phone</dt><dd><?php echo h($application["guardian_phone"]); ?></dd>
			<dt>Applicant / guardian email</dt><dd><?php echo h($application["guardian_email"]); ?></dd>
			<dt>Address</dt><dd><?php echo nl2br(h($application["home_address"])); ?></dd>
			<dt>Boarding</dt><dd><?php echo h($application["boarding_preference"]); ?></dd>
			<dt>Support notes</dt><dd><?php echo $application["special_needs"] ? nl2br(h($application["special_needs"])) : "None provided"; ?></dd>
		</dl>
	</div>
	<div class="card">
		<h3>Applicant documents</h3>
		<ul class="document-list">
		<?php $documents_by_type = array(); while ($document = $documents->fetch_assoc()) { $documents_by_type[$document["document_type"]] = $document; } ?>
		<?php foreach (document_labels($application["class_level"]) as $type => $label) { ?>
			<li>
				<span><?php echo h($label); ?></span>
				<?php if (isset($documents_by_type[$type])) { ?>
					<span class="document-actions">
						<a class="btn outline small" href="<?php echo h(app_url("admin/document.php?id=" . (int) $documents_by_type[$type]["id"] . "&view=1")); ?>" target="_blank" rel="noopener noreferrer">View</a>
						<a class="btn outline small" href="<?php echo h(app_url("admin/document.php?id=" . (int) $documents_by_type[$type]["id"])); ?>">Download</a>
					</span>
				<?php } else { ?><span class="muted">Missing</span><?php } ?>
			</li>
		<?php } ?>
		</ul>
		<div class="notice" style="margin-top:16px">Admitted applicants receive a printable admission letter.</div>
		<?php if ($application["status"] === "Admitted") { ?><p><a class="btn" href="<?php echo h(app_url("admin/admission-letter.php?id=" . (int) $application_id)); ?>">Print admission letter</a></p><?php } ?>
	</div>
</div>
<div class="section-title"><div><h2>Record a decision</h2><p>Changing an application status automatically emails the guardian with the decision and applicable schedule.</p></div></div>
<div class="card form-card">
	<?php if ($error !== "") { ?><div class="alert alert-error" role="alert" aria-live="polite"><?php echo h($error); ?></div><?php } ?>
	<form method="post" action="<?php echo h(app_url("admin/review.php?id=" . (int) $application_id)); ?>" onsubmit="return confirm('Save this application decision and email the guardian if the status changes?');">
		<?php csrf_input(); ?>
		<div class="form-grid">
			<div class="field"><label for="status">Application status</label><select id="status" name="status"><?php foreach ($statuses as $status) { ?><option value="<?php echo h($status); ?>" <?php echo $status_value === $status ? "selected" : ""; ?>><?php echo h($status); ?></option><?php } ?></select></div>
			<div class="field review-schedule" data-schedule="reporting" <?php echo $status_value === "Admitted" ? "" : "hidden"; ?>>
				<label for="reporting_at">School reporting date and time</label>
				<input id="reporting_at" name="reporting_at" type="datetime-local" value="<?php echo h($reporting_value); ?>">
			</div>
			<div class="field review-schedule" data-schedule="reporting" <?php echo $status_value === "Admitted" ? "" : "hidden"; ?>>
				<label for="reporting_location">Reporting venue / instructions</label>
				<input id="reporting_location" name="reporting_location" maxlength="190" value="<?php echo h($reporting_location_value); ?>">
			</div>
		</div>
		<div class="form-actions"><a class="btn outline" href="<?php echo h(app_url("admin/applications.php")); ?>">Back to applications</a><button class="btn" type="submit">Save decision</button></div>
	</form>
</div>
<script>
	(function () {
		var status = document.getElementById("status");
		var form = status ? status.form : null;
		if (!status || !form) {
			return;
		}
		function updateScheduleFields() {
			var selected = status.value;
			form.querySelectorAll('[data-schedule="reporting"]').forEach(function (field) {
				var visible = selected === "Admitted";
				field.hidden = !visible;
				field.querySelector("input").required = visible;
			});
		}
		status.addEventListener("change", updateScheduleFields);
		updateScheduleFields();
	}());
</script>
<?php $document_statement->close(); page_end("admin"); ?>
