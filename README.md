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

## How staff (CEO / Admin / Super Admin) get accounts

Staff members **cannot sign up through the public registration page** — that form always creates an `APPLICANT` account (`role` defaults to `APPLICANT` in the database). Staff accounts are created in two ways:

1. **Seeded accounts** — `install.sql` inserts one account per staff role when you import it. Sign in with the credentials above.
2. **Super Admin creates them** — the **Super Admin** is the gatekeeper. After signing in as `superadmin@kyc.local`, open **Users** (`?page=users`) and:
   - **Create a user** and choose the role: `APPLICANT`, `ADMIN`, `SUPER_ADMIN`, or `CEO`
   - **Change an existing user's role** — for example, promote an applicant to Admin
   - **Reset a user's password**

All user-management actions are protected by `require_role(['SUPER_ADMIN'])`, so a random person can never register themselves as CEO, Admin, or Super Admin.

### Sign-in flow for staff

```text
Sign in → ?page=login → role-based dashboard
   CEO          → company analytics (KPIs, pipeline, email activity)
   SUPER_ADMIN  → review queue + user management
   ADMIN        → review queue + all applications
   APPLICANT    → personal KYC dashboard
```

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

2. Create your local environment file from the template:

   ```text
   cp .env.example .env
   ```

   On Windows (Git Bash / PowerShell):

   ```text
   copy .env.example .env
   ```

   The `.env` file holds your database and SMTP settings. It is **ignored by git**, so your credentials never get committed. If you skip this step the built-in defaults are used.

3. Install PHP dependencies (PHPMailer). With [Composer](https://getcomposer.org) installed:

   ```text
   cd C:\xampp\htdocs\kyc-v4
   composer install
   ```

4. Open the XAMPP Control Panel and start **Apache** and **MySQL**.

5. Open phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).

6. Select **Import**, choose [install.sql](install.sql), then click **Import**. This creates the `kyc_system` database, all tables, and the seeded staff accounts.

7. Open the application:

   ```text
   http://localhost/kyc-v4/
   ```

8. Register an applicant account, create an application, and submit it — the review team will be notified by email.

> **Important:** If you imported an older version of this project schema, first remove the old `kyc_system` database in phpMyAdmin, then import the revised [install.sql](install.sql). This prevents old tables or columns from conflicting with the normalized design.

## Configuration with `.env`

All settings live in `.env` (copy it from `.env.example`). [config.php](config.php) loads the file at startup with a lightweight built-in parser — no extra package needed — and falls back to sensible defaults when a variable is missing or the file does not exist.

| Variable           | Default                   | Description                                 |
| ------------------ | ------------------------- | ------------------------------------------- |
| `APP_NAME`         | `KYC Verify`              | Application name shown in the UI and emails |
| `APP_URL`          | `http://localhost/kyc-v4` | Base URL used in email links                |
| `UPLOAD_DIR`       | `uploads`                 | Folder where uploaded documents are stored  |
| `MAX_UPLOAD_BYTES` | `5242880`                 | Maximum upload size in bytes (5 MB)         |
| `DB_HOST`          | `127.0.0.1`               | MySQL host                                  |
| `DB_NAME`          | `kyc_system`              | MySQL database name                         |
| `DB_USER`          | `root`                    | MySQL username                              |
| `DB_PASS`          | _(empty)_                 | MySQL password                              |
| `MAIL_ENABLED`     | `false`                   | Set `true` to send real email via SMTP      |
| `SMTP_HOST`        | `smtp.gmail.com`          | SMTP server                                 |
| `SMTP_PORT`        | `587`                     | SMTP port                                   |
| `SMTP_USER`        | `your-email@gmail.com`    | SMTP username                               |
| `SMTP_PASS`        | `your-app-password`       | SMTP password / app password                |
| `SMTP_ENCRYPTION`  | `tls`                     | `tls` or `ssl`                              |
| `MAIL_FROM`        | `no-reply@kyc.local`      | From address for outgoing mail              |
| `MAIL_FROM_NAME`   | `KYC Verify`              | From name for outgoing mail                 |
| `APP_TIMEZONE`     | `Asia/Kathmandu`          | Application timezone                        |

Example `.env`:

```dotenv
APP_NAME="KYC Verify"
APP_URL="http://localhost/kyc-v4"

DB_HOST="127.0.0.1"
DB_NAME="kyc_system"
DB_USER="root"
DB_PASS=""

MAIL_ENABLED="true"
SMTP_HOST="smtp.gmail.com"
SMTP_PORT="587"
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-app-password"
SMTP_ENCRYPTION="tls"
MAIL_FROM="no-reply@kyc.local"
MAIL_FROM_NAME="KYC Verify"
```

> `MAIL_ENABLED`, `SMTP_PORT`, and `MAX_UPLOAD_BYTES` are parsed as real boolean / integer values, so use `true`/`false` and plain numbers there.

## Email / SMTP configuration

Email is **disabled by default** (`MAIL_ENABLED = false`). Every message is still recorded in the `email_logs` table, so you can verify notifications without a mail server.

To send real email, set these values in `.env`:

```dotenv
MAIL_ENABLED="true"
SMTP_HOST="smtp.gmail.com"
SMTP_PORT="587"
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-app-password"   # Google App Password, not your normal password
SMTP_ENCRYPTION="tls"
MAIL_FROM="no-reply@kyc.local"
MAIL_FROM_NAME="KYC Verify"
APP_URL="http://localhost/kyc-v4"   # base URL used in email links
```

For Gmail you must enable **2-Step Verification** and create an **App Password**. For other providers adjust `SMTP_HOST`, `SMTP_PORT`, and `SMTP_ENCRYPTION` accordingly.

## Database configuration

The default XAMPP MySQL credentials are set in `.env`:

```dotenv
DB_HOST="127.0.0.1"
DB_NAME="kyc_system"
DB_USER="root"
DB_PASS=""
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
config.php         Loads .env and defines all app settings
.env.example       Template for your local .env (copy to .env)
.env               Your local secrets (ignored by git, not committed)
database.php       PDO connection
functions.php      Core helpers (output, uploads, queries, badges)
auth.php           Login / role helpers
mailer.php         Email sending via PHPMailer + email_logs
actions.php        All POST handlers (auth, applications, review, users)
layout.php         Shared header / footer / navigation
pages/             One file per page (login, dashboard, review, users, ...)
assets/style.css   Design system and responsive layout
install.sql        Schema + seeded staff accounts
.gitignore         Excludes .env, uploads, and vendor from git
```
