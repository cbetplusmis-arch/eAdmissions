<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();
$database = admissions_db();
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["close_vacancy_id"])) {
	verify_csrf();
	$close_vacancy_id = filter_input(INPUT_POST, "close_vacancy_id", FILTER_VALIDATE_INT);
	if (!$close_vacancy_id || $close_vacancy_id < 1) {
		$error = "Choose a valid vacancy to close.";
	} else {
		$close = $database->prepare(
			"UPDATE ad_vacancies SET application_open = 0 WHERE id = ? AND application_open = 1"
		);
		if (!$close) {
			$error = "Could not prepare the vacancy closure.";
		} else {
			$close->bind_param("i", $close_vacancy_id);
			if (!$close->execute()) {
				$error = "Could not close this vacancy. Please try again.";
			} elseif ($close->affected_rows !== 1) {
				$error = "The selected vacancy is already closed or could not be found.";
			} else {
				$close->close();
				flash_message("Applications for this vacancy have been closed manually.");
				redirect_to("admin/vacancies.php");
			}
			$close->close();
		}
	}
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reopen_vacancy_id"])) {
	verify_csrf();
	$reopen_vacancy_id = filter_input(INPUT_POST, "reopen_vacancy_id", FILTER_VALIDATE_INT);
	$reopen_closes_on = trim((string) (isset($_POST["reopen_closes_on"]) ? $_POST["reopen_closes_on"] : ""));
	if (!$reopen_vacancy_id || $reopen_vacancy_id < 1
		|| !valid_date_input($reopen_closes_on, "!Y-m-d", "Y-m-d")
		|| $reopen_closes_on < date("Y-m-d")) {
		$error = "Choose a valid new closing date that is today or later.";
	} else {
		$lookup = $database->prepare("SELECT class_level, school_year FROM ad_vacancies WHERE id = ? LIMIT 1");
		if (!$lookup) {
			$error = "Could not find the vacancy to reopen.";
		} else {
			$lookup->bind_param("i", $reopen_vacancy_id);
			if (!$lookup->execute()) {
				$lookup->close();
				$error = "Could not load the vacancy to reopen.";
			} else {
				$vacancy_to_reopen = $lookup->get_result()->fetch_assoc();
				$lookup->close();
			}
			if ($error === "" && !$vacancy_to_reopen) {
				$error = "The selected vacancy could not be found.";
			} elseif ($error === "") {
				$reopen = $database->prepare(
					"UPDATE ad_vacancies SET application_open = 1, opens_on = NULL, closes_on = ? WHERE id = ?"
				);
				if (!$reopen) {
					$error = "Could not prepare the vacancy reopening.";
				} else {
					$reopen->bind_param("si", $reopen_closes_on, $reopen_vacancy_id);
					if (!$reopen->execute()) {
						$error = "Could not reopen this vacancy. Please try again.";
					} else {
						$reopen->close();
						flash_message(
							$vacancy_to_reopen["class_level"] . " applications are open through "
							. date("j M Y", strtotime($reopen_closes_on)) . "."
						);
						redirect_to("admin/vacancies.php");
					}
					$reopen->close();
				}
			}
		}
	}
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["reopen_vacancy_id"]) && !isset($_POST["close_vacancy_id"])) {
	verify_csrf();
	$school_year = trim(isset($_POST["school_year"]) ? $_POST["school_year"] : "");
	$class_level = isset($_POST["class_level"]) ? $_POST["class_level"] : "";
	$available_places = filter_input(INPUT_POST, "available_places", FILTER_VALIDATE_INT);
	$application_open = isset($_POST["application_open"]) ? 1 : 0;
	$opens_on = trim(isset($_POST["opens_on"]) ? $_POST["opens_on"] : "");
	$closes_on = trim(isset($_POST["closes_on"]) ? $_POST["closes_on"] : "");

	if (!preg_match('/^\d{4}$/', $school_year) || !in_array($class_level, array("S.1", "S.5"), true)
		|| $available_places === false || $available_places < 1 || $available_places > 5000) {
		$error = "Enter a four-digit school year, a valid entry class and a capacity between 1 and 5,000.";
	} elseif (($opens_on !== "" && !valid_date_input($opens_on, "!Y-m-d", "Y-m-d"))
		|| ($closes_on !== "" && !valid_date_input($closes_on, "!Y-m-d", "Y-m-d"))
		|| ($opens_on !== "" && $closes_on !== "" && $closes_on < $opens_on)) {
		$error = "Check the opening and closing dates.";
	} else {
		$opens_on = $opens_on === "" ? null : $opens_on;
		$closes_on = $closes_on === "" ? null : $closes_on;
		$headteacher_id = (int) $_SESSION["headteacher_id"];
		$admitted_statement = $database->prepare(
			"SELECT COUNT(a.id) AS admitted_count
			 FROM ad_vacancies v
			 LEFT JOIN ad_applications a ON a.vacancy_id = v.id AND a.status = 'Admitted'
			 WHERE v.school_year = ? AND v.class_level = ?"
		);
		if (!$admitted_statement) {
			$error = "Could not check the current admissions capacity.";
		} else {
			$admitted_statement->bind_param("ss", $school_year, $class_level);
			if (!$admitted_statement->execute()) {
				$error = "Could not check the current admissions capacity.";
			} else {
				$admitted_count = (int) $admitted_statement->get_result()->fetch_assoc()["admitted_count"];
				if ($available_places < $admitted_count) {
					$error = "Capacity cannot be lower than the number of learners already admitted.";
				}
			}
			$admitted_statement->close();
		}
		if ($error === "") {
			$statement = $database->prepare(
				"INSERT INTO ad_vacancies
				(school_year, class_level, available_places, application_open, opens_on, closes_on, created_by_hmd_id)
			 VALUES (?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE available_places = VALUES(available_places),
				application_open = VALUES(application_open), opens_on = VALUES(opens_on), closes_on = VALUES(closes_on)"
			);
			if (!$statement) {
				$error = "Could not prepare the vacancy update.";
			} else {
				$statement->bind_param("ssiissi", $school_year, $class_level, $available_places, $application_open, $opens_on, $closes_on, $headteacher_id);
				if ($statement->execute()) {
					flash_message($class_level . " vacancy settings saved for " . $school_year . ".");
					$statement->close();
					redirect_to("admin/vacancies.php");
				}
				$error = "Could not save this vacancy. Please try again.";
				$statement->close();
			}
		}
	}
}

$vacancies = $database->query(
	"SELECT v.id, v.school_year, v.class_level, v.available_places, v.application_open, v.opens_on, v.closes_on,
		COUNT(a.id) AS applicant_count,
		SUM(a.status = 'Admitted') AS admitted_count
	 FROM ad_vacancies v
	 LEFT JOIN ad_applications a ON a.vacancy_id = v.id
	 GROUP BY v.id
	 ORDER BY v.school_year DESC, v.class_level ASC"
);
if (!$vacancies) {
	http_response_code(500);
	die("Could not load vacancy records.");
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Class vacancies", "vacancies", "admin");
?>
<div class="card form-card">
	<h2>Add or update a vacancy</h2>
	<p class="muted small">Use the same school year and class to update its capacity or dates. You can close applications manually at any time, or let the closing date close them automatically.</p>
	<?php if ($error !== "") { ?><div class="alert alert-error" role="alert" aria-live="polite"><?php echo h($error); ?></div><?php } ?>
	<form method="post" action="<?php echo h(app_url("admin/vacancies.php")); ?>" onsubmit="return confirm('Save these vacancy settings?');">
		<?php csrf_input(); ?>
		<div class="form-grid">
			<div class="field"><label for="school_year">School year</label><input id="school_year" name="school_year" inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?php echo h(SCHOOL_YEAR); ?>" required></div>
			<div class="field"><label for="class_level">Entry class</label><select id="class_level" name="class_level" required><option value="S.1">S.1 · Lower Secondary</option><option value="S.5">S.5 · Upper Secondary</option></select></div>
			<div class="field"><label for="available_places">Total capacity</label><input id="available_places" name="available_places" type="number" min="1" max="5000" value="100" required></div>
			<div class="field"><label for="opens_on">Opening date (optional)</label><input id="opens_on" name="opens_on" type="date"></div>
			<div class="field"><label for="closes_on">Closing date (optional)</label><input id="closes_on" name="closes_on" type="date"></div>
			<div class="field"><label class="check-row" for="application_open"><input id="application_open" name="application_open" type="checkbox" value="1" checked><span>Accept applications for this vacancy</span></label></div>
		</div>
		<div class="form-actions"><button class="btn" type="submit">Save vacancy</button></div>
	</form>
</div>
<div class="section-title"><div><h2>Configured vacancies</h2><p>Remaining places equal total capacity minus admitted learners. Other application statuses do not reduce the count.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>School year</th><th>Entry class</th><th>Capacity</th><th>Applicants</th><th>Admitted</th><th>Remaining</th><th>Application window</th><th>Actions</th></tr></thead><tbody>
<?php if ($vacancies->num_rows === 0) { ?><tr><td colspan="8">No vacancies have been created.</td></tr><?php } ?>
<?php while ($vacancy = $vacancies->fetch_assoc()) { ?>
	<?php
	$today = date("Y-m-d");
	$admitted_count = (int) $vacancy["admitted_count"];
	$remaining_places = max(0, (int) $vacancy["available_places"] - $admitted_count);
	$window_open = (int) $vacancy["application_open"] === 1
		&& (!$vacancy["opens_on"] || $vacancy["opens_on"] <= $today)
		&& (!$vacancy["closes_on"] || $vacancy["closes_on"] >= $today);
	?>
	<tr>
		<td><?php echo h($vacancy["school_year"]); ?></td>
		<td><?php echo h($vacancy["class_level"]); ?></td>
		<td><?php echo (int) $vacancy["available_places"]; ?></td>
		<td><?php echo (int) $vacancy["applicant_count"]; ?></td>
		<td><?php echo $admitted_count; ?></td>
		<td><strong><?php echo $remaining_places; ?></strong></td>
		<td>
			<?php
			$application_open = (int) $vacancy["application_open"] === 1;
			$status_label = !$application_open
				? "Manually closed"
				: (($vacancy["opens_on"] && $vacancy["opens_on"] > $today)
					? "Not open yet"
					: (($vacancy["closes_on"] && $vacancy["closes_on"] < $today) ? "Closed by date" : "Open"));
			?>
			<span class="badge <?php echo $window_open ? "admitted" : "rejected"; ?>"><?php echo h($status_label); ?></span>
			<?php if ($vacancy["opens_on"] || $vacancy["closes_on"]) { ?><div class="muted small"><?php echo h($vacancy["opens_on"] ?: "Now"); ?> – <?php echo h($vacancy["closes_on"] ?: "No closing date"); ?></div><?php } ?>
		</td>
		<td>
			<?php if (!$window_open) { ?>
				<form method="post" action="<?php echo h(app_url("admin/vacancies.php")); ?>" class="reopen-form" onsubmit="return confirm('Reopen applications until the selected closing date?');">
					<?php csrf_input(); ?>
					<input type="hidden" name="reopen_vacancy_id" value="<?php echo (int) $vacancy["id"]; ?>">
					<label class="field-hint" for="reopen-<?php echo (int) $vacancy["id"]; ?>">New closing date</label>
					<input id="reopen-<?php echo (int) $vacancy["id"]; ?>" type="date" name="reopen_closes_on" min="<?php echo h($today); ?>" required>
					<button class="btn small" type="submit">Reopen</button>
				</form>
			<?php } else { ?>
				<form method="post" action="<?php echo h(app_url("admin/vacancies.php")); ?>" class="close-vacancy-form" onsubmit="return confirm('Close applications for this vacancy now?');">
					<?php csrf_input(); ?>
					<input type="hidden" name="close_vacancy_id" value="<?php echo (int) $vacancy["id"]; ?>">
					<button class="btn small" type="submit">Close</button>
				</form>
			<?php } ?>
		</td>
	</tr>
<?php } ?>
</tbody></table></div>
<?php page_end("admin"); ?>
