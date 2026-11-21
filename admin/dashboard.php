<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();

$database = admissions_db();
$summary = $database->query(
	"SELECT COUNT(*) AS total,
		SUM(status NOT IN ('Admitted', 'Rejected')) AS under_review,
		SUM(status = 'Admitted') AS admitted,
		SUM(status = 'Rejected') AS rejected
	 FROM ad_applications"
);
if (!$summary) {
	http_response_code(500);
	die("Could not load admissions statistics.");
}
$counts = $summary->fetch_assoc();
$vacancy_count = $database->query(
	"SELECT COUNT(*) AS total FROM ad_vacancies
	 WHERE application_open = 1
	   AND (opens_on IS NULL OR opens_on <= CURRENT_DATE())
	   AND (closes_on IS NULL OR closes_on >= CURRENT_DATE())"
);
if (!$vacancy_count) {
	http_response_code(500);
	die("Could not load vacancy statistics.");
}
$open_vacancies = (int) $vacancy_count->fetch_assoc()["total"];
$recent = $database->query(
	"SELECT a.id, a.reference_no, a.first_name, a.last_name, a.status, a.created_at,
		a.guardian_phone, a.guardian_email, v.class_level
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 ORDER BY a.created_at DESC LIMIT 8"
);
if (!$recent) {
	http_response_code(500);
	die("Could not load recent applications.");
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Admissions overview", "dashboard", "admin");
?>
<div class="grid grid-3">
	<div class="card stat-card"><div><div class="stat-label">Total applications</div><div class="stat-value"><?php echo (int) $counts["total"]; ?></div></div><span class="stat-mark">▤</span></div>
	<div class="card stat-card"><div><div class="stat-label">Under review</div><div class="stat-value"><?php echo (int) $counts["under_review"]; ?></div></div><span class="stat-mark">◷</span></div>
	<div class="card stat-card"><div><div class="stat-label">Admitted</div><div class="stat-value"><?php echo (int) $counts["admitted"]; ?></div></div><span class="stat-mark">✓</span></div>
	<div class="card stat-card"><div><div class="stat-label">Rejected</div><div class="stat-value"><?php echo (int) $counts["rejected"]; ?></div></div><span class="stat-mark">×</span></div>
	<div class="card stat-card"><div><div class="stat-label">Open vacancies</div><div class="stat-value"><?php echo $open_vacancies; ?></div></div><span class="stat-mark">＋</span></div>
</div>
<div class="section-title"><div><h2>Recent applications</h2><p>Review and update decisions from one place.</p></div><a class="btn small" href="<?php echo h(app_url("admin/applications.php")); ?>">View all applications</a></div>
<?php if ($recent->num_rows === 0) { ?>
	<div class="card empty-state"><div class="empty-icon">▤</div><h3>No applications received yet</h3><p>Open a class vacancy and applicants can submit applications through the public portal.</p><a class="btn" href="<?php echo h(app_url("admin/vacancies.php")); ?>">Manage vacancies</a></div>
<?php } else { ?>
	<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Applicant</th><th>Entry</th><th>Received</th><th>Status</th><th></th></tr></thead><tbody>
	<?php while ($application = $recent->fetch_assoc()) { ?>
		<tr><td><strong><?php echo h($application["reference_no"]); ?></strong></td><td><?php echo h($application["first_name"] . " " . $application["last_name"]); ?><div class="muted small"><?php echo h($application["guardian_phone"]); ?> · <?php echo h($application["guardian_email"]); ?></div></td><td><?php echo h($application["class_level"]); ?></td><td><?php echo h(date("j M Y", strtotime($application["created_at"]))); ?></td><td><span class="badge <?php echo h(status_class($application["status"])); ?>"><?php echo h(application_status_label($application["status"])); ?></span></td><td><a class="btn outline small" href="<?php echo h(app_url("admin/review.php?id=" . (int) $application["id"])); ?>">Review</a></td></tr>
	<?php } ?>
	</tbody></table></div>
<?php } ?>
<?php page_end("admin"); ?>
