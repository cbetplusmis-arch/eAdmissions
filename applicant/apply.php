<?php
require_once __DIR__ . "/../includes/bootstrap.php";

$database = admissions_db();
$vacancy_id = filter_input(INPUT_GET, "vacancy", FILTER_VALIDATE_INT);
if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$vacancy_id = filter_input(INPUT_POST, "vacancy_id", FILTER_VALIDATE_INT);
}
if (!$vacancy_id || $vacancy_id < 1) {
	http_response_code(400);
	exit("Choose a valid class vacancy before applying.");
}

$vacancy_statement = $database->prepare(
	"SELECT id, school_year, class_level, available_places, application_open, opens_on, closes_on
	 FROM ad_vacancies WHERE id = ? LIMIT 1"
);
$vacancy_statement->bind_param("i", $vacancy_id);
$vacancy_statement->execute();
$vacancy = $vacancy_statement->get_result()->fetch_assoc();
$vacancy_statement->close();
if (!$vacancy) {
	http_response_code(404);
	exit("That class vacancy could not be found.");
}

$classes = array(
	"S.1" => array("exam" => "PLE", "minimum" => 4, "maximum" => 36),
	"S.5" => array("exam" => "UCE", "minimum" => 6, "maximum" => 54)
);
$combinations = array(
	"PCM (Physics, Chemistry, Mathematics)",
	"BCM (Biology, Chemistry, Mathematics)",
	"HEG (History, Economics, Geography)",
	"HEL (History, Economics, Literature)",
	"MEG (Mathematics, Economics, Geography)"
);
$document_labels = document_labels($vacancy["class_level"]);
$errors = array();
$uploads = array();
$values = array_fill_keys(array(
	"first_name", "last_name", "date_of_birth", "gender", "district", "previous_school",
	"exam_year", "aggregate_score", "subject_combination", "guardian_name",
	"guardian_relationship", "guardian_phone", "guardian_email", "home_address",
	"boarding_preference", "special_needs"
), "");
$class = $classes[$vacancy["class_level"]];

if (!(int) $vacancy["application_open"]
	|| ($vacancy["opens_on"] && $vacancy["opens_on"] > date("Y-m-d"))
	|| ($vacancy["closes_on"] && $vacancy["closes_on"] < date("Y-m-d"))) {
	$vacancy_open = false;
	$errors[] = "Applications for this vacancy are closed.";
} else {
	$vacancy_open = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	verify_csrf();
	foreach ($values as $key => $unused) {
		$values[$key] = trim(isset($_POST[$key]) ? (string) $_POST[$key] : "");
	}
	$values["guardian_email"] = strtolower($values["guardian_email"]);

	foreach (array("first_name", "last_name", "date_of_birth", "gender", "district", "previous_school", "exam_year", "aggregate_score", "guardian_name", "guardian_relationship", "guardian_phone", "home_address", "boarding_preference") as $required) {
		if ($values[$required] === "") {
			$errors[] = "Complete all required student, guardian and previous-school details.";
			break;
		}
	}
	if (!in_array($values["gender"], array("Female", "Male", "Other"), true)) {
		$errors[] = "Choose a valid gender.";
	}
	if (!in_array($values["boarding_preference"], array("Day", "Boarding"), true)) {
		$errors[] = "Choose Day or Boarding.";
	}
	if (strlen($values["first_name"]) > 100 || strlen($values["last_name"]) > 100
		|| strlen($values["district"]) > 100 || strlen($values["previous_school"]) > 190
		|| strlen($values["guardian_name"]) > 160 || strlen($values["guardian_relationship"]) > 50
		|| strlen($values["guardian_phone"]) > 32 || strlen($values["home_address"]) > 5000) {
		$errors[] = "One or more text fields exceed the allowed length.";
	}
	$date_of_birth = valid_date_input($values["date_of_birth"], "!Y-m-d", "Y-m-d")
		? new DateTime($values["date_of_birth"])
		: false;
	if (!$date_of_birth || $date_of_birth > new DateTime("today")) {
		$errors[] = "Enter a valid date of birth.";
	}
	if (!ctype_digit($values["exam_year"]) || (int) $values["exam_year"] < 2000 || (int) $values["exam_year"] > (int) SCHOOL_YEAR + 1) {
		$errors[] = "Enter a valid PLE/UCE examination year.";
	}
	if (!ctype_digit($values["aggregate_score"]) || (int) $values["aggregate_score"] < $class["minimum"] || (int) $values["aggregate_score"] > $class["maximum"]) {
		$errors[] = "The aggregate must be between " . $class["minimum"] . " and " . $class["maximum"] . " for " . $class["exam"] . ".";
	}
	if ($vacancy["class_level"] === "S.5" && !in_array($values["subject_combination"], $combinations, true)) {
		$errors[] = "Choose a valid S.5 subject combination.";
	}
	if (!filter_var($values["guardian_email"], FILTER_VALIDATE_EMAIL) || strlen($values["guardian_email"]) > 190) {
		$errors[] = "Enter a correct email address for application updates.";
	}
	if (!isset($_POST["declaration"]) || $_POST["declaration"] !== "1") {
		$errors[] = "Confirm the application declaration before submitting.";
	}
	if (!preg_match('/^[0-9+() -]{7,32}$/', $values["guardian_phone"])) {
		$errors[] = "Enter a valid guardian telephone number.";
	}

	$existing = $database->prepare(
		"SELECT id FROM ad_applications
		 WHERE vacancy_id = ? AND guardian_email = ? AND first_name = ? AND last_name = ? AND date_of_birth = ?
		 LIMIT 1"
	);
	if (!$existing) {
		$errors[] = "Could not check for an existing application. Please try again.";
	} else {
		$existing->bind_param("issss", $vacancy_id, $values["guardian_email"], $values["first_name"], $values["last_name"], $values["date_of_birth"]);
		if (!$existing->execute()) {
			$errors[] = "Could not check for an existing application. Please try again.";
		} else {
			$already_applied = $existing->get_result()->num_rows > 0;
			if ($already_applied) {
				$errors[] = "An application for this learner and class already exists with this email address.";
			}
		}
		$existing->close();
	}

	$uploads = array();
	if (!$errors) {
		$upload_directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "admissions";
		if (!is_dir($upload_directory) && !mkdir($upload_directory, 0700, true)) {
			$errors[] = "Secure document storage is unavailable. Please contact the Admissions Office.";
		}
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		foreach ($document_labels as $document_type => $label) {
			if (!isset($_FILES["documents"]["error"][$document_type])) {
				$errors[] = "Select the required file: " . $label . ".";
				continue;
			}
			$file = array(
				"name" => $_FILES["documents"]["name"][$document_type],
				"tmp_name" => $_FILES["documents"]["tmp_name"][$document_type],
				"error" => $_FILES["documents"]["error"][$document_type],
				"size" => $_FILES["documents"]["size"][$document_type]
			);
			if ($file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
				$errors[] = "Upload a valid file for " . $label . ".";
				continue;
			}
			$size = filesize($file["tmp_name"]);
			$mime_type = $finfo->file($file["tmp_name"]);
			$allowed_types = $document_type === "passport_photo"
				? array("image/jpeg" => "jpg", "image/png" => "png")
				: array("application/pdf" => "pdf", "image/jpeg" => "jpg", "image/png" => "png");
			if ($size === false || $size < 1 || $size > 5 * 1024 * 1024 || !isset($allowed_types[$mime_type])) {
				$errors[] = $label . " must be a PDF, JPG or PNG no larger than 5 MB.";
				continue;
			}
			$storage_name = bin2hex(random_bytes(32)) . "." . $allowed_types[$mime_type];
			$stored_path = $upload_directory . DIRECTORY_SEPARATOR . $storage_name;
			if (!move_uploaded_file($file["tmp_name"], $stored_path)) {
				$errors[] = "Could not securely store " . $label . ". Please try again.";
				continue;
			}
			$uploads[] = array(
				"type" => $document_type,
				"original_name" => substr(basename($file["name"]), 0, 255),
				"storage_name" => $storage_name,
				"mime_type" => $mime_type,
				"file_size" => (int) $size,
				"path" => $stored_path
			);
		}
	}

	if (!$errors) {
		$reference_no = "AD" . SCHOOL_YEAR . "-" . str_replace(".", "", $vacancy["class_level"]) . "-" . strtoupper(bin2hex(random_bytes(4)));
		$exam_body = $class["exam"];
		$exam_year = (int) $values["exam_year"];
		$aggregate_score = (int) $values["aggregate_score"];
		$subject_combination = $vacancy["class_level"] === "S.5" ? $values["subject_combination"] : null;
		$guardian_email = $values["guardian_email"];
		$applicant_id = null;
		$special_needs = $values["special_needs"] === "" ? null : $values["special_needs"];
		$insert = $database->prepare(
			"INSERT INTO ad_applications
			(reference_no, applicant_id, vacancy_id, first_name, last_name, date_of_birth, gender, district,
			 previous_school, exam_body, exam_year, aggregate_score, subject_combination, guardian_name,
			 guardian_relationship, guardian_phone, guardian_email, home_address, boarding_preference, special_needs, status)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Under review')"
		);
		$types = "sii" . str_repeat("s", 7) . "ii" . str_repeat("s", 8);
		if (!$insert) {
			$errors[] = "The application form could not be prepared. Please contact the Admissions Office.";
		} else {
		$insert->bind_param(
			$types,
			$reference_no, $applicant_id, $vacancy_id, $values["first_name"], $values["last_name"],
			$values["date_of_birth"], $values["gender"], $values["district"], $values["previous_school"],
			$exam_body, $exam_year, $aggregate_score, $subject_combination, $values["guardian_name"],
			$values["guardian_relationship"], $values["guardian_phone"], $guardian_email,
			$values["home_address"], $values["boarding_preference"], $special_needs
		);

		if (!$database->begin_transaction()) {
			$errors[] = "The application could not be started. Please try again.";
		} elseif (!$insert->execute()) {
			$database->rollback();
			$errors[] = "The application could not be saved. Please try again or contact the Admissions Office.";
		} else {
			$application_id = (int) $database->insert_id;
			$document_insert = $database->prepare(
				"INSERT INTO ad_documents (application_id, document_type, original_name, storage_name, mime_type, file_size)
				 VALUES (?, ?, ?, ?, ?, ?)"
			);
			$document_saved = true;
			foreach ($uploads as $upload) {
				$document_insert->bind_param(
					"issssi", $application_id, $upload["type"], $upload["original_name"],
					$upload["storage_name"], $upload["mime_type"], $upload["file_size"]
				);
				if (!$document_insert->execute()) {
					$document_saved = false;
					break;
				}
			}
			$document_insert->close();
			$event = $database->prepare(
				"INSERT INTO ad_application_events (application_id, actor_role, event_type, new_status)
				 VALUES (?, 'Applicant', 'Application submitted', 'Under review')"
			);
			$event->bind_param("i", $application_id);
			if (!$document_saved || !$event->execute() || !$database->commit()) {
				$database->rollback();
				foreach ($uploads as $upload) {
					if (is_file($upload["path"])) {
						unlink($upload["path"]);
					}
				}
				$errors[] = "The application documents could not be saved. Please try again.";
			} else {
				$event->close();
				flash_message("Application submitted successfully. Your reference is " . $reference_no . ". Keep this reference; the Admissions Office may use the contact details you provided for follow-up.");
				redirect_to("index.php");
			}
		}
		$insert->close();
		}
	}
	if ($errors && $uploads) {
		foreach ($uploads as $upload) {
			if (is_file($upload["path"])) {
				unlink($upload["path"]);
			}
		}
	}
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Apply for " . $vacancy["class_level"]);
?>
<div class="card form-card">
	<p class="eyebrow">SCHOOL YEAR <?php echo h($vacancy["school_year"]); ?> · <?php echo h($vacancy["class_level"]); ?> ENTRY</p>
	<h2>Student application</h2>
	<p class="muted">No account or password needed. Complete the form, enter a working phone number and email, and upload the required documents.</p>
	<?php if ($errors) { ?><div class="alert alert-error" role="alert" aria-live="polite"><?php echo h(implode(" ", array_unique($errors))); ?></div><?php } ?>
	<?php if ($vacancy_open) { ?>
	<form id="admission-application-form" method="post" enctype="multipart/form-data" action="<?php echo h(app_url("applicant/apply.php?vacancy=" . (int) $vacancy_id)); ?>">
		<?php csrf_input(); ?>
		<input type="hidden" name="vacancy_id" value="<?php echo (int) $vacancy_id; ?>">
		<h3>Student details</h3>
		<div class="form-grid">
			<div class="field"><label for="first_name">First name <span class="required">*</span></label><input id="first_name" name="first_name" maxlength="100" required value="<?php echo h($values["first_name"]); ?>"></div>
			<div class="field"><label for="last_name">Surname <span class="required">*</span></label><input id="last_name" name="last_name" maxlength="100" required value="<?php echo h($values["last_name"]); ?>"></div>
			<div class="field"><label for="date_of_birth">Date of birth <span class="required">*</span></label><input id="date_of_birth" name="date_of_birth" type="date" max="<?php echo date("Y-m-d"); ?>" required value="<?php echo h($values["date_of_birth"]); ?>"></div>
			<div class="field"><label for="gender">Gender <span class="required">*</span></label><select id="gender" name="gender" required><option value="">Select</option><?php foreach (array("Female", "Male", "Other") as $option) { ?><option value="<?php echo h($option); ?>" <?php echo $values["gender"] === $option ? "selected" : ""; ?>><?php echo h($option); ?></option><?php } ?></select></div>
			<div class="field"><label for="district">District of residence <span class="required">*</span></label><input id="district" name="district" maxlength="100" required value="<?php echo h($values["district"]); ?>"></div>
			<div class="field"><label>Class applied for</label><input value="<?php echo h($vacancy["class_level"]); ?> · <?php echo h($class["exam"]); ?>" disabled></div>
		</div>

		<h3 style="margin-top:24px">Previous school and results</h3>
		<div class="form-grid">
			<div class="field"><label for="previous_school">Previous school <span class="required">*</span></label><input id="previous_school" name="previous_school" maxlength="190" required value="<?php echo h($values["previous_school"]); ?>"></div>
			<div class="field"><label for="exam_year"><?php echo h($class["exam"]); ?> year <span class="required">*</span></label><input id="exam_year" name="exam_year" type="number" min="2000" max="<?php echo (int) SCHOOL_YEAR + 1; ?>" required value="<?php echo h($values["exam_year"]); ?>"></div>
			<div class="field"><label for="aggregate_score"><?php echo h($class["exam"]); ?> aggregate <span class="required">*</span></label><input id="aggregate_score" name="aggregate_score" type="number" min="<?php echo $class["minimum"]; ?>" max="<?php echo $class["maximum"]; ?>" required value="<?php echo h($values["aggregate_score"]); ?>"><span class="field-hint">Enter the aggregate total (lower totals are stronger).</span></div>
			<?php if ($vacancy["class_level"] === "S.5") { ?>
				<div class="field"><label for="subject_combination">A-Level subject combination <span class="required">*</span></label><select id="subject_combination" name="subject_combination" required><option value="">Select combination</option><?php foreach ($combinations as $combination) { ?><option <?php echo $values["subject_combination"] === $combination ? "selected" : ""; ?>><?php echo h($combination); ?></option><?php } ?></select></div>
			<?php } ?>
			<div class="field"><label for="boarding_preference">Boarding preference <span class="required">*</span></label><select id="boarding_preference" name="boarding_preference" required><option value="">Select</option><option value="Day" <?php echo $values["boarding_preference"] === "Day" ? "selected" : ""; ?>>Day</option><option value="Boarding" <?php echo $values["boarding_preference"] === "Boarding" ? "selected" : ""; ?>>Boarding</option></select></div>
			<div class="field full"><label for="special_needs">Medical or learning support information</label><textarea id="special_needs" name="special_needs"><?php echo h($values["special_needs"]); ?></textarea></div>
		</div>

		<h3 class="form-section-title">Applicant / parent or guardian contact</h3>
		<div class="form-grid">
			<div class="field"><label for="guardian_name">Full name <span class="required">*</span></label><input id="guardian_name" name="guardian_name" maxlength="160" required value="<?php echo h($values["guardian_name"]); ?>"></div>
			<div class="field"><label for="guardian_relationship">Relationship <span class="required">*</span></label><input id="guardian_relationship" name="guardian_relationship" maxlength="50" required value="<?php echo h($values["guardian_relationship"]); ?>"></div>
			<div class="field"><label for="guardian_phone">Contact phone number <span class="required">*</span></label><input id="guardian_phone" name="guardian_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="32" required value="<?php echo h($values["guardian_phone"]); ?>"><span class="field-hint">Enter an active number for the applicant or guardian.</span></div>
			<div class="field"><label for="guardian_email">Correct email address <span class="required">*</span></label><input id="guardian_email" name="guardian_email" type="email" inputmode="email" autocomplete="email" maxlength="190" required value="<?php echo h($values["guardian_email"]); ?>"><span class="field-hint">Enter an address the applicant or guardian checks.</span></div>
			<div class="field full"><label for="home_address">Home address <span class="required">*</span></label><textarea id="home_address" name="home_address" required><?php echo h($values["home_address"]); ?></textarea></div>
		</div>

		<h3 style="margin-top:24px">Required documents</h3>
		<div class="form-grid">
			<?php foreach ($document_labels as $document_type => $label) { ?>
				<div class="field"><label for="doc_<?php echo h($document_type); ?>"><?php echo h($label); ?> <span class="required">*</span></label><input id="doc_<?php echo h($document_type); ?>" type="file" name="documents[<?php echo h($document_type); ?>]" accept="<?php echo $document_type === "passport_photo" ? ".jpg,.jpeg,.png,image/jpeg,image/png" : ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"; ?>" required><span class="field-hint">PDF/JPG/PNG, maximum 5 MB.</span></div>
			<?php } ?>
		</div>
		<p class="check-row" style="margin-top:18px"><input type="checkbox" name="declaration" value="1" required><span>I confirm that the information supplied is accurate and consent to its use for this admissions application.</span></p>
		<div class="form-actions"><a class="btn outline" href="<?php echo h(app_url("vacancies.php")); ?>">Back to vacancies</a><button class="btn" type="submit">Submit application</button></div>
	</form>
	<?php } elseif (!$errors) { ?>
		<div class="alert alert-warning" role="status" aria-live="polite">This vacancy is not accepting applications at the moment.</div>
	<?php } ?>
</div>
<script>
document.getElementById("admission-application-form").addEventListener("submit", function (event) {
	if (!window.confirm("Submit this application? Check that the phone number and email address are correct.")) {
		event.preventDefault();
	}
});
</script>
<?php page_end(); ?>
