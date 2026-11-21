CREATE DATABASE IF NOT EXISTS e_admissions
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

USE e_admissions;

CREATE TABLE IF NOT EXISTS ad_applicant_accounts (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	full_name VARCHAR(160) NOT NULL,
	email VARCHAR(190) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ad_applicant_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_vacancies (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	school_year VARCHAR(9) NOT NULL,
	class_level ENUM('S.1', 'S.5') NOT NULL,
	available_places SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	application_open TINYINT(1) NOT NULL DEFAULT 1,
	opens_on DATE NULL,
	closes_on DATE NULL,
	created_by_hmd_id INT UNSIGNED NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ad_vacancy_year_class (school_year, class_level),
	KEY idx_ad_vacancy_open (application_open, school_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_applications (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	reference_no VARCHAR(32) NOT NULL,
	applicant_id BIGINT UNSIGNED NULL,
	vacancy_id INT UNSIGNED NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	date_of_birth DATE NOT NULL,
	gender ENUM('Female', 'Male', 'Other') NOT NULL,
	district VARCHAR(100) NOT NULL,
	previous_school VARCHAR(190) NOT NULL,
	exam_body ENUM('PLE', 'UCE') NOT NULL,
	exam_year SMALLINT UNSIGNED NOT NULL,
	aggregate_score TINYINT UNSIGNED NOT NULL,
	subject_combination VARCHAR(120) NULL,
	guardian_name VARCHAR(160) NOT NULL,
	guardian_relationship VARCHAR(50) NOT NULL,
	guardian_phone VARCHAR(32) NOT NULL,
	guardian_email VARCHAR(190) NULL,
	home_address TEXT NOT NULL,
	boarding_preference ENUM('Day', 'Boarding') NOT NULL DEFAULT 'Day',
	special_needs TEXT NULL,
	status ENUM('Pending', 'Under review', 'Shortlisted', 'Admitted', 'Waitlisted', 'Rejected') NOT NULL DEFAULT 'Under review',
	decision_notes TEXT NULL,
	interview_at DATETIME NULL,
	interview_location VARCHAR(190) NULL,
	reporting_at DATETIME NULL,
	reporting_location VARCHAR(190) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ad_application_reference (reference_no),
	UNIQUE KEY uq_ad_application_applicant_vacancy (applicant_id, vacancy_id),
	KEY idx_ad_application_vacancy_status (vacancy_id, status),
	CONSTRAINT fk_ad_application_applicant FOREIGN KEY (applicant_id)
		REFERENCES ad_applicant_accounts (id) ON DELETE CASCADE,
	CONSTRAINT fk_ad_application_vacancy FOREIGN KEY (vacancy_id)
		REFERENCES ad_vacancies (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ad_applications
	MODIFY applicant_id BIGINT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS ad_documents (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	application_id BIGINT UNSIGNED NOT NULL,
	document_type ENUM('exam_result', 'birth_certificate', 'recommendation', 'passport_photo') NOT NULL,
	original_name VARCHAR(255) NOT NULL,
	storage_name CHAR(68) NOT NULL,
	mime_type VARCHAR(100) NOT NULL,
	file_size INT UNSIGNED NOT NULL,
	uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ad_document_type (application_id, document_type),
	CONSTRAINT fk_ad_document_application FOREIGN KEY (application_id)
		REFERENCES ad_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ad_documents
	MODIFY storage_name CHAR(68) NOT NULL;

CREATE TABLE IF NOT EXISTS ad_application_events (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	application_id BIGINT UNSIGNED NOT NULL,
	actor_hmd_id INT UNSIGNED NULL,
	actor_role ENUM('Applicant', 'Headteacher', 'System') NOT NULL,
	event_type VARCHAR(40) NOT NULL,
	old_status VARCHAR(30) NULL,
	new_status VARCHAR(30) NULL,
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_ad_application_event (application_id, created_at),
	CONSTRAINT fk_ad_event_application FOREIGN KEY (application_id)
		REFERENCES ad_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ad_vacancies (school_year, class_level, available_places, application_open)
VALUES ('2027', 'S.1', 120, 1), ('2027', 'S.5', 80, 1);
