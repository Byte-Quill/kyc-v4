# 🚀 How to Run KYC Verify — Step by Step

Simple instructions for **Windows**, **Linux (Debian / Linux Mint)**, and **macOS**.
The app runs on **XAMPP** (Apache + PHP + MySQL) — no Node.js, no build step.

---

## What you need (all platforms)

1. **XAMPP** — download from https://www.apachefriends.org
   - It includes Apache, PHP 8, and MySQL/MariaDB — everything the app needs.
2. **This project folder** (`kyc-v4`).

> 💡 Composer is **not required** — the PHPMailer library is already included
> in the `vendor/` folder.

---

## 🪟 Windows

### Step 1 — Install XAMPP

1. Download the Windows installer from https://www.apachefriends.org
2. Run it and install to the default location: `C:\xampp`

### Step 2 — Copy the project into XAMPP

1. Copy the whole `kyc-v4` folder into:
   ```text
   C:\xampp\htdocs\
   ```
2. You should now have `C:\xampp\htdocs\kyc-v4\index.php`

### Step 3 — Create the environment file

1. Inside `C:\xampp\htdocs\kyc-v4`, make a copy of `.env.example` and name it `.env`
2. The defaults work out of the box — no changes needed for a first run.

### Step 4 — Start Apache and MySQL

1. Open the **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

### Step 5 — Import the database

1. Open your browser and go to: http://localhost/phpmyadmin
2. Click the **Import** tab at the top
3. Click **Choose File** and select `C:\xampp\htdocs\kyc-v4\install.sql`
4. Click **Go** — this creates the `kyc_system` database with demo accounts

### Step 6 — Open the app 🎉

```text
http://localhost/kyc-v4/
```

---

## 🐧 Linux (Debian / Linux Mint)

### Step 1 — Install XAMPP

First **download** the installer (about 151 MB), then run it:

```bash
# 1. Download the installer into your Downloads folder
cd ~/Downloads
wget "https://sourceforge.net/projects/xampp/files/XAMPP%20Linux/8.2.12/xampp-linux-x64-8.2.12-0-installer.run"

# 2. Make it executable
chmod +x xampp-linux-x64-8.2.12-0-installer.run

# 3. Run the installer
sudo ./xampp-linux-x64-8.2.12-0-installer.run
```

> 💡 If `wget` is not installed, use `curl -L -o xampp-linux-x64-8.2.12-0-installer.run "https://sourceforge.net/projects/xampp/files/XAMPP%20Linux/8.2.12/xampp-linux-x64-8.2.12-0-installer.run"` instead, or download it in your browser from https://www.apachefriends.org/download.html and run steps 2–3 from the folder where it landed.

Follow the installer wizard (defaults are fine). XAMPP installs to `/opt/lampp`.

### Step 2 — Put the project in place

**Option A — automatic (recommended):** open a terminal in the project folder and run:

```bash
cd /path/to/kyc-v4
sudo bash deploy-xampp.sh
```

This single script copies the code into `/opt/lampp/htdocs/kyc-v4`, makes the
`uploads/` folder writable, starts Apache + MySQL, imports the database (first
time only), and prints a health check.

**Option B — manual:**

```bash
sudo cp -r /path/to/kyc-v4 /opt/lampp/htdocs/
sudo chmod -R 777 /opt/lampp/htdocs/kyc-v4/uploads
cp /opt/lampp/htdocs/kyc-v4/.env.example /opt/lampp/htdocs/kyc-v4/.env
sudo /opt/lampp/lampp start
```

Then import the database:

```bash
sudo /opt/lampp/bin/mysql -uroot < /opt/lampp/htdocs/kyc-v4/install.sql
```

### Step 3 — Open the app 🎉

```text
http://localhost/kyc-v4/
```

> 💡 Any time you change the code, just re-run `sudo bash deploy-xampp.sh` to
> sync it into XAMPP (it also adds any missing database indexes).

---

## 🍎 macOS

### Step 1 — Install XAMPP

1. Download the macOS installer (`.dmg`) from https://www.apachefriends.org
2. Open it and drag **XAMPP** into your **Applications** folder
3. Open **XAMPP** from Applications → go to the **Manage Servers** tab

### Step 2 — Copy the project into XAMPP

1. Copy the whole `kyc-v4` folder into:
   ```text
   /Applications/XAMPP/htdocs/
   ```
2. You should now have `/Applications/XAMPP/htdocs/kyc-v4/index.php`

### Step 3 — Create the environment file

1. Inside `/Applications/XAMPP/htdocs/kyc-v4`, make a copy of `.env.example` and name it `.env`
2. The defaults work out of the box — no changes needed for a first run.

### Step 4 — Start Apache and MySQL

1. In the XAMPP app, open **Manage Servers**
2. Select **Apache Web Server** → click **Start**
3. Select **MySQL Database** → click **Start**

### Step 5 — Import the database

1. Open your browser and go to: http://localhost/phpmyadmin
2. Click the **Import** tab at the top
3. Click **Choose File** and select `/Applications/XAMPP/htdocs/kyc-v4/install.sql`
4. Click **Go** — this creates the `kyc_system` database with demo accounts

### Step 6 — Open the app 🎉

```text
http://localhost/kyc-v4/
```

---

## 🔑 Demo logins (all platforms)

The database comes with three staff accounts ready to use:

| Role        | Email                  | Password      |
| ----------- | ---------------------- | ------------- |
| CEO         | `ceo@kyc.local`        | `Password123` |
| Super Admin | `superadmin@kyc.local` | `Password123` |
| Admin       | `admin@kyc.local`      | `Password123` |

You can also click **Create an account** on the login page to register a new
**Applicant** and test the full submit → review → approve flow.

> ⚠️ These are development-only credentials — change them before any real deployment.

---

## 🩺 Troubleshooting

| Problem                                                | Fix                                                                                                                                                                                      |
| ------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **"Unable to connect to the database"**                | Make sure MySQL is started in the XAMPP Control Panel / Manage Servers                                                                                                                   |
| **Page is blank or 404**                               | Check the folder is named `kyc-v4` inside `htdocs`, and that `index.php` is inside it                                                                                                    |
| **Port 80 already in use** (Windows)                   | Stop Skype/IIS or change Apache's port in XAMPP Config                                                                                                                                   |
| **Upload fails with "Unable to create upload folder"** | Make the `uploads/` folder writable (Linux: `sudo chmod -R 777 uploads`)                                                                                                                 |
| **Old database errors after updating the project**     | In phpMyAdmin, drop the `kyc_system` database, then import `install.sql` again                                                                                                           |
| **Emails not arriving**                                | Email is off by default (`MAIL_ENABLED=false` in `.env`). Messages are still saved in the `email_logs` table. See the README "Email / SMTP configuration" section to enable real sending |

---

## 📧 Optional: enable real email sending

1. Open `.env` in the project folder
2. Set:
   ```dotenv
   MAIL_ENABLED="true"
   SMTP_USER="your-email@gmail.com"
   SMTP_PASS="your-app-password"
   ```
   (For Gmail: enable 2-Step Verification and create an **App Password** — your
   normal password will not work.)
3. Reload the app.
