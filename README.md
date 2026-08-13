# KYC Verify

> A local **Know Your Customer (KYC)** application and document-verification system built with PHP, MySQL, and XAMPP.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white) ![XAMPP](https://img.shields.io/badge/Run%20with-XAMPP-FB7A24) ![License](https://img.shields.io/badge/Local%20project-ready-success)

KYC Verify lets applicants create a secure account, complete a KYC application, provide their addresses, and upload academic and government documents. Reviewers and administrators can then review applications and record a final decision.

## Highlights

- Native PHP application — no Node.js, Django, Supabase, or external cloud service required
- MySQL database designed for XAMPP/phpMyAdmin
- Secure registration and sign-in using PHP password hashing and protected sessions
- Permanent and temporary address details
- Education-document uploads: **SEE**, **SLC**, and **Graduate** certificates
- Government-document uploads: **Citizenship**, **Passport**, and **License**
- JPG, PNG, and PDF validation with a 5 MB upload limit
- Draft, submit, approve, reject, and resubmission application workflow
- Reviewer/admin review queue and a complete audit trail

## Technology

| Layer | Used technology |
| --- | --- |
| Web server | Apache, included with XAMPP |
| Backend | PHP 8.0+ |
| Database | MySQL / MariaDB |
| Database access | PDO prepared statements |
| Authentication | PHP sessions + `password_hash()` |
| Document storage | Local `uploads/users/<user-id>/` directory |
| Styling | Responsive custom CSS |

## Quick start with XAMPP

1. Copy this project folder to your XAMPP web root, for example:

   ```text
   C:\xampp\htdocs\kyc-v4
   ```

2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.

3. Open phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).

4. Select **Import**, choose [install.sql](install.sql), then click **Import**.

5. Open the application:

   ```text
   http://localhost/kyc-v4/
   ```

6. Register an applicant account and create an application.

> **Important:** If you imported an older version of this project schema, first remove the old `kyc_system` database in phpMyAdmin, then import the revised [install.sql](install.sql). This prevents old tables or columns from conflicting with the normalized design.

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

| Table | Main fields | Purpose |
| --- | --- | --- |
| `users` | `id`, `username`, `email`, `password_hash`, `role` | Applicant, reviewer, and administrator accounts |
| `addresses` | `user_id`, `permanent_address`, `temporary_address` | A user's address record |
| `education` | `user_id`, `see_document`, `slc_document`, `graduate_document` | Paths for academic documents |
| `additional_documents` | `user_id`, `citizenship_document`, `passport_document`, `license_document` | Paths for government identity documents |
| `applications` | `applicant_id`, KYC details, `status`, review data | KYC workflow and review status |
| `audit_logs` | `application_id`, `actor_id`, `action`, `detail` | Record of important application events |

The `addresses`, `education`, and `additional_documents` tables each have a `UNIQUE user_id` constraint. This means each user has one current address record, one education-document record, and one additional-document record. All of these foreign keys use `ON DELETE CASCADE`.

## Application workflow

```text
DRAFT → SUBMITTED → UNDER REVIEW → APPROVED
                                ├→ REJECTED
                                └→ RESUBMISSION REQUESTED → DRAFT/UPDATED → SUBMITTED
```

1. An applicant registers and creates a draft application.
2. The applicant saves personal KYC details and permanent/temporary addresses.
3. The applicant uploads education and additional documents.
4. The applicant submits the completed application.
5. A reviewer or administrator approves it, rejects it, or requests resubmission with notes.
6. Each important action is recorded in the audit log.

## Document rules

| Category | Required upload fields |
| --- | --- |
| Education | SEE document, SLC document, Graduate document |
| Additional documents | Citizenship, Passport, License |
| File types | `.jpg`, `.jpeg`, `.png`, `.pdf` |
| Maximum file size | 5 MB per file |
| Saved location | `uploads/users/<user-id>/` |

On Linux or macOS, make sure Apache has write access to the `uploads` directory. On a standard Windows XAMPP installation this works by default.

## Roles

| Role | Permissions |
| --- | --- |
| `APPLICANT` | Register, edit drafts, save addresses, upload personal documents, and submit applications |
| `REVIEWER` | View the review queue and approve, reject, or request resubmission |
| `ADMIN` | Has all reviewer permissions |

New accounts are applicants by default. Promote an account after it is registered by running this query in phpMyAdmin:

```sql
UPDATE users SET role = 'ADMIN' WHERE email = 'admin@example.com';
-- Or:
UPDATE users SET role = 'REVIEWER' WHERE email = 'reviewer@example.com';
```

## Project structure

```text
kyc-v4/
├── assets/
│   └── style.css          # Responsive interface styling
├── uploads/
│   └── users/             # Uploaded user documents (created as needed)
├── config.php             # XAMPP/MySQL settings and upload size
├── database.php           # PDO MySQL connection
├── functions.php          # Shared authentication, audit, and upload helpers
├── index.php              # Application routes and user interface
├── install.sql            # Complete MySQL schema
└── README.md              # Project documentation
```

## Security measures included

- Passwords are stored with PHP's `password_hash()`; plain-text passwords are never stored.
- Database operations use PDO prepared statements.
- Forms use CSRF tokens.
- Sessions use `HttpOnly` cookies and regenerate the session ID on login.
- Uploads are restricted by MIME type and file size.
- Uploaded files use random generated filenames rather than the original filename.
- Role checks protect the reviewer queue and review actions.

## Troubleshooting

| Problem | Solution |
| --- | --- |
| `Database error` message | Confirm MySQL is running and check credentials in `config.php`. |
| `Access denied for user root` | Set the correct MySQL password in `DB_PASS` in `config.php`. |
| `Table ... does not exist` | Import `install.sql` again into phpMyAdmin. |
| Upload does not save | Ensure Apache can write to the `uploads` folder. |
| Page not found | Confirm the project folder is inside `xampp/htdocs` and use the matching URL. |
| Old schema conflicts | Drop the old `kyc_system` database, then import the current `install.sql`. |

## Verification

PHP syntax checks have been run for the project PHP files:

```bash
php -l index.php
php -l functions.php
php -l database.php
php -l config.php
```
