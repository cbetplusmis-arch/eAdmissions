<?php
require_once __DIR__ . "/../includes/bootstrap.php";
if (isset($_SESSION["role"]) && $_SESSION["role"] === "Headteacher") {
	redirect_to("admin/dashboard.php");
}

$error = "";
$email = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
	verify_csrf();
	$email = strtolower(trim(isset($_POST["email"]) ? $_POST["email"] : ""));
	$password = isset($_POST["password"]) ? (string) $_POST["password"] : "";

	if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== "") {
		$school_database = school_db();
		$statement = $school_database->prepare(
			"SELECT id, full_name, email, password, status
			 FROM hmd
			 WHERE email = ? AND role = 'Headteacher'
			 LIMIT 1"
		);
		if (!$statement) {
			http_response_code(500);
			die("Could not prepare Headteacher sign-in.");
		}
		$statement->bind_param("s", $email);
		if (!$statement->execute()) {
			$statement->close();
			http_response_code(500);
			die("Could not check the Headteacher account.");
		}
		$statement->bind_result($headteacher_id, $full_name, $stored_email, $stored_password, $account_status);
		$found = $statement->fetch();
		$statement->close();

		$password_matches = $found && (
			password_verify($password, $stored_password)
			|| hash_equals((string) $stored_password, $password)
		);
		if ($password_matches && strcasecmp((string) $account_status, "Active") === 0) {
			session_regenerate_id(true);
			$_SESSION = array(
				"role" => "Headteacher",
				"headteacher_id" => (int) $headteacher_id,
				"full_name" => $full_name,
				"email" => $stored_email
			);
			redirect_to("admin/dashboard.php");
		}
	}
	$error = "The account is inactive or the email/password is not correct.";
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Login");
?>
<section class="login-card">
	<div class="login-brand">
		<img src="<?php echo h(app_url("assets/lawrence-college-crest.jpg")); ?>" alt="">
		<strong><?php echo h(school_name()); ?></strong>
	</div>
	<h1>Welcome back</h1>
	<p class="login-intro">Sign in with your school account to continue.</p>
	<?php if ($error !== "") { ?><div class="alert alert-error" role="alert" aria-live="polite"><?php echo h($error); ?></div><?php } ?>
	<form method="post" action="<?php echo h(app_url("admin/login.php")); ?>">
		<?php csrf_input(); ?>
		<div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required value="<?php echo h($email); ?>"></div>
		<div class="field password-field">
			<label for="password">Password</label>
			<div class="password-input-wrap">
				<input id="password" name="password" type="password" autocomplete="current-password" required>
				<button class="password-toggle" type="button" aria-label="Show password" aria-controls="password" aria-pressed="false">
					<svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
						<circle cx="12" cy="12" r="2.5"></circle>
					</svg>
					<svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M3 3l18 18M10.6 6.2A10.3 10.3 0 0 1 12 6c6 0 9.5 6 9.5 6a15.8 15.8 0 0 1-2.2 3M6.1 6.1C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6M9.8 9.8a3.1 3.1 0 0 0 4.4 4.4"></path>
					</svg>
				</button>
			</div>
		</div>
		<button class="btn navy" type="submit">Login</button>
	</form>
	<a class="btn outline login-return" href="<?php echo h(app_url("index.php")); ?>">Return to school admissions</a>
</section>
<script>
	(function () {
		var password = document.getElementById("password");
		var toggle = document.querySelector(".password-toggle");
		if (!password || !toggle) {
			return;
		}
		toggle.addEventListener("click", function () {
			var showPassword = password.type === "password";
			password.type = showPassword ? "text" : "password";
			toggle.setAttribute("aria-pressed", showPassword ? "true" : "false");
			toggle.setAttribute("aria-label", showPassword ? "Hide password" : "Show password");
			toggle.classList.toggle("showing", showPassword);
		});
	}());
</script>
<?php page_end(); ?>
