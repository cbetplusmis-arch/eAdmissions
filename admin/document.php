<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();
require_once __DIR__ . "/../includes/document_download.php";

$document_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$document_id || $document_id < 1) {
	http_response_code(400);
	exit("A valid document is required.");
}
$inline = isset($_GET["view"]) && $_GET["view"] === "1";
download_application_document($document_id, null, $inline);
