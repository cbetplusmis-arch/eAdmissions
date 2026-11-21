<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();

$allowed_statuses = array("Under review", "Admitted", "Rejected");
$class_filter = isset($_GET["class"]) && in_array($_GET["class"], array("S.1", "S.5"), true) ? $_GET["class"] : "";
$status_filter = isset($_GET["status"]) && in_array($_GET["status"], $allowed_statuses, true) ? $_GET["status"] : "";
$search = trim(isset($_GET["q"]) ? $_GET["q"] : "");
$database = admissions_db();
$statement = $database->prepare(
	"SELECT a.id, a.reference_no, a.first_name, a.last_name, a.status, a.aggregate_score,
		a.exam_body, a.created_at, a.guardian_phone, a.guardian_email, v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE (? = '' OR v.class_level = ?)
	   AND (? = '' OR (CASE WHEN a.status IN ('Admitted', 'Rejected') THEN a.status ELSE 'Under review' END) = ?)
	   AND (? = '' OR a.reference_no LIKE CONCAT('%', ?, '%')
			OR a.first_name LIKE CONCAT('%', ?, '%')
			OR a.last_name LIKE CONCAT('%', ?, '%')
			OR a.guardian_phone LIKE CONCAT('%', ?, '%')
			OR a.guardian_email LIKE CONCAT('%', ?, '%')
			OR a.guardian_name LIKE CONCAT('%', ?, '%'))
	 ORDER BY FIELD(CASE WHEN a.status IN ('Admitted', 'Rejected') THEN a.status ELSE 'Under review' END,
		'Under review', 'Admitted', 'Rejected'),
		a.created_at ASC"
);
if (!$statement) {
	http_response_code(500);
	die("Could not prepare the application list.");
}
$statement->bind_param("sssssssssss", $class_filter, $class_filter, $status_filter, $status_filter, $search, $search, $search, $search, $search, $search, $search);
if (!$statement->execute()) {
	http_response_code(500);
	die("Could not load applications.");
}
$applications = $statement->get_result();

require_once __DIR__ . "/../includes/layout.php";
page_start("Applications", "applications", "admin");
?>
<form class="filter-row" id="application-filters" method="get" action="<?php echo h(app_url("admin/applications.php")); ?>">
	<input type="search" name="q" value="<?php echo h($search); ?>" placeholder="Search learner, reference, phone or email" aria-label="Search applications">
	<select name="class"><option value="">All entry classes</option><option value="S.1" <?php echo $class_filter === "S.1" ? "selected" : ""; ?>>S.1</option><option value="S.5" <?php echo $class_filter === "S.5" ? "selected" : ""; ?>>S.5</option></select>
	<select name="status"><option value="">All statuses</option><?php foreach ($allowed_statuses as $status) { ?><option value="<?php echo h($status); ?>" <?php echo $status_filter === $status ? "selected" : ""; ?>><?php echo h($status); ?></option><?php } ?></select>
</form>
<script>
	(function () {
		var form = document.getElementById("application-filters");
		if (!form) {
			return;
		}
		var search = form.querySelector('input[name="q"]');
		var timer;
		function submitFilters() {
			if (typeof form.requestSubmit === "function") {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}
		search.addEventListener("input", function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(submitFilters, 400);
		});
		form.querySelectorAll("select").forEach(function (filter) {
			filter.addEventListener("change", submitFilters);
		});
	}());
</script>
<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Applicant</th><th>Entry</th><th>Exam aggregate</th><th>Received</th><th>Status</th><th></th></tr></thead><tbody>
<?php if ($applications->num_rows === 0) { ?><tr><td colspan="7">No applications match these filters.</td></tr><?php } ?>
<?php while ($application = $applications->fetch_assoc()) { ?>
	<tr>
		<td><strong><?php echo h($application["reference_no"]); ?></strong></td>
		<td><?php echo h($application["first_name"] . " " . $application["last_name"]); ?><div class="muted small"><?php echo h($application["guardian_phone"]); ?> · <?php echo h($application["guardian_email"]); ?></div></td>
		<td><?php echo h($application["class_level"]); ?> <span class="muted">(<?php echo h($application["school_year"]); ?>)</span></td>
		<td><?php echo h($application["exam_body"]); ?> · <?php echo (int) $application["aggregate_score"]; ?></td>
		<td><?php echo h(date("j M Y", strtotime($application["created_at"]))); ?></td>
		<td><span class="badge <?php echo h(status_class($application["status"])); ?>"><?php echo h(application_status_label($application["status"])); ?></span></td>
		<td><a class="btn outline small" href="<?php echo h(app_url("admin/review.php?id=" . (int) $application["id"])); ?>">Review</a></td>
	</tr>
<?php } ?>
</tbody></table></div>
<?php $statement->close(); page_end("admin"); ?>
