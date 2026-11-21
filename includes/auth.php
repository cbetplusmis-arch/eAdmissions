<?php

function require_applicant() {
	flash_message("No applicant account is needed. Choose an open vacancy to submit an application.");
	redirect_to("vacancies.php");
}

function require_headteacher() {
	if (!isset($_SESSION["role"], $_SESSION["headteacher_id"]) || $_SESSION["role"] !== "Headteacher") {
		if (isset($_SESSION["role"]) && $_SESSION["role"] === "Applicant") {
			http_response_code(403);
			exit("Applicant accounts cannot access admissions administration.");
		}
		redirect_to("admin/login.php");
	}
}

function clear_portal_session() {
	$_SESSION = array();
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
	}
	session_destroy();
}
