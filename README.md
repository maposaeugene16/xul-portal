# Mavambo SchoolOS

PHP/MySQL school administration portal for Zimbabwean secondary schools. It is being built module by module: account security, learner records, academics, attendance, fees and finance, communications, reports, and school configuration.

## Local development

This computer has XAMPP 8.2.12 and MariaDB 10.4.32 running on port 3306. MariaDB is the MySQL-compatible database shipped with XAMPP. If another database process already owns port 3306, keep that server running and do not start a second XAMPP MySQL service.

On Windows, double-click `Start Mavambo Portal.vbs` to start the PHP server in the background and open `http://127.0.0.1:8000`. It checks the configured database host/port, reuses an already-running portal, and shows a message if XAMPP MySQL or PHP needs attention. Start the MySQL service from the XAMPP Control Panel first. The PowerShell implementation is in `start-portal.ps1` for review. On first open, create the school administrator account in the browser. Use a unique password of at least 12 characters; do not share it.

After signing in, configure the school profile, term calendar and class groups. Mark exactly one term as current before enrolling learners or confirming a CSV import; the portal will not guess a term when the calendar is unset. Create each form/stream class before enrolling learners into it.

For a fresh database, import `database/schema.sql`. Existing installations apply any missing scripts in order from `database/migrations/`. Library, welfare, admissions, inventory, ZIMSEC examination entries, staffing, payroll, boarding, sign-in rate limits, transport, activities and operating expenses add migrations `010_library.sql` through `021_school_expenses.sql`. In phpMyAdmin, select `mavambo_school`, open **Import**, choose each missing file in order, then press **Import**. These scripts use `IF NOT EXISTS` for their tables.

Connection values can be set with `SCHOOL_DB_HOST`, `SCHOOL_DB_PORT`, `SCHOOL_DB_NAME`, `SCHOOL_DB_USER`, and `SCHOOL_DB_PASSWORD`. A git-ignored `config.local.php` can hold local development credentials. Environment variables take precedence.

## Current modules

- Secure first-administrator setup, password login with session and database-backed rate limits, logout, role checks, session hardening and CSRF protection
- Role-scoped teacher dashboard learner and attendance totals based on current class/subject assignments; fee totals appear only for roles with fee access
- Administrator-only filtered audit log for record changes, actors, timestamps and collapsed event details
- Staff accounts with role assignment, initial and reset-password change enforcement, active-administrator protection during concurrent role changes and school profile settings
- Staff daily attendance register with check-in/out times, leave requests, leadership approval, overlap checks and review history
- Confidential school payroll with USD/ZiG salary settings, non-overlapping draft periods, itemized earnings/deductions, a preparer who cannot approve their own run, payment by someone other than the approver, payment references and staff-only paid payslips
- Boarding rooms/dormitories, gender-aware active learner allocation, capacity checks, room moves and retained move-out history; consented guardians can view linked learner boarding details
- Fleet vehicles, inspection/insurance dates, route stops/times, seat-limited learner assignments, move history and daily outbound/return bus registers; guardians see only linked learner routes and recent register updates
- Co-curricular sports, clubs and academic/cultural/service groups; learner membership, capacity limits, meetings/fixtures/trips, attendance and results; guardians see linked learner participation
- Learner directory with search, filters, pagination, profiles, create/update, status changes, class history and CSV export; CSV bulk import with template download, row validation, duplicate checks, preview, and all-or-nothing enrolment creation
- Administrator/head class progression for selected cohorts, tied to destination school-term dates and configured destination classes, with retained class history and Form 6 completion
- Parent/legal guardian contact and consent recording; changing/removing linked contact details or moving consent away from Granted revokes the old portal link and requires verification/relinking before access can be restored
- Academic setup for Forms 1–6, class streams, teacher/class-subject assignments, O-Level/A-Level subject catalog and school terms; enrolment requires exactly one configured current term, and concurrent term changes are serialized
- Class/date attendance registers with Present, Absent, Late and Excused statuses, using the learner's dated class enrollment for the register date
- Assessment creation and mark entry scoped to classes, terms, subjects and assigned teachers; mark rosters use learner enrolments overlapping the assessment term instead of matching the learner’s current form/stream alone
- School-configurable O-Level/A-Level grade scales, term report cards, attendance summaries and teacher/head comments
- Term timetables with class, teacher, period-time and venue conflict checks
- Library catalog and loan register with copy availability, active learner issue checks, due dates, overdue indicators, returns and audit history
- Confidential welfare and discipline incident reporting, teacher class-assignment scope, leadership review, follow-up assignment, recorded outcomes and guardian contact history
- Admissions register with application review, waitlist, offer expiry, acceptance, decision history and transactional learner enrollment into current or upcoming configured terms
- School stores inventory for receipts, issues, write-offs and signed adjustments, with stock locks, non-negative balance protection, reorder alerts and immutable movement history
- ZIMSEC O-Level/A-Level school-side entry registers with configurable sessions, offered subjects, formal/nonformal and self/BEAM details, repeat-subject references, review CSV, controlled school submission status and audited restoration of withdrawn entries during an open window
- Announcement drafts, audience-targeted publication, read receipts, and school calendar events
- Guardian portal logins linked to consented learner contacts, with scoped learner attendance, results and fee balances
- Fee schedules by term, category, form and currency; learner balances; USD and ZiG transactions; numbered receipts; immutable reversals and audit history
- School operating expenses with submission, independent administrator/head approval, payment recording by someone other than the approver, USD/ZiG totals, filtered CSV export, and retained review/payment history
- Annual finance report for administrators, heads and bursars, netting reversed fee receipts and comparing fee receipts with paid operating expenses and payroll by currency
- Administrator-only SQL database backup download with a consistent InnoDB snapshot, audit logging, and restore guidance
- Administrator deployment-readiness page checking live schema coverage, database engine/account, HTTPS, application mode, school profile and backup requests
- MySQL/MariaDB schema and migrations for terms, subjects, classes, assessments, attendance, fees, users, announcements and library loans
- Zimbabwe-oriented Forms 1–6 and O-Level/A-Level language, with separate USD/ZiG ledgers

## Privacy and production readiness

Learner records are personal information and may belong to children. The portal records guardian consent method and reference and applies role-based limits to learner details. Schools must configure their own approved privacy notice and lawful basis, verify guardian authority, meet licensing and data-protection obligations, and decide retention periods before using real learner data. Refer to the [Cyber and Data Protection Act](https://zimlii.org/akn/zw/act/2021/5/eng%402022-03-11) and [POTRAZ implementation guidance for children’s personal information](https://www.veritaszim.net/node/7801).

The current local MariaDB root account has no password. A separate limited application account could not be created because MariaDB returned an Aria system-table checksum error. The portal is for local development only until that database issue is repaired, a limited account is created, authentication is fully administered, HTTPS and protected off-device backup storage are configured, and the remaining workflows are completed. The administrator backup download contains private learner, guardian and staff records plus password hashes; store it in an access-controlled location. Do not use this local setup for real learner data.

Payroll note: the portal stores salary snapshots, earnings, deductions, approvals and payment references. It does not calculate PAYE, NSSA or other statutory amounts automatically; the school must verify current Zimbabwe requirements and enter approved payroll items before approving a run.
