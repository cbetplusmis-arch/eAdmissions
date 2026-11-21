<?php
require_once __DIR__ . "/../includes/bootstrap.php";
flash_message("No applicant sign-in is required. Choose an open vacancy to apply.");
redirect_to("vacancies.php");
