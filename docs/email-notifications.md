# Admissions email notifications

Application status changes are sent through PHPMailer using SMTP to the guardian email submitted with the application. The sender is **CBETPlus E-Admissions <cbetplusmis@gmail.com>** using Gmail SMTP.

On the XAMPP installation where `CBETPlus/Authetication/smtp_config.local.php` exists, admissions reuses that private SMTP configuration automatically. The password stays in that ignored local config file and is not copied into this project. Any `AD_MAIL_*` environment variables set for Apache take precedence. On other installations, configure the following environment variables for Apache/PHP, then restart Apache:

- `AD_MAIL_HOST`: SMTP server hostname (defaults to `smtp.gmail.com`).
- `AD_MAIL_PORT`: SMTP port (defaults to `587`).
- `AD_MAIL_USERNAME`: SMTP account name (defaults to `cbetplusmis@gmail.com`).
- `AD_MAIL_PASSWORD`: Gmail app password (required; do not use or store the account password in the project).
- `AD_MAIL_FROM`: verified sender email address (defaults to `cbetplusmis@gmail.com`).
- `AD_MAIL_FROM_NAME`: sender name (defaults to `CBETPlus E-Admissions`).
- `AD_MAIL_ENCRYPTION`: `tls` (default), `ssl`, or `none`.

Do not commit SMTP passwords to the project. If SMTP is missing or sending fails, the Headteacher sees an explicit warning after the status has been saved.

On an existing database, run `database/migrations/20261117_add_reporting_schedule.sql` once. New database imports already include the reporting schedule columns.
