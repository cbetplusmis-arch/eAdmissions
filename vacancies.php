<?php
require_once __DIR__ . "/includes/bootstrap.php";
$database = admissions_db();
$result = $database->query(
	"SELECT v.id, v.school_year, v.class_level, v.available_places, v.opens_on, v.closes_on,
		(SELECT COUNT(*) FROM ad_applications a WHERE a.vacancy_id = v.id AND a.status = 'Admitted') AS admitted_count
	 FROM ad_vacancies v
	 WHERE v.application_open = 1
	   AND (v.opens_on IS NULL OR v.opens_on <= CURRENT_DATE())
	   AND (v.closes_on IS NULL OR v.closes_on >= CURRENT_DATE())
	 ORDER BY v.school_year DESC, v.class_level ASC"
);
if (!$result) {
	http_response_code(500);
	die("Could not load class vacancies.");
}
require_once __DIR__ . "/includes/layout.php";
page_start("Open class vacancies");
?>
<section class="vacancies-intro">
	<p class="eyebrow"><?php echo h(SCHOOL_YEAR); ?> ADMISSIONS</p>
	<h1>Find your place at <?php echo h(school_name()); ?></h1>
	<p class="page-intro">No account is needed. Choose an open class and complete the application form in one visit.</p>
</section>
<div class="grid grid-2">
	<?php if ($result->num_rows === 0) { ?>
		<div class="card empty-state"><div class="empty-icon">◷</div><h3>Applications are closed</h3><p>No class vacancies are accepting applications at present.</p></div>
	<?php } ?>
	<?php while ($vacancy = $result->fetch_assoc()) {
		$remaining = max(0, (int) $vacancy["available_places"] - (int) $vacancy["admitted_count"]);
		$level = $vacancy["class_level"];
	?>
		<article class="card class-card">
			<div class="stat-card"><span class="class-code"><?php echo h(str_replace(".", "", $level)); ?></span><span class="places"><?php echo $remaining; ?> places remaining</span></div>
			<h3><?php echo h($level); ?> · <?php echo $level === "S.1" ? "Lower Secondary" : "Upper Secondary"; ?></h3>
			<p><?php echo $level === "S.1" ? "S.1 entry requires a PLE pass slip (aggregate 4–36)." : "S.5 entry requires an S.4 UCE pass slip (best-six aggregate 6–54) and a subject combination."; ?></p>
			<div class="muted small">School year <?php echo h($vacancy["school_year"]); ?> · Day or boarding preference</div>
			<?php if ($vacancy["closes_on"]) { ?><div class="muted small">Closing date: <?php echo h(date("j M Y", strtotime($vacancy["closes_on"]))); ?></div><?php } ?>
			<div style="margin-top:16px">
				<a class="btn" href="<?php echo h(app_url("applicant/apply.php?vacancy=" . (int) $vacancy["id"])); ?>">Apply for <?php echo h($level); ?></a>
			</div>
		</article>
	<?php } ?>
</div>
<?php page_end(); ?>
