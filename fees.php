<?php
require_once __DIR__ . "/includes/bootstrap.php";

$fee_directory = __DIR__ . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "fees";
$fee_pdfs = array(
	"S.5 Boarding" => "s5-boarding.pdf",
	"S.5 Day Scholar" => "s5-day-scholar.pdf",
	"S.1 Boarding" => "s1-boarding.pdf",
	"S.1 Day Scholar" => "s1-day-scholar.pdf"
);
$available_pdfs = array();
if (is_dir($fee_directory)) {
	foreach ($fee_pdfs as $category => $file) {
		if (is_file($fee_directory . DIRECTORY_SEPARATOR . $file)) {
			$available_pdfs[$category] = $file;
		}
	}
}

require_once __DIR__ . "/includes/layout.php";
page_start("Fees");
?>
<div class="fee-category-grid">
	<?php foreach ($fee_pdfs as $category => $file) {
		$is_available = isset($available_pdfs[$category]);
		$pdf_url = app_url("assets/fees/" . rawurlencode($file));
		if ($is_available) {
	?>
			<a class="fee-category-card" href="<?php echo h($pdf_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open the <?php echo h($category); ?> fee PDF in a new tab">
				<span class="fee-category-icon" aria-hidden="true">PDF</span>
				<span class="fee-category-copy"><strong><?php echo h($category); ?></strong><small>Fee structure</small></span>
				<span class="fee-category-action">Open PDF <span aria-hidden="true">↗</span></span>
			</a>
	<?php } else { ?>
			<div class="fee-category-card unavailable" aria-label="<?php echo h($category); ?> fee PDF not uploaded">
				<span class="fee-category-icon" aria-hidden="true">PDF</span>
				<span class="fee-category-copy"><strong><?php echo h($category); ?></strong><small>Fee structure</small></span>
				<span class="fee-category-action">Not uploaded</span>
			</div>
	<?php } } ?>
</div>
<?php page_end(); ?>
