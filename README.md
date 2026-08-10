# MUHAMMAD AHMAD SAFDAR — School Management System (v2.1)

A secure, modular PHP + MySQL school management system: Students, Teachers,
Classes/Sections, Subjects, Attendance, Exams & Results, Fee Management
(invoices + discounts + fines + partial payments + receipts), Salary,
Timetable, Certificates, ID Cards, Reports & Analytics, and Notices — with
role-based access for Admin, Teacher, and custom staff roles (Accountant,
Librarian, Receptionist, or anything else you create).

## What changed from the original version

The original version had SQL injection in several files, a publicly-reachable
password reset, debug scripts left on the live server, and auth checks that
didn't `exit` after a failed redirect. This rebuild fixes all of that:

- **PDO + prepared statements everywhere** — no raw `$_GET`/`$_POST` in SQL.
- **CSRF tokens** on every form and every destructive link (delete buttons).
- **Role-based access control** — teachers only see/edit their own
  section's students, attendance, and exam subjects; admins see everything;
  custom roles get exactly the View/Add/Edit/Delete permissions you assign.
- **Account lockout** after 5 failed logins (15-minute cooldown).
- **Forced password change** on first login for any new account.
- **Secure file uploads** — MIME-type checked, size-limited, random filenames,
  uploads folders block script execution via `.htaccess`.
- **One-time installer** (`install.php`) protected by a setup key, instead
  of permanently-exposed `setup.php` / `reset_password.php` / `test_login.php`.
- **Activity log** — logins, deletions, payments, and other key actions are
  recorded in the `activity_log` table.

## Requirements

- PHP 8.0+ with `pdo_mysql`, `mbstring`, `fileinfo` extensions enabled
- MySQL or MariaDB
- Apache with `mod_rewrite` and `.htaccess` support (XAMPP/WAMP/LAMP all fine)

## Feature overview

**Core**: Students (full admission form with CNIC/B-Form, blood group,
religion, caste, previous school, guardian info, photo), Teachers (with
fixed salary and login account creation), Classes & Sections, Subjects,
Attendance (section-scoped for teachers), Exams & Results (with printable
report cards), Timetable.

**Finance**: Fee structures per class, bulk invoice generation with
per-student discount and configurable fine-after-due-date, printable
invoices with bank details, partial payment recording with receipts, a
Fees Defaulters view, and CSV export. Salary module for paying teachers
(fixed + bonus − deduction) with printable slips and payment history.

**Admin tools**: Institute Settings (school branding used everywhere),
Roles & Permissions (create custom staff roles with granular access),
Staff Accounts, Certificates Generator (Leaving/Character/Bonafide),
ID Cards Generator, Reports & Analytics dashboard with charts.

**Not included** — these need paid third-party infrastructure and are out
of scope for a self-hosted PHP project: Online Store/POS, Live Class
streaming, SMS/WhatsApp gateways, multi-language UI, student/parent login
portal.

## First-time setup

1. **Copy all files** to your web server root (e.g. `htdocs/sms/` in XAMPP).

2. **Edit `install.php`**: change the `INSTALL_KEY` constant near the top
   to something random and private (e.g. a 20+ character string). Also
   confirm `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` match your MySQL setup.

3. **Edit `config/database.php`**: set the same `DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USER`, `DB_PASS` values so the live app can connect.

4. **Run the installer** by visiting:
   `http://localhost/sms/install.php?key=YOUR_INSTALL_KEY`
   Fill in your admin username, full name, and a strong password (min 8
   characters). This creates the database, all tables, and your admin account.

5. **Delete `install.php`** immediately after setup completes. Leaving an
   installer reachable on a live server is one of the most common ways
   school sites get compromised — don't skip this step.

6. **Log in** at `index.php` with the admin account you just created.

7. Start by creating **Classes & Sections**, then add **Teachers**,
   **Subjects**, and **Students**. Create teacher login accounts from the
   Teachers list page — a secure temporary password is generated and shown
   once; the teacher must change it on first login. Then visit **Institute
   Settings** to set your school logo, address, and bank details (used on
   certificates, ID cards, and fee invoices).

## Updating an existing installation

If you already ran `install.php` once, **don't** re-run it — it refuses to
run again once an admin exists. Instead:

1. Overwrite your existing `sms/` folder with the new files.
2. Open `migrate_v2.php` and set `MIGRATE_KEY` to the same key you used in
   `install.php` (or any key you choose, if `install.php` is already deleted).
3. Visit `http://localhost/sms/migrate_v2.php?key=YOUR_KEY` once. It adds
   every table/column introduced since your last update and skips anything
   that already exists — safe to run more than once if needed.
4. **Delete `migrate_v2.php`** afterward, same as `install.php`.
5. Go to **Institute Settings** and fill in your bank details if you want
   them to appear on fee invoices.
6. Existing students won't have the newer fields (CNIC, blood group, etc.)
   filled in — edit them individually as needed; it's optional, not
   required for the system to keep working.

The Reports charts load Chart.js from a CDN (`cdnjs.cloudflare.com`) — if
your server has no internet access, those specific charts won't render, but
the rest of the page (stats, tables, CSV export) still works normally.

## Folder structure

```
/config/           - DB connection, app bootstrap (session, constants)
/includes/         - shared functions, auth guards, sidebar
/modules/<name>/   - one folder per feature, each with list.php / form.php / etc.
/assets/css/       - global stylesheet
/assets/uploads/   - student, teacher & branding images (script execution blocked)
database.sql       - full schema, run automatically by install.php
install.php        - one-time setup script — DELETE after running
migrate_v2.php     - incremental schema updater for existing installs — DELETE after running
```

## Notes on production deployment

- Switch `display_errors` only when actively debugging — it's off by default
  in `config/app.php` so errors don't leak details to visitors.
- Serve the site over HTTPS in production; the session cookie automatically
  gets the `Secure` flag when `$_SERVER['HTTPS']` is set.
- Back up your database regularly — fee, salary, and exam records are not
  something you want to lose.
- The default report card grading scale (A+ at 90%, F below 33%) is a
  reasonable default; adjust `gradeFor()` in
  `modules/exams/report_card.php` if your school uses different bands.
