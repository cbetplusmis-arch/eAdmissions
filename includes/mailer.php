<?php

function send_admissions_status_email($application, $new_status, &$error) {
	$error = "";
	$recipient = trim((string) $application["guardian_email"]);
	if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
		$error = "The applicant's guardian email address is missing or invalid.";
		return false;
	}

	$autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
	if (!is_file($autoload)) {
		$error = "PHPMailer is not installed. Run Composer install before sending notifications.";
		return false;
	}
	require_once $autoload;
	if (!class_exists("PHPMailer\\PHPMailer\\PHPMailer")) {
		$error = "PHPMailer could not be loaded from the Composer dependencies.";
		return false;
	}

	$shared_smtp = array();
	$shared_config_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "CBETPlus"
		. DIRECTORY_SEPARATOR . "Authetication" . DIRECTORY_SEPARATOR . "smtp_config.php";
	$shared_local_config_path = dirname($shared_config_path) . DIRECTORY_SEPARATOR . "smtp_config.local.php";
	$admissions_password = getenv("AD_MAIL_PASSWORD");
	if (($admissions_password === false || $admissions_password === "")
		&& is_file($shared_config_path) && is_file($shared_local_config_path)) {
		require_once $shared_config_path;
		if (function_exists("cbetplusSmtpConfig")) {
			try {
				$shared_smtp = cbetplusSmtpConfig();
			} catch (\RuntimeException $exception) {
				$error = "The existing CBETPlus SMTP configuration is invalid: " . $exception->getMessage();
				return false;
			}
		}
	}

	$setting = function ($environment_name, $shared_name, $default) use ($shared_smtp) {
		$value = getenv($environment_name);
		if ($value !== false && $value !== "") {
			return $value;
		}
		return isset($shared_smtp[$shared_name]) ? $shared_smtp[$shared_name] : $default;
	};
	$host = trim((string)$setting("AD_MAIL_HOST", "host", "smtp.gmail.com"));
	$username = trim((string)$setting("AD_MAIL_USERNAME", "username", "cbetplusmis@gmail.com"));
	$password = (string)$setting("AD_MAIL_PASSWORD", "password", "");
	$from_email = trim((string)$setting("AD_MAIL_FROM", "from_email", "cbetplusmis@gmail.com"));
	$from_name = trim((string)admissions_config("AD_MAIL_FROM_NAME", "CBETPlus E-Admissions"));
	$port = filter_var($setting("AD_MAIL_PORT", "port", "587"), FILTER_VALIDATE_INT);
	$encryption = strtolower(trim((string)$setting("AD_MAIL_ENCRYPTION", "secure", "tls")));
	if ($host === "" || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
		$error = "Email is not configured. Set AD_MAIL_HOST and a valid AD_MAIL_FROM address.";
		return false;
	}
	if ($username !== "" && $password === "") {
		$error = "No SMTP password is configured. Add it to the CBETPlus authentication SMTP config or set AD_MAIL_PASSWORD for Apache.";
		return false;
	}
	if ($port === false || $port < 1 || $port > 65535
		|| !in_array($encryption, array("tls", "ssl", "none"), true)) {
		$error = "The SMTP port or encryption setting is invalid.";
		return false;
	}

	$student_name = trim($application["first_name"] . " " . $application["last_name"]);
	$reference = (string) $application["reference_no"];
	$class = (string) $application["class_level"];
	$school_year = (string) $application["school_year"];
	$guardian_name = trim((string) $application["guardian_name"]);
	$escaped = function ($value) {
		return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
	};

	switch ($new_status) {
		case "Under review":
			$lead = "The application is now under review. The school will contact you if any further information or action is needed.";
			$schedule_at = null;
			$schedule_location = "";
			$schedule_label = "";
			break;
		case "Admitted":
			$lead = "Congratulations. The applicant has been admitted. Please report to school for registration at the date, time and venue below.";
			$schedule_at = $application["reporting_at"];
			$schedule_location = $application["reporting_location"];
			$schedule_label = "Reporting";
			break;
		case "Rejected":
			$lead = "The school is unable to offer a place for this intake. No reporting action is required.";
			$schedule_at = null;
			$schedule_location = "";
			$schedule_label = "";
			break;
		default:
			$error = "Choose a supported application status.";
			return false;
	}

	$schedule_html = "";
	$schedule_text = "";
	if ($schedule_at) {
		$formatted_at = date("l, j F Y, H:i", strtotime($schedule_at));
		$schedule_html = "<p><strong>" . $escaped($schedule_label) . " date and time:</strong> " . $escaped($formatted_at)
			. "<br><strong>Venue / instructions:</strong> " . nl2br($escaped($schedule_location)) . "</p>";
		$schedule_text = $schedule_label . " date and time: " . $formatted_at
			. "\nVenue / instructions: " . $schedule_location . "\n";
	}

	$subject = "Admissions status update - " . $reference;
	$html_body = "<p>Dear " . $escaped($guardian_name !== "" ? $guardian_name : "Parent/Guardian") . ",</p>"
		. "<p>" . $escaped($lead) . "</p>"
		. "<p><strong>Learner:</strong> " . $escaped($student_name)
		. "<br><strong>Entry class:</strong> " . $escaped($class)
		. "<br><strong>School year:</strong> " . $escaped($school_year)
		. "<br><strong>Application reference:</strong> " . $escaped($reference)
		. "<br><strong>Current status:</strong> " . $escaped($new_status) . "</p>"
		. $schedule_html
		. "<p>Please keep this reference for any communication with " . $escaped(school_name()) . ".</p>"
		. "<p>Regards,<br>" . $escaped(school_name()) . " Admissions Office</p>";
	$text_body = "Dear " . ($guardian_name !== "" ? $guardian_name : "Parent/Guardian") . ",\n\n"
		. $lead . "\n\nLearner: " . $student_name . "\nEntry class: " . $class
		. "\nSchool year: " . $school_year . "\nApplication reference: " . $reference
		. "\nCurrent status: " . $new_status . "\n" . $schedule_text
		. "\nPlease keep this reference for any communication with " . school_name() . ".\n\n"
		. "Regards,\n" . school_name() . " Admissions Office";

	$mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
	try {
		$mailer->isSMTP();
		$mailer->Host = $host;
		$mailer->Port = $port;
		$mailer->SMTPAuth = $username !== "";
		$mailer->Username = $username;
		$mailer->Password = $password;
		$mailer->Timeout = 15;
		$mailer->CharSet = "UTF-8";
		$mailer->SMTPDebug = 0;
		$mailer->SMTPAutoTLS = $encryption === "tls";
		$mailer->SMTPSecure = $encryption === "tls"
			? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
			: ($encryption === "ssl" ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : "");
		$mailer->setFrom($from_email, $from_name);
		$mailer->addAddress($recipient, $guardian_name);
		$mailer->Subject = $subject;
		$mailer->isHTML(true);
		$mailer->Body = $html_body;
		$mailer->AltBody = $text_body;
		$mailer->send();
		return true;
	} catch (\PHPMailer\PHPMailer\Exception $exception) {
		$error = $mailer->ErrorInfo !== "" ? $mailer->ErrorInfo : $exception->getMessage();
		return false;
	}
}
