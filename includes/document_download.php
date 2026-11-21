<?php

function download_application_document($document_id, $applicant_id = null, $inline = false) {
	$database = admissions_db();
	if ($applicant_id === null) {
		$statement = $database->prepare(
			"SELECT d.storage_name, d.original_name
			 FROM ad_documents d JOIN ad_applications a ON a.id = d.application_id
			 WHERE d.id = ? LIMIT 1"
		);
		$statement->bind_param("i", $document_id);
	} else {
		$statement = $database->prepare(
			"SELECT d.storage_name, d.original_name
			 FROM ad_documents d JOIN ad_applications a ON a.id = d.application_id
			 WHERE d.id = ? AND a.applicant_id = ? LIMIT 1"
		);
		$statement->bind_param("ii", $document_id, $applicant_id);
	}
	if (!$statement || !$statement->execute()) {
		http_response_code(500);
		exit("Could not retrieve the requested document.");
	}
	$document = $statement->get_result()->fetch_assoc();
	$statement->close();
	if (!$document) {
		http_response_code(404);
		exit("The requested document was not found.");
	}

	if (!preg_match('/^[a-f0-9]{64}\.(pdf|jpg|png)$/', $document["storage_name"])) {
		http_response_code(404);
		exit("The requested document is unavailable.");
	}
	$file_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "admissions" . DIRECTORY_SEPARATOR . $document["storage_name"];
	if (!is_file($file_path)) {
		http_response_code(404);
		exit("The requested document is no longer available.");
	}
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime_type = $finfo->file($file_path);
	if (!in_array($mime_type, array("application/pdf", "image/jpeg", "image/png"), true)) {
		http_response_code(415);
		exit("The stored file type is not supported.");
	}
	$download_name = preg_replace('/[^A-Za-z0-9._-]/', "_", basename($document["original_name"]));
	header("X-Content-Type-Options: nosniff");
	header("Content-Type: " . $mime_type);
	header("Content-Length: " . filesize($file_path));
	header("Content-Disposition: " . ($inline ? "inline" : "attachment") . "; filename=\"" . ($download_name ?: "admissions-document") . "\"");
	readfile($file_path);
	exit;
}
