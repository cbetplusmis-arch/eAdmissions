<?php

function admissions_config($name, $default) {
	$value = getenv($name);
	return $value === false || $value === "" ? $default : $value;
}

define("AD_DB_HOST", admissions_config("AD_DB_HOST", "localhost"));
define("AD_DB_USER", admissions_config("AD_DB_USER", "root"));
define("AD_DB_PASSWORD", admissions_config("AD_DB_PASSWORD", ""));
define("AD_DB_NAME", admissions_config("AD_DB_NAME", "e_admissions"));
define("SCHOOL_DB_NAME", admissions_config("SCHOOL_DB_NAME", "sms"));
define("SCHOOL_YEAR", "2027");
