<?php
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_set_cookie_params(array(
		"httponly" => true,
		"samesite" => "Lax",
		"secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
	));
	session_start();
}

require_once __DIR__ . "/auth.php";
