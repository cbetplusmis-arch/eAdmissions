<?php
require_once __DIR__ . "/../includes/bootstrap.php";
flash_message("Applicant accounts have been removed. Choose an open vacancy to apply directly.");
redirect_to("vacancies.php");
