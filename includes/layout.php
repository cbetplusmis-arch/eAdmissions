<?php

function page_start($title, $active = "", $portal = "") {
	$role = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
	$is_admin = $portal === "admin";
	$body_class = $title === "Login" ? "login-page" : ($is_admin ? "admin-page" : "");
	$brand_link = $is_admin ? "admin/dashboard.php" : "index.php";
	$profile = school_profile();
	$brand_name = $is_admin ? "Admissions Office" : $profile["school_name"];
	?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#174c54">
	<title><?php echo h($title); ?> | <?php echo h($profile["school_name"]); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo h(app_url("assets/admissions.css?v=" . filemtime(__DIR__ . "/../assets/admissions.css"))); ?>">
</head>
<body class="<?php echo h($body_class); ?>">
<header class="topbar">
	<div class="topbar-inner">
		<a class="brand" href="<?php echo h(app_url($brand_link)); ?>">
			<img class="brand-logo" src="<?php echo h(app_url("assets/lawrence-college-crest.jpg")); ?>" alt="" aria-hidden="true">
			<span><strong><?php echo h($brand_name); ?></strong><small>ONLINE ADMISSIONS · <?php echo h(SCHOOL_YEAR); ?></small></span>
		</a>
		<button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open navigation menu">
			<span class="menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
		</button>
		<nav class="top-links" aria-label="Main navigation">
			<?php if ($is_admin) { ?>
				<span class="signed-in">Headteacher · <?php echo h(isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : ""); ?></span>
				<a href="<?php echo h(app_url("admin/logout.php")); ?>" onclick="return confirm('Are you sure you want to sign out?');">Sign out</a>
			<?php } else { ?>
				<a href="<?php echo h(app_url("index.php")); ?>">Home</a>
				<a href="<?php echo h(app_url("vacancies.php")); ?>">Vacancies</a>
				<a href="<?php echo h(app_url("fees.php")); ?>">Fees</a>
				<?php if ($role === "Headteacher") { ?>
					<a href="<?php echo h(app_url("admin/dashboard.php")); ?>">Admissions admin</a>
					<a class="nav-cta" href="<?php echo h(app_url("admin/logout.php")); ?>" onclick="return confirm('Are you sure you want to sign out?');">Sign out</a>
				<?php } else { ?>
					<a class="nav-cta" href="<?php echo h(app_url("admin/login.php")); ?>">Login</a>
				<?php } ?>
			<?php } ?>
		</nav>
	</div>
</header>
<div class="mobile-menu" id="mobile-menu" role="dialog" aria-modal="true" aria-label="Main navigation" hidden>
	<div class="mobile-menu-heading">
		<strong>MENU</strong>
		<button class="mobile-menu-close" type="button" aria-label="Close navigation menu"><span aria-hidden="true">&times;</span></button>
	</div>
	<?php if ($is_admin) { ?>
		<p class="mobile-menu-context">Headteacher · <?php echo h(isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : ""); ?></p>
		<nav class="mobile-menu-links" aria-label="Admissions administration">
			<a href="<?php echo h(app_url("admin/dashboard.php")); ?>">Overview</a>
			<a href="<?php echo h(app_url("admin/applications.php")); ?>">Applications</a>
			<a href="<?php echo h(app_url("admin/vacancies.php")); ?>">Class vacancies</a>
			<a href="<?php echo h(app_url("admin/fee_pdfs.php")); ?>">Fee PDFs</a>
			<a href="<?php echo h(app_url("index.php")); ?>">Public admissions site</a>
			<a class="mobile-menu-signout" href="<?php echo h(app_url("admin/logout.php")); ?>" onclick="return confirm('Are you sure you want to sign out?');">Sign out</a>
		</nav>
	<?php } else { ?>
		<nav class="mobile-menu-links" aria-label="Admissions">
			<a href="<?php echo h(app_url("index.php")); ?>">Home</a>
			<a href="<?php echo h(app_url("vacancies.php")); ?>">Vacancies</a>
			<a href="<?php echo h(app_url("fees.php")); ?>">Fees</a>
			<?php if ($role === "Headteacher") { ?>
				<a href="<?php echo h(app_url("admin/dashboard.php")); ?>">Admissions admin</a>
				<a class="mobile-menu-signout" href="<?php echo h(app_url("admin/logout.php")); ?>" onclick="return confirm('Are you sure you want to sign out?');">Sign out</a>
			<?php } else { ?>
				<a class="mobile-menu-signout" href="<?php echo h(app_url("admin/login.php")); ?>">Login</a>
			<?php } ?>
		</nav>
	<?php } ?>
</div>
<?php if ($is_admin) { ?>
<div class="portal-layout">
	<aside class="sidebar">
		<div class="sidebar-caption">ADMISSIONS ADMIN</div>
		<a class="side-link <?php echo $active === "dashboard" ? "active" : ""; ?>" href="<?php echo h(app_url("admin/dashboard.php")); ?>"><span>⌂</span> Overview</a>
		<a class="side-link <?php echo $active === "applications" ? "active" : ""; ?>" href="<?php echo h(app_url("admin/applications.php")); ?>"><span>▤</span> Applications</a>
		<a class="side-link <?php echo $active === "vacancies" ? "active" : ""; ?>" href="<?php echo h(app_url("admin/vacancies.php")); ?>"><span>＋</span> Class vacancies</a>
		<a class="side-link <?php echo $active === "fees" ? "active" : ""; ?>" href="<?php echo h(app_url("admin/fee_pdfs.php")); ?>"><span>▣</span> Fee PDFs</a>
		<div class="sidebar-bottom">
			<div class="security-note"><strong>Headteacher access</strong><span>Review applications and manage the school intake.</span></div>
		</div>
	</aside>
	<main class="workspace">
		<div class="page-heading"><div><p class="eyebrow">SCHOOL MANAGEMENT</p><h1><?php echo h($title); ?></h1></div></div>
		<?php $flash = take_flash_message(); if ($flash) { ?>
			<div class="alert alert-<?php echo h($flash["type"]); ?>" role="<?php echo $flash["type"] === "error" ? "alert" : "status"; ?>" aria-live="polite"><?php echo h($flash["message"]); ?></div>
		<?php } ?>
<?php } else { ?>
<main class="public-main">
	<?php $flash = take_flash_message(); if ($flash) { ?>
		<div class="alert alert-<?php echo h($flash["type"]); ?> public-alert" role="<?php echo $flash["type"] === "error" ? "alert" : "status"; ?>" aria-live="polite"><?php echo h($flash["message"]); ?></div>
	<?php } ?>
<?php } ?>
	<?php
}

function page_end($portal = "") {
	?>
	</main>
	<?php if ($portal !== "") { ?></div><?php } ?>
	<footer class="site-footer"><span>Admissions online managed by <?php echo h(school_name()); ?></span></footer>
	<script>
		(function () {
			var toggle = document.querySelector(".mobile-menu-toggle");
			var menu = document.getElementById("mobile-menu");
			var closeButton = menu ? menu.querySelector(".mobile-menu-close") : null;
			if (!toggle || !menu || !closeButton) {
				return;
			}
			function setMenuOpen(open) {
				menu.hidden = !open;
				toggle.setAttribute("aria-expanded", open ? "true" : "false");
				toggle.setAttribute("aria-label", open ? "Close navigation menu" : "Open navigation menu");
				document.body.classList.toggle("menu-open", open);
				if (open) {
					closeButton.focus();
				} else {
					toggle.focus();
				}
			}
			toggle.addEventListener("click", function () {
				setMenuOpen(menu.hidden);
			});
			closeButton.addEventListener("click", function () {
				setMenuOpen(false);
			});
			menu.addEventListener("keydown", function (event) {
				if (event.key === "Escape") {
					setMenuOpen(false);
					return;
				}
				if (event.key !== "Tab") {
					return;
				}
				var focusable = menu.querySelectorAll('a[href], button:not([disabled])');
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			});
		}());
	</script>
	</body>
</html>
	<?php
}
