<?php

function h($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function school_profile() {
	static $profile = null;
	if ($profile !== null) {
		return $profile;
	}

	$statement = school_db()->prepare(
		"SELECT school_name, motto, pobox, location, mission, vision
		 FROM institutional_profile WHERE id = 1 LIMIT 1"
	);
	if (!$statement || !$statement->execute()) {
		http_response_code(500);
		die("Could not load the school profile from the SMS database.");
	}
	$profile = $statement->get_result()->fetch_assoc();
	$statement->close();
	if (!$profile || trim($profile["school_name"]) === "") {
		http_response_code(500);
		die("The school profile is missing from the SMS database.");
	}
	foreach ($profile as $key => $value) {
		if (is_string($value)) {
			$profile[$key] = preg_replace('/\s+/', ' ', trim($value));
		}
	}
	return $profile;
}

function school_name() {
	return school_profile()["school_name"];
}

function app_url($path = "") {
	$directory = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"]));
	$last_directory = basename($directory);
	if ($last_directory === "admin" || $last_directory === "applicant") {
		$directory = dirname($directory);
	}
	$base = rtrim($directory, "/");
	return $base . ($path === "" ? "/" : "/" . ltrim($path, "/"));
}

function redirect_to($path) {
	header("Location: " . app_url($path));
	exit;
}

function csrf_token() {
	if (empty($_SESSION["csrf_token"])) {
		$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
	}
	return $_SESSION["csrf_token"];
}

function csrf_input() {
	echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf() {
	$submitted = isset($_POST["csrf_token"]) ? (string) $_POST["csrf_token"] : "";
	if ($submitted === "" || !hash_equals(csrf_token(), $submitted)) {
		http_response_code(419);
		exit("This form expired. Return to the page and submit it again.");
	}
}

function flash_message($message, $type = "success") {
	$_SESSION["flash_message"] = array("message" => $message, "type" => $type);
}

function take_flash_message() {
	if (!isset($_SESSION["flash_message"])) {
		return null;
	}
	$message = $_SESSION["flash_message"];
	unset($_SESSION["flash_message"]);
	return $message;
}

function document_labels($class_level = null) {
	return array(
		"exam_result" => $class_level === "S.5"
			? "S.4 UCE pass slip or certificate"
			: "PLE pass slip or certificate",
		"birth_certificate" => "Birth certificate or equivalent",
		"recommendation" => "Recommendation from previous school",
		"passport_photo" => "Recent passport photograph"
	);
}

function status_class($status) {
	return strtolower(str_replace(" ", "-", application_status_label($status)));
}

function application_status_label($status) {
	$status = (string) $status;
	return in_array($status, array("Admitted", "Rejected"), true) ? $status : "Under review";
}

function valid_date_input($value, $parse_format, $output_format) {
	$date = DateTime::createFromFormat($parse_format, $value);
	$errors = DateTime::getLastErrors();
	return $date !== false
		&& $date->format($output_format) === $value
		&& ($errors === false || ($errors["warning_count"] === 0 && $errors["error_count"] === 0));
}
