<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_applicant();

$database = admissions_db();
$statement = $database->prepare(
	"SELECT a.id, a.reference_no, a.status, a.created_at, a.interview_at,
		a.interview_location, v.class_level, v.school_year
	 FROM ad_applications a
	 JOIN ad_vacancies v ON v.id = a.vacancy_id
	 WHERE a.applicant_id = ?
	 ORDER BY a.created_at DESC"
);
if (!$statement) {
	http_response_code(500);
	die("Could not load your applications.");
}
$applicant_id = (int) $_SESSION["applicant_id"];
$statement->bind_param("i", $applicant_id);
if (!$statement->execute()) {
	http_response_code(500);
	die("Could not load your applications.");
}
$applications = $statement->get_result();

require_once __DIR__ . "/../includes/layout.php";
page_start("My applications", "dashboard", "applicant");
?>
<div class="grid grid-3">
	<div class="card stat-card"><div><div class="stat-label">Applications submitted</div><div class="stat-value"><?php echo $applications->num_rows; ?></div></div><span class="stat-mark">▤</span></div>
	<div class="card stat-card"><div><div class="stat-label">Applicant account</div><div class="stat-value" style="font-size:15px"><?php echo h($_SESSION["full_name"]); ?></div></div><span class="stat-mark">◎</span></div>
	<div class="card stat-card"><div><div class="stat-label">Next step</div><div class="stat-value" style="font-size:13px">Check your status</div></div><span class="stat-mark">→</span></div>
</div>
<div class="section-title"><div><h2>Your applications</h2><p>Only you and the Headteacher can access these records.</p></div><a class="btn small" href="<?php echo h(app_url("vacancies.php")); ?>">Browse vacancies</a></div>
<?php if ($applications->num_rows === 0) { ?>
	<div class="card empty-state"><div class="empty-icon">✦</div><h3>Your next step starts here</h3><p>There are no submitted applications on this account yet.</p><a class="btn" href="<?php echo h(app_url("vacancies.php")); ?>">View open vacancies</a></div>
<?php } else { ?>
	<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Entry class</th><th>School year</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody>
	<?php while ($application = $applications->fetch_assoc()) { ?>
		<tr>
			<td><strong><?php echo h($application["reference_no"]); ?></strong></td>
			<td><?php echo h($application["class_level"]); ?></td>
			<td><?php echo h($application["school_year"]); ?></td>
			<td><?php echo h(date("j M Y", strtotime($application["created_at"]))); ?></td>
			<td><span class="badge <?php echo h(status_class($application["status"])); ?>"><?php echo h(application_status_label($application["status"])); ?></span></td>
			<td><a class="btn outline small" href="<?php echo h(app_url("applicant/application.php?id=" . (int) $application["id"])); ?>">View</a></td>
		</tr>
	<?php } ?>
	</tbody></table></div>
<?php } ?>
<?php
$statement->close();
page_end("applicant");
