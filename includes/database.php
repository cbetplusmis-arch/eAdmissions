<?php
require_once __DIR__ . "/config.php";

mysqli_report(MYSQLI_REPORT_OFF);

function admissions_db() {
	static $database = null;
	if ($database === null) {
		$database = new mysqli(AD_DB_HOST, AD_DB_USER, AD_DB_PASSWORD, AD_DB_NAME);
		if ($database->connect_error) {
			http_response_code(500);
			die("Admissions database connection failed. Create/import database/e_admissions.sql and check includes/config.php.");
		}
		if (!$database->set_charset("utf8mb4")) {
			http_response_code(500);
			die("Could not set the admissions database character set.");
		}
	}
	return $database;
}

function school_db() {
	static $database = null;
	if ($database === null) {
		$database = new mysqli(AD_DB_HOST, AD_DB_USER, AD_DB_PASSWORD, SCHOOL_DB_NAME);
		if ($database->connect_error) {
			http_response_code(500);
			die("The school account database is unavailable. Headteacher sign-in requires the sms database.");
		}
		if (!$database->set_charset("utf8mb4")) {
			http_response_code(500);
			die("Could not set the school account database character set.");
		}
	}
	return $database;
}
