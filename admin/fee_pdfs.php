<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_headteacher();

$fee_categories = array(
	"s5-boarding" => array("label" => "S.5 Boarding", "file" => "s5-boarding.pdf"),
	"s5-day-scholar" => array("label" => "S.5 Day Scholar", "file" => "s5-day-scholar.pdf"),
	"s1-boarding" => array("label" => "S.1 Boarding", "file" => "s1-boarding.pdf"),
	"s1-day-scholar" => array("label" => "S.1 Day Scholar", "file" => "s1-day-scholar.pdf")
);
$fee_directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "fees";
$error = "";
$selected_category = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	verify_csrf();
	$selected_category = isset($_POST["fee_category"]) && is_string($_POST["fee_category"])
		? trim($_POST["fee_category"])
		: "";
	$upload = isset($_FILES["fee_pdf"]) && is_array($_FILES["fee_pdf"]) ? $_FILES["fee_pdf"] : null;

	if (!isset($fee_categories[$selected_category])) {
		$error = "Choose one of the four fee categories.";
	} elseif (!$upload || !isset($upload["error"], $upload["tmp_name"], $upload["size"])) {
		$error = "Choose a PDF file to upload.";
	} elseif (!is_numeric($upload["error"]) || !is_string($upload["tmp_name"]) || !is_numeric($upload["size"])) {
		$error = "The uploaded file information is invalid. Please choose the PDF again.";
	} elseif ((int) $upload["error"] !== UPLOAD_ERR_OK) {
		$error = (int) $upload["error"] === UPLOAD_ERR_INI_SIZE || (int) $upload["error"] === UPLOAD_ERR_FORM_SIZE
			? "The PDF is larger than the server upload limit."
			: "The PDF upload failed. Please choose the file again.";
	} elseif (!is_uploaded_file($upload["tmp_name"])) {
		$error = "The selected file could not be verified as an upload.";
	} else {
		$file_size = filesize($upload["tmp_name"]);
		$file_info = new finfo(FILEINFO_MIME_TYPE);
		$file_mime = $file_info->file($upload["tmp_name"]);
		$file_signature = file_get_contents($upload["tmp_name"], false, null, 0, 5);
		if ($file_size === false || $file_size < 1 || $file_size > 20 * 1024 * 1024) {
			$error = "Upload a PDF smaller than 20 MB.";
		} elseif ($file_mime !== "application/pdf" || $file_signature !== "%PDF-") {
			$error = "The selected file is not a valid PDF.";
		} elseif (!is_dir($fee_directory) && !mkdir($fee_directory, 0750, true) && !is_dir($fee_directory)) {
			$error = "The fee PDF storage folder could not be created.";
		} elseif (!is_writable($fee_directory)) {
			$error = "The fee PDF storage folder is not writable by the web server.";
		} else {
			$destination = $fee_directory . DIRECTORY_SEPARATOR . $fee_categories[$selected_category]["file"];
			if (!move_uploaded_file($upload["tmp_name"], $destination)) {
				$error = "The PDF could not be saved. Please try again.";
			} else {
				flash_message($fee_categories[$selected_category]["label"] . " PDF uploaded successfully.");
				redirect_to("admin/fee_pdfs.php");
			}
		}
	}
}

require_once __DIR__ . "/../includes/layout.php";
page_start("Fee PDFs", "fees", "admin");
?>
<section class="card form-card">
	<h2>Upload a fee PDF</h2>
	<p class="muted small">Choose the category for this approved PDF. Uploading another file for the same category replaces the current one shown to applicants.</p>
	<?php if ($error !== "") { ?><div class="alert alert-error" role="alert" aria-live="polite"><?php echo h($error); ?></div><?php } ?>
	<form method="post" enctype="multipart/form-data" action="<?php echo h(app_url("admin/fee_pdfs.php")); ?>" onsubmit="return confirm('Upload this fee PDF? It will replace the current PDF for the selected category.');">
		<?php csrf_input(); ?>
		<div class="field">
			<label for="fee_category">Fee category</label>
			<select id="fee_category" name="fee_category" required>
				<option value="">Choose a category</option>
				<?php foreach ($fee_categories as $key => $category) { ?>
					<option value="<?php echo h($key); ?>" <?php echo $selected_category === $key ? "selected" : ""; ?>><?php echo h($category["label"]); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="field" style="margin-top:14px">
			<label for="fee_pdf">PDF file</label>
			<input id="fee_pdf" name="fee_pdf" type="file" accept=".pdf,application/pdf" required>
			<span class="field-hint">PDF only · maximum 20 MB.</span>
		</div>
		<div class="form-actions"><button class="btn" type="submit">Upload PDF</button></div>
	</form>
</section>

<div class="section-title"><div><h2>Published PDFs</h2><p>These are the files currently displayed on the public Fees page.</p></div></div>
<div class="card">
	<ul class="document-list">
		<?php foreach ($fee_categories as $category) {
			$file_path = $fee_directory . DIRECTORY_SEPARATOR . $category["file"];
			?>
			<li>
				<span><?php echo h($category["label"]); ?></span>
				<?php if (is_file($file_path)) { ?>
					<a class="btn outline small" href="<?php echo h(app_url("assets/fees/" . $category["file"])); ?>" target="_blank" rel="noopener noreferrer">Open PDF</a>
				<?php } else { ?>
					<span class="muted">Not uploaded</span>
				<?php } ?>
			</li>
		<?php } ?>
	</ul>
</div>
<?php page_end("admin"); ?>
