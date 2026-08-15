# KYC Verify

> A local **Know Your Customer (KYC)** application and document-verification system built with PHP, MySQL, and XAMPP.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white) ![XAMPP](https://img.shields.io/badge/Run%20with-XAMPP-FB7A24) ![License](https://img.shields.io/badge/Local%20project-ready-success)

KYC Verify lets applicants create a secure account, complete a KYC application, provide their addresses, and upload academic and government documents. A three-tier review team — **CEO**, **Super Admin**, and **Admin** — reviews every submission, each with its own dashboard. The system emails all staff the moment an application is submitted and emails the applicant on every review decision.

## Roles

| Role            | Dashboard                            | Capabilities                                                         |
| --------------- | ------------------------------------ | -------------------------------------------------------------------- |
| **APPLICANT**   | Personal stats & recent applications | Create, complete, submit and resubmit applications; upload documents |
| **ADMIN**       | Review queue & all applications      | Review, approve, reject, request resubmission                        |
| **SUPER_ADMIN** | Review queue + user management       | Everything Admin can do, plus create users and change roles          |
| **CEO**         | Company analytics                    | KPIs, approval rate, pipeline breakdown, email activity              |

> **Seeded staff accounts** (password for all: `Password123`): `ceo@kyc.local`, `superadmin@kyc.local`, `admin@kyc.local`

## Highlights

- Native PHP application — no Node.js, Django, Supabase, or external cloud service required
- MySQL database designed for XAMPP/phpMyAdmin
- Secure registration and sign-in using PHP password hashing and protected sessions
- Role-based dashboards: distinct views for applicants, admins, super admins, and the CEO
- **Email notifications via SMTP (PHPMailer)**:
  - On submission → CEO, Super Admin, and Admin are all notified at once
  - On resubmission request / rejection / approval → the applicant is emailed with review notes
  - Every email is logged in `email_logs`, so the flow can be verified even without a mail server
- Permanent and temporary address details
- Education-document uploads: **SEE**, **SLC**, and **Graduate** certificates
- Government-document uploads: **Citizenship**, **Passport**, and **License**
- JPG, PNG, and PDF validation with a 5 MB upload limit
- Draft, submit, approve, reject, and resubmission application workflow
- Complete audit trail of every application event
- Clean, responsive multi-file codebase (pages/actions split, zero legacy)

## Technology

| Layer            | Used technology                            |
| ---------------- | ------------------------------------------ |
| Web server       | Apache, included with XAMPP                |
| Backend          | PHP 8.0+                                   |
| Database         | MySQL / MariaDB                            |
| Database access  | PDO prepared statements                    |
| Authentication   | PHP sessions + `password_hash()`           |
| Email            | PHPMailer over SMTP (optional)             |
| Document storage | Local `uploads/users/<user-id>/` directory |
| Styling          | Responsive custom CSS                      |

## Quick start with XAMPP

1. Copy this project folder to your XAMPP web root, for example:

   ```text
   C:\xampp\htdocs\kyc-v4
   ```

2. Install PHP dependencies (PHPMailer). With [Composer](https://getcomposer.org) installed:

   ```text
   cd C:\xampp\htdocs\kyc-v4
   composer install
   ```

3. Open the XAMPP Control Panel and start **Apache** and **MySQL**.

4. Open phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).

5. Select **Import**, choose [install.sql](install.sql), then click **Import**. This creates the `kyc_system` database, all tables, and the seeded staff accounts.

6. Open the application:

   ```text
   http://localhost/kyc-v4/
   ```

7. Register an applicant account, create an application, and submit it — the review team will be notified by email.

> **Important:** If you imported an older version of this project schema, first remove the old `kyc_system` database in phpMyAdmin, then import the revised [install.sql](install.sql). This prevents old tables or columns from conflicting with the normalized design.

## Email / SMTP configuration

Email is **disabled by default** (`MAIL_ENABLED = false`). Every message is still recorded in the `email_logs` table, so you can verify notifications without a mail server.

To send real email, edit [config.php](config.php):

```php
define('MAIL_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password'); // Google App Password, not your normal password
define('SMTP_ENCRYPTION', 'tls');
define('MAIL_FROM', 'no-reply@kyc.local');
define('MAIL_FROM_NAME', 'KYC Verify');
define('APP_URL', 'http://localhost/kyc-v4'); // base URL used in email links
```

For Gmail you must enable **2-Step Verification** and create an **App Password**. For other providers adjust `SMTP_HOST`, `SMTP_PORT`, and `SMTP_ENCRYPTION` accordingly.

## Database configuration

The default XAMPP MySQL credentials are already set in [config.php](config.php):

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kyc_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Update these values only if your MySQL username, password, or database name is different.

## Database design

`users` is the parent table. All user-profile data uses `user_id` as a foreign key to `users.id`.

```text
users (id)
 ├── addresses (user_id)
 ├── education (user_id)
 ├── additional_documents (user_id)
 └── applications (applicant_id)
       └── audit_logs (application_id)
```

| Table                  | Main fields                                                                | Purpose                                         |
| ---------------------- | -------------------------------------------------------------------------- | ----------------------------------------------- |
| `users`                | `id`, `username`, `email`, `password_hash`, `role`                         | Applicant, admin, super admin, and CEO accounts |
| `addresses`            | `user_id`, `permanent_address`, `temporary_address`                        | A user's address record                         |
| `education`            | `user_id`, `see_document`, `slc_document`, `graduate_document`             | Paths for academic documents                    |
| `additional_documents` | `user_id`, `citizenship_document`, `passport_document`, `license_document` | Paths for government identity documents         |
| `applications`         | `applicant_id`, KYC details, `status`, review data                         | KYC workflow and review status                  |
| `audit_logs`           | `application_id`, `actor_id`, `action`, `detail`                           | Record of important application events          |
| `email_logs`           | `recipient`, `subject`, `body`, `status`, `error`                          | Outbox for every email the system sends         |

The `addresses`, `education`, and `additional_documents` tables each have a `UNIQUE user_id` constraint. This means each user has one current address record, one education-document record, and one additional-document record. All of these foreign keys use `ON DELETE CASCADE`.

## Application workflow

```text
DRAFT → SUBMITTED → UNDER REVIEW → APPROVED
                                ├→ REJECTED
                                └→ RESUBMISSION REQUESTED → DRAFT/UPDATED → SUBMITTED
```

## Project structure

```text
index.php          Front controller — sessions, CSRF, routing
config.php         Database, SMTP, and app settings
database.php       PDO connection
functions.php      Core helpers (output, uploads, queries, badges)
auth.php           Login / role helpers
mailer.php         Email sending via PHPMailer + email_logs
actions.php        All POST handlers (auth, applications, review, users)
layout.php         Shared header / footer / navigation
pages/             One file per page (login, dashboard, review, users, …)
assets/style.css   Design system and responsive layout
install.sql        Schema + seeded staff accounts
```
