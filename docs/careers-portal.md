# Careers Portal

The Careers Portal is a separate recruitment application alongside the
admissions portal. It has its own public job board, applicant accounts and
Headteacher workspace, styling and database tables. It does not change the
existing admissions pages or admission records.

## Setup

1. Start Apache and MySQL and configure the existing database credentials in
   `includes/config.php` (or the matching environment variables).
2. Import `database/e_admissions.sql` if the `e_admissions` database does not
   already exist.
3. Keep the Careers Portal in its own `C:\xampp\htdocs\careers_portal` folder
   beside `eAdmissions_cbetplus`. Import
   `C:\xampp\htdocs\careers_portal\database\careers.sql` into the same MySQL server. The script
   creates only `career_` tables in `e_admissions`; it does not alter any
   `ad_` admissions table. For databases that already have Careers Portal
   tables, also apply
   `C:\xampp\htdocs\careers_portal\database\migrations\20261117_add_account_recovery_and_job_contact.sql`.
4. Open `http://localhost/careers_portal/`.

The top navigation's **Login** button opens the shared sign-in form, which
assigns access based on the verified account role. Use the Headteacher's
existing active account from
`sms.hmd` at `http://localhost/careers_portal/admin/login.php`. No separate
staff account can be created or reset in this portal. Applicants register at
`http://localhost/careers_portal/applicant/register.php`, browse open roles,
submit a cover letter and CV, and see their status and feedback in their
account dashboard. Applicant-only password reset links are emailed and expire
after 30 minutes. Configure the shared SMTP settings and
`CAREER_PORTAL_BASE_URL` for the public site URL.

The Headteacher must provide a valid, monitored recruitment contact email when
creating each job. It is shown to candidates for role-related enquiries.

## Headteacher workflow

- Publish, edit, open or close roles in **Manage jobs**.
- Review applications and download candidate CVs in **Applications**.
- Update the candidate's status and feedback. The applicant sees those updates
  the next time they visit their dashboard.

Candidate CVs are restricted to PDF, DOC or DOCX files up to 5 MB. They are
stored under `C:\xampp\htdocs\careers_portal\storage\` with generated
filenames, blocked from direct web access, and delivered only through the
Headteacher-authenticated download route. Back up this directory with the
database and keep its access protection enabled.

The portal does not send automated email or SMS messages. Use HTTPS, a
least-privilege database account and malware scanning for production uploads.
