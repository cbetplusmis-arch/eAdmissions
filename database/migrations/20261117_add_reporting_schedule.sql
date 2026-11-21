USE e_admissions;

ALTER TABLE ad_applications
	ADD COLUMN reporting_at DATETIME NULL AFTER interview_location,
	ADD COLUMN reporting_location VARCHAR(190) NULL AFTER reporting_at;
