# Lawrence College School — Online Admissions

The portal is a PHP/MySQL application for the S.1 and S.5 admissions process.
Applicants submit a single application without creating an account. The
Headteacher reviews applications, manages class vacancies, records decisions,
and schedules interviews.

## Install on XAMPP

1. Start Apache and MySQL.
2. Import `database/e_admissions.sql` in phpMyAdmin, or run:

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root < "C:\xampp\htdocs\eAdmissions_cbetplus\database\e_admissions.sql"
   ```

3. If your local MySQL credentials differ from the XAMPP defaults, set
   `AD_DB_HOST`, `AD_DB_USER`, `AD_DB_PASSWORD`, `AD_DB_NAME` and
   `SCHOOL_DB_NAME` for Apache/PHP, or update the defaults in
   `includes/config.php`. The admissions database defaults to `e_admissions`;
   Headteacher authentication uses the existing `sms` database.
4. Open `http://localhost/eAdmissions_cbetplus/`.

The Careers Portal is a standalone sibling folder at
`http://localhost/careers_portal/`. After importing the admissions database,
also import `C:\xampp\htdocs\careers_portal\database\careers.sql` to add its
independent tables. See [docs/careers-portal.md](docs/careers-portal.md) for
the Careers Portal setup and roles.

## Portal access

- Applicants: select an open S.1 or S.5 vacancy and submit the application
  directly. No account or password is required. A working phone number and
  valid email address are required for follow-up; the confirmation includes an
  application reference.
- Headteacher: sign in at `admin/login.php` using an active account from
  `sms.hmd` whose `role` is exactly `Headteacher`. Password hashes are
  verified; existing plaintext CBETPlus passwords are also accepted for
  compatibility with that database.

The import creates 2027 S.1 and S.5 vacancies (120 and 80 places, respectively).
The Headteacher can adjust capacity and open/close dates in **Class vacancies**.
Shortlisting requires an interview date, time and venue. The Headteacher can
print interview passes and admission letters from the application review page.
The portal does not send automated email/SMS notifications.

## Data and files

Admissions and careers data are stored separately by table prefix in the
`e_admissions` database: admissions tables use `ad_` and Careers Portal tables
use `career_`. Headteacher identity remains in `sms.hmd`; neither portal
creates duplicate staff accounts or roles.

Uploaded documents are limited to PDF/JPG/PNG files up to 5 MB and are served
through role-checked download endpoints. The `storage/admissions` directory
denies direct web access; keep that protection enabled and back up the
directory together with the database.

For production, use HTTPS, a dedicated least-privilege database account,
environment-based credentials, regular backups, and antivirus scanning for
uploaded files. The XAMPP `root`/blank-password defaults are for local
development only.
