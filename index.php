<?php
require_once __DIR__ . "/includes/bootstrap.php";
$profile = school_profile();
$database = admissions_db();
$vacancies = $database->query(
	"SELECT v.id, v.school_year, v.class_level, v.available_places,
		(SELECT COUNT(*) FROM ad_applications a WHERE a.vacancy_id = v.id AND a.status = 'Admitted') AS admitted_count
	 FROM ad_vacancies v
	 WHERE v.application_open = 1
	   AND (v.opens_on IS NULL OR v.opens_on <= CURRENT_DATE())
	   AND (v.closes_on IS NULL OR v.closes_on >= CURRENT_DATE())
	 ORDER BY v.school_year DESC, v.class_level ASC"
);
if (!$vacancies) {
	http_response_code(500);
	die("Could not load the current class vacancies.");
}
$open_vacancies = array();
while ($vacancy = $vacancies->fetch_assoc()) {
	if (!isset($open_vacancies[$vacancy["class_level"]])) {
		$open_vacancies[$vacancy["class_level"]] = $vacancy;
	}
}

require_once __DIR__ . "/includes/layout.php";
page_start("Admissions for " . SCHOOL_YEAR);
?>
<section class="home-hero">
	<div class="home-hero-copy">
		<p class="home-kicker"><?php echo h(SCHOOL_YEAR); ?> ADMISSIONS · S.1 &amp; S.5</p>
		<h1>Welcome to <span><?php echo h($profile["school_name"]); ?></span></h1>
		<p class="home-lead">A warm welcome to learners and families. Begin your Senior 1 or Senior 5 application in one simple visit—no account or password required.</p>
		<div class="actions">
			<a class="btn home-apply-button" href="<?php echo h(app_url("vacancies.php")); ?>">Apply for admission <span aria-hidden="true">→</span></a>
			<a class="btn outline" href="<?php echo h(app_url("fees.php")); ?>">Fees and requirements</a>
		</div>
		<div class="home-quick-points"><span>✓ No account needed</span><span>✓ Secure document upload</span></div>
	</div>
	<div class="home-hero-photo">
		<img src="<?php echo h(app_url("assets/school-campus.jpg")); ?>" alt="School buildings surrounded by gardens" fetchpriority="high">
		<div class="home-photo-caption">
			<span class="home-caption-mark"><img src="<?php echo h(app_url("assets/lawrence-college-crest.jpg")); ?>" alt=""></span>
			<span><strong><?php echo h($profile["school_name"]); ?></strong><small><?php echo h($profile["motto"]); ?></small></span>
		</div>
		<div class="home-photo-badge"><strong><?php echo h(SCHOOL_YEAR); ?></strong><span>NEW INTAKE</span></div>
	</div>
</section>

<section class="home-school-strip" aria-label="School information">
	<div><span class="home-strip-icon" aria-hidden="true">✦</span><span><strong><?php echo h($profile["motto"]); ?></strong><small>Our school motto</small></span></div>
	<div><span class="home-strip-icon" aria-hidden="true">⌖</span><span><strong><?php echo h($profile["location"]); ?></strong><small><?php echo h($profile["pobox"]); ?></small></span></div>
	<div><span class="home-strip-icon" aria-hidden="true">✓</span><span><strong>S.1 and S.5</strong><small>Applications welcome</small></span></div>
</section>

<section class="home-section">
	<div class="home-section-heading">
		<div><p class="home-kicker">CHOOSE YOUR ENTRY</p><h2>Find the right class</h2></div>
		<a class="home-text-link" href="<?php echo h(app_url("vacancies.php")); ?>">View all vacancies <span aria-hidden="true">→</span></a>
	</div>
	<div class="home-vacancy-grid">
		<?php foreach (array("S.1", "S.5") as $level) {
			$vacancy = isset($open_vacancies[$level]) ? $open_vacancies[$level] : null;
			$exam = $level === "S.1" ? "PLE" : "UCE";
			$places = $vacancy ? max(0, (int) $vacancy["available_places"] - (int) $vacancy["admitted_count"]) : 0;
		?>
			<article class="home-vacancy-card">
				<div class="home-class-icon"><?php echo h(str_replace(".", "", $level)); ?></div>
				<div class="home-vacancy-content">
					<p><?php echo $level === "S.1" ? "LOWER SECONDARY" : "UPPER SECONDARY"; ?></p>
					<h3><?php echo h($level); ?> entry</h3>
					<span><?php echo $level === "S.1" ? "PLE pass slip required" : "S.4 UCE pass slip required"; ?><?php if ($vacancy) { ?> · <?php echo $places; ?> places available<?php } ?></span>
				</div>
				<a class="btn home-card-button" href="<?php echo h(app_url($vacancy ? "applicant/apply.php?vacancy=" . (int) $vacancy["id"] : "vacancies.php")); ?>"><?php echo $vacancy ? "Apply now" : "View details"; ?></a>
			</article>
		<?php } ?>
	</div>
</section>

<section class="home-how">
	<div class="home-section-heading">
		<div><p class="home-kicker">FOUR SIMPLE STEPS</p><h2>How to apply</h2></div>
		<p class="home-how-intro">Prepare your documents, apply online, and watch for your admission update.</p>
	</div>
	<div class="home-steps">
		<div><span>01</span><strong>Choose S.1 or S.5</strong><p>Check the entry class and examination requirements.</p></div>
		<div><span>02</span><strong>Complete the form</strong><p>Provide learner details and a working phone number and email.</p></div>
		<div><span>03</span><strong>Submit for review</strong><p>The Admissions Office will review your application.</p></div>
		<div><span>04</span><strong>Wait for confirmation</strong><p>Wait for an SMS or email from the school about your admission. Please be patient; we will get back to you. Thank you for your interest.</p></div>
	</div>
</section>

<?php page_end(); ?>
