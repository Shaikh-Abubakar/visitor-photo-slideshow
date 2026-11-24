# Quick start: Windows XAMPP

For the corrected folder layout and complete XAMPP database setup, follow [SETUP_XAMPP.md](SETUP_XAMPP.md). The ZIP includes a local `.env` configured for the common XAMPP default (`root` with an empty MySQL password). If your MySQL root account has a password, update `DB_PASSWORD` in `.env`.

---

# Visitor Photo Slideshow System

A PHP and MySQL web application, built as College Assignment 1, that lets visitors upload photographs,
record their consent, and have their photographs shown on a public slideshow.

> **Current status: Step 8 implemented in code (administrator dashboard); database/browser integration tests remain to be run.**
> The folder structure, database schema, documentation, configuration loader, database connection, visitor registration
> (`register.php`, Step 3), returning visitor identification (`identify.php`, Step 4), the photo upload page
> (`upload.php`, Step 5) and the **visitor gallery** (`gallery.php` with the private image endpoint `photo.php`, Step 6) exist.
> An identified visitor can upload photographs and later see all of their own photographs, including those from earlier visits. The public slideshow is implemented in Step 7 using HTML meta refresh.
> **Not built yet:** administrator screens.
> **Testing:** the Step 7 PHP syntax checks passed, but database-backed slideshow behavior and real-browser/XAMPP tests were not run in this environment. Earlier Step 1–6 test claims are historical; see `AI_LOG.md`.

------|--------|
| Language | PHP 8.2 or newer (`pdo_mysql`, `fileinfo`, `mbstring`) |
| Database | MySQL 8+ or compatible MariaDB (InnoDB, utf8mb4) |
| Database access | PDO with prepared statements |
| Web server | Apache 2.4 (XAMPP on Windows, `apache2` on Ubuntu) |
| Browser presentation | CSS is used only by the public slideshow; JavaScript is not used |
| Not used | Frameworks, Composer packages, JavaScript, Node.js, Python |

## Planned project modules

| Module | Purpose | Step |
|--------|---------|------|
| Foundation and database | Structure, schema, documentation | **1 (done)** |
| Configuration and database connection | Read `.env`, connect with PDO | **2 (done)** |
| Visitor registration and code | Create visitor, generate and hash code | **3 (done)** |
| Returning visitor identification | Enter the visitor code, keep the identity in a secure session | **4 (written, not yet tested by anyone)** |
| Consent and photo upload and storage | Consent record per upload session; validate, store privately, save metadata | **5 (written, not yet tested by anyone)** |
| Visitor gallery | Returning visitors see their photographs (paginated) | **6 (implemented)** |
| Public slideshow | Show explicitly consented, active photographs | **7 (implemented; database/browser tests pending)** |
| Administration | Login, visitor and photo management, settings | planned |

## Project structure

```
visitor-photo-slideshow/
├── config/
│   ├── config.php     configuration loader (reads .env and environment variables)
│   └── database.php   reusable PDO connection function
├── includes/
│   ├── bootstrap.php  common start-up file that every page loads
│   ├── security.php   secure session, CSRF token, security headers, output escaping (Step 3)
│   ├── visitors.php   name validation, visitor-code generation and hashing, insert (Step 3)
│   ├── visitor_auth.php   code lookup, visitor session, logout, wrong-code throttle (Step 4)
│   ├── uploads.php        upload validation, private storage, consent and photo records (Step 5)
│   ├── gallery.php        gallery queries, visibility rule, number validation, safe file path (Step 6)
│   └── slideshow.php      public slideshow selection and safe file checks (Step 7)
├── admin/             administrator pages (empty for now)
├── database/
│   └── schema.sql     database tables
├── storage/
│   └── uploads/       private photograph storage (normally excluded from version control; this supplied ZIP contains nine small sample PNGs)
├── tests/
│   ├── check_slideshow_rendering.php  static CSS and eligibility checks (Step 7)
│   ├── check_database.php   command-line check of the configuration and database
│   ├── check_identify.php   command-line check of the Step 4 lookup and throttle (changes nothing)
│   ├── check_upload.php     command-line check of the Step 5 upload logic (changes nothing)
│   └── check_gallery.php    command-line check of the Step 6 gallery logic (changes nothing)
├── register.php       visitor registration page (Step 3)
├── identify.php       returning visitor identification page (Step 4)
├── upload.php         photo upload page (Step 5)
├── gallery.php        visitor photo gallery (Step 6)
├── photo.php          private image endpoint: sends one photograph to its owner (Step 6)
├── slideshow.php      public one-photo-at-a-time slideshow page (Step 7)
├── slideshow.css      responsive TV/computer display styling (Step 7)
├── slideshow_photo.php public image endpoint; rechecks consent and private storage (Step 7)
├── .env.example       configuration template (placeholders only)
├── .gitignore
├── README.md
├── ASSUMPTIONS.md
├── ARCHITECTURE.md
└── AI_LOG.md
```

`.htaccess` files in `config/`, `includes/`, `database/`, `storage/` and `tests/` deny direct web access.

## Requirements

* PHP 8.2 or newer with `pdo_mysql`, `fileinfo` and `mbstring` (all available on Windows and Ubuntu).
* MySQL 8+ or MariaDB. **XAMPP includes MariaDB, not MySQL.** The schema works on both.
* Apache 2.4.
* PHP upload limits raised as shown below.

### Required PHP upload settings

One upload request can carry 10 photographs × 5 MB = **50 MB**. PHP's built-in defaults
(`upload_max_filesize = 2M`, `post_max_size = 8M`) are too small, and uploads would fail before the
application sees them. Use at least:

```ini
file_uploads = On
upload_max_filesize = 6M
post_max_size = 64M
max_file_uploads = 20
```

`upload_max_filesize` is slightly above 5 MB so the application can show a clear "file too large"
message instead of PHP silently dropping the file. `max_file_uploads` must be at least 10.

## Initial database setup (both platforms)

The database is **created by you first**; `schema.sql` only creates the tables inside it.

1. Create an empty database named `visitor_photo_slideshow` with `utf8mb4` and `utf8mb4_unicode_ci`.
2. Create a dedicated database user (do not run the application as `root`).
3. Import `database/schema.sql` into that database.
4. Check that five tables exist: `visitors`, `consents`, `photos`, `admins`, `settings`.

`schema.sql` creates tables only. It contains no administrator account and no sample data.
Import it into an **empty** database: if the tables already exist, the import stops with
"Table ... already exists" instead of overwriting anything.

**Consent records.** The `consents` table allows many rows for the same visitor and the same consent version,
because a new consent record is created for every upload session. Do not add a unique key on
`(visitor_id, consent_version)`.

**Already imported the earlier Step 1 schema?** That version had a unique key `uq_consents_visitor_version`,
which blocks a second consent record with the same version. Either drop and re-import into an empty
database, or run this one statement (it keeps your data; the order inside it matters because the foreign key
needs an index at all times):

```sql
ALTER TABLE consents
    ADD KEY idx_consents_visitor_version (visitor_id, consent_version),
    DROP INDEX uq_consents_visitor_version;
```

SQL to create the database and user (run in phpMyAdmin's **SQL** tab, or the `mysql` command line).
Replace the password; the user is created for both `localhost` and `127.0.0.1` because the
application connects over TCP (`127.0.0.1`):

```sql
CREATE DATABASE visitor_photo_slideshow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vps_user'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
CREATE USER 'vps_user'@'127.0.0.1' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON visitor_photo_slideshow.* TO 'vps_user'@'localhost';
GRANT ALL PRIVILEGES ON visitor_photo_slideshow.* TO 'vps_user'@'127.0.0.1';
```

## Configuration and database connection (both platforms)

### How configuration works

Every setting is read by `config/config.php` from, in this order:

1. a **real environment variable** (for example set in Apache or in the shell),
2. the optional local file **`.env`** in the project folder (copy `.env.example` to `.env`),
3. the **default** built into the code.

An empty value counts as "not set". The loader is plain PHP; no package is installed. A mistake in a
setting stops the application with a message that names the setting (never its value).

| Setting | Default | Meaning |
|---------|---------|---------|
| `APP_NAME` | `Visitor Photo Slideshow` | Application name |
| `APP_ENV` | `production` | `development`, `testing` or `production` |
| `APP_DEBUG` | `false` | `true` shows error details on the page. Refused when `APP_ENV=production` |
| `APP_URL` | `http://localhost` | Public web address, no trailing slash (used only for URLs) |
| `DB_HOST` | `127.0.0.1` | Database server. Keep `127.0.0.1`; `localhost` can use a different connection method on Ubuntu |
| `DB_PORT` | `3306` | Database port |
| `DB_NAME` | `visitor_photo_slideshow` | Database name |
| `DB_USER` | *(required)* | Database user |
| `DB_PASSWORD` | *(empty)* | Database password |
| `UPLOAD_STORAGE_PATH` | `storage/uploads` in the project | Absolute path of the private photograph folder |
| `UPLOAD_MAX_FILE_SIZE_BYTES` | `5242880` (5 MB) | Largest photograph, in bytes |
| `UPLOAD_MAX_FILES` | `10` | Most photographs per upload |
| `UPLOAD_ALLOWED_MIME_TYPES` | `image/jpeg,image/png,image/webp` | Allowed image types, comma-separated; only these three can be named (Step 5) |

`SLIDESHOW_DEFAULT_DURATION_SECONDS` is read by Step 7 (default 5; allowed 2–3600). `VISITOR_CODE_PEPPER` remains reserved and is not read by the current visitor-code implementation.

Rules for the `.env` file:

* One `NAME=value` per line. Lines starting with `#` are comments; comments after a value are **not** supported.
* Put quotes around a value that starts or ends with a space or contains `#`, for example
  `DB_PASSWORD='my#secret'`. Quotes are removed; there are no escape sequences, so a Windows path is
  written exactly as `D:\private\uploads`.
* Windows editors that add a byte-order mark or use CRLF line endings are fine.
* **A real environment variable with the same name overrides `.env`.** If a setting does not behave as
  expected, run the check below: it shows where each setting came from.
* To keep `.env` outside the web folder, set the real environment variable `APP_ENV_FILE` to its absolute path.

Paths and URLs are separate: paths come from `app_path()` and `UPLOAD_STORAGE_PATH` (built with
`DIRECTORY_SEPARATOR`), web addresses only from `APP_URL` through `app_url()`.

### How PHP connects to MySQL

`config/database.php` provides one function, `db_connection()`, which returns a shared PDO connection.
The connection opens the first time it is needed and is reused for the rest of the request. It uses
exceptions for all errors, real (non-emulated) prepared statements, associative-array rows, no
persistent connection, a 5-second connect timeout and the `utf8mb4` character set. Right after
connecting it sets the session time zone to UTC and the collation to `utf8mb4_unicode_ci`.

If the connection fails, the technical reason goes to the server error log and the code throws an
exception whose message contains no password. On a web page a visitor sees only "Something went wrong.
Please try again later." (details appear only when `APP_DEBUG=true`).

Every future PHP file starts with the bootstrap, which loads the configuration and the connection code:

```php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
// in a file in the project root use __DIR__ instead of dirname(__DIR__)

$pdo = db_connection();
$statement = $pdo->prepare('SELECT id, name FROM visitors WHERE id = ?');
$statement->execute([1]);
```

### The database check

`tests/check_database.php` runs only from the command line (a web request gets an empty 403). It loads
the bootstrap, connects, runs `SELECT 1`, confirms the five tables exist, and reports where each
setting came from. It never changes data and never prints the database user or password. It exits
with status `0` when everything passes and `1` otherwise. A `[WARN]` about the upload folder is only information.

### Common errors (both platforms)

| Message from the check | Usual cause and fix |
|------------------------|---------------------|
| `DB_USER is not set` | No `.env` in the project folder (or it is named `.env.txt`), or `DB_USER` is empty. |
| `Line N of the configuration file is not in NAME=value form` | A line without `=`, or a lowercase name. Comments must start with `#`. |
| `The configuration file exists but cannot be read` | File permissions (Ubuntu: see below). |
| `APP_DEBUG must be false when APP_ENV is production` | Set `APP_ENV=development`, or `APP_DEBUG=false`. |
| `The database server could not be reached` | MySQL is not running, or `DB_HOST` / `DB_PORT` are wrong. |
| `rejected the user name or password...` | Wrong `DB_USER` or `DB_PASSWORD`; the user was not created for `127.0.0.1`; or `DB_NAME` is misspelled (MySQL gives this same answer for a database that does not exist when the user has no rights on it). A password containing `#` needs quotes in `.env`. |
| `The database named in DB_NAME does not exist` | Create the database (see *Initial database setup*). |
| `Missing table(s)` | Import `database/schema.sql` into the empty database. |
| `pdo_mysql is not installed or not enabled` | Enable the PHP extension (Windows: `php.ini`; Ubuntu: `sudo apt install php-mysql`). |
| A setting shows source `environment` but you expected `.env file` | A system environment variable with the same name is overriding `.env`; remove it. |

---

## Windows installation (XAMPP)

1. **Install XAMPP.** Download an XAMPP version that includes **PHP 8.2 or newer** from
   <https://www.apachefriends.org>. Install it (default folder `C:\xampp`).
2. **Start services.** Open the XAMPP Control Panel and start **Apache** and **MySQL** (this is MariaDB).
3. **Copy the project.** Copy the `visitor-photo-slideshow` folder into XAMPP's web folder, normally
   `C:\xampp\htdocs\`. The result is `C:\xampp\htdocs\visitor-photo-slideshow\`. Keep all file names
   exactly as they are (lower case).
4. **Configure MySQL.**
   1. Open <http://localhost/phpmyadmin>.
   2. XAMPP's `root` account has **no password** by default. Set a root password (phpMyAdmin →
      *User accounts* → `root` → *Edit privileges* → *Change password*). If you change it, also update
      phpMyAdmin's `config.inc.php` password so phpMyAdmin keeps working.
   3. Open the **SQL** tab, paste the database and user statements from the section above, and click **Go**.
5. **Import `schema.sql` with phpMyAdmin.**
   1. In the left list click **visitor_photo_slideshow**.
   2. Click the **Import** tab.
   3. Under *File to import* click **Choose File** and select
      `C:\xampp\htdocs\visitor-photo-slideshow\database\schema.sql`.
   4. Keep format **SQL** and character set **utf-8**, then click **Import** at the bottom.
   5. Confirm the green success message and five tables in the left list.
6. **Configure credentials.** In the project folder copy `.env.example` to `.env`
   (make sure the name is exactly `.env`, not `.env.txt`; enable *File name extensions* in Explorer's
   *View* menu). Edit `.env` with a text editor and set `DB_NAME`, `DB_USER`, `DB_PASSWORD`,
   `APP_URL` (for example `http://localhost/visitor-photo-slideshow`) and a long random
   `VISITOR_CODE_PEPPER`. Never share or commit `.env`.
   `config/config.php` reads this file (see *Configuration and database connection* below).
7. **Set PHP upload limits.** In the XAMPP Control Panel click **Config** next to Apache →
   **PHP (php.ini)**, set the values from *Required PHP upload settings*, save, then **Stop** and
   **Start** Apache.
8. **Set upload-folder permissions.** Only `storage\uploads` needs to be writable by Apache.
   1. Find out which account Apache runs as:
      * Started from the XAMPP Control Panel: it runs as **your own Windows user**, which normally can
        already write to the folder. Nothing more is needed.
      * Installed as a Windows service: press `Win + R`, run `services.msc`, open **Apache2.4** →
        **Properties** → **Log On** and note the account (often *Local System*).
   2. If the account needs access, give it **Modify** permission on that one folder only. In a
      Command Prompt run (replace `<account>`, for example `DESKTOP-ABC\yourname` or
      `NT AUTHORITY\LocalService`):

      ```bat
      icacls "C:\xampp\htdocs\visitor-photo-slideshow\storage\uploads" /grant "<account>:(OI)(CI)M"
      ```

      Or use Explorer: right-click the folder → *Properties* → *Security* → *Edit* → *Add* → enter the
      account → tick **Modify** → *OK*.
   3. Do **not** grant *Everyone* full control, and do not open up the whole project folder.
9. **Open the application.** Browse to <http://localhost/visitor-photo-slideshow/>.
   There is no start page (`index.php`) yet, so the bare folder address shows a folder listing or an error;
   that is expected. The one page that exists is the registration page, `register.php` (Step 3, see
   *Visitor registration* below).

### Windows: configure and test the database connection (Step 2)

1. **Start the servers.** In the XAMPP Control Panel start **Apache** and **MySQL**.
2. **Configure the credentials.** In the project folder (`C:\xampp\htdocs\visitor-photo-slideshow`), copy
   `.env.example` to `.env` and open it in a text editor. Set `DB_NAME`, `DB_USER` and `DB_PASSWORD` to
   the values you used in *Initial database setup*. Keep `DB_HOST=127.0.0.1` and `DB_PORT=3306`
   (change the port only if your MySQL uses another one).
3. **Configure the application URL.** Set `APP_URL=http://localhost/visitor-photo-slideshow` (no trailing slash).
   If you want photographs outside the web folder, create that folder and set `UPLOAD_STORAGE_PATH`
   to it, for example `UPLOAD_STORAGE_PATH=D:\private\uploads` (Apache needs *Modify* rights, as in step 8 above).
4. **Check that PHP can talk to MySQL.** In XAMPP, `extension=pdo_mysql` is normally already enabled in
   `C:\xampp\php\php.ini`. After editing `php.ini`, restart Apache. Changes to `.env` need no restart.
5. **Run the database check** from a Command Prompt:

   ```bat
   cd C:\xampp\htdocs\visitor-photo-slideshow
   C:\xampp\php\php.exe tests\check_database.php
   ```

   Every line should start with `[PASS]` and the last line should be `RESULT: PASS`.
6. **Make sure `.env` is not public.** Browse to <http://localhost/visitor-photo-slideshow/.env>. You must see
   **403 Forbidden** (or 404), never the file's text. If the text appears, stop and fix the `.htaccess` files
   before continuing.

Windows-specific problems:

| Problem | Fix |
|---------|-----|
| `'php' is not recognized` | Use the full path `C:\xampp\php\php.exe`. |
| MySQL will not start in XAMPP | Another program uses port 3306. Stop it, or change the port in XAMPP's MySQL configuration and set the same `DB_PORT` in `.env`. |
| `.env` is ignored | It may really be called `.env.txt`. Enable *File name extensions* in Explorer and rename it. |
| Access denied although the password is right | Create the user for both `localhost` and `127.0.0.1` (*Initial database setup*). |

*This procedure has not been tested on Windows or XAMPP; see `AI_LOG.md`.*

---

## Ubuntu installation

These commands assume **Ubuntu 24.04 LTS** (it ships PHP 8.3, which satisfies "8.2+"). Ubuntu 22.04
ships PHP 8.1, so it needs a PHP 8.2+ source first (for example the Ondřej Surý PPA).

1. **Install Apache, PHP and MySQL.**

   ```bash
   sudo apt update
   sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring
   ```

2. **Check the PHP extensions** (`pdo_mysql`, `fileinfo` and `mbstring` must be listed; `fileinfo` comes with the base PHP package):

   ```bash
   php -v
   php -m | grep -Ei 'pdo_mysql|fileinfo|mbstring'
   ```

3. **Place the application** in its own directory (this guide uses `/var/www/visitor-photo-slideshow`):

   ```bash
   sudo mkdir -p /var/www/visitor-photo-slideshow
   sudo cp -a /path/to/visitor-photo-slideshow/. /var/www/visitor-photo-slideshow/
   ```

4. **Configure MySQL and import `schema.sql`.**

   ```bash
   sudo systemctl enable --now mysql
   sudo mysql
   ```

   In the MySQL prompt paste the database and user statements from *Initial database setup*, then `exit`.
   Import the schema into the existing database:

   ```bash
   mysql -u vps_user -p visitor_photo_slideshow < /var/www/visitor-photo-slideshow/database/schema.sql
   mysql -u vps_user -p visitor_photo_slideshow -e "SHOW TABLES;"
   ```

   The second command should list five tables. (phpMyAdmin also works if you install it.)

5. **Configure credentials.**

   ```bash
   cd /var/www/visitor-photo-slideshow
   cp .env.example .env
   nano .env
   ```

   Set `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `APP_URL` (for example `http://localhost`) and a long
   random `VISITOR_CODE_PEPPER`. `config/config.php` reads this file (see *Configuration and database
connection* below).

6. **Set PHP upload limits.** Create a small override file (replace `<version>` with your PHP
   version from `php -v`, for example `8.3`):

   ```bash
   sudo nano /etc/php/<version>/apache2/conf.d/99-visitor-photo-slideshow.ini
   ```

   containing the values from *Required PHP upload settings*.

7. **Configure the Apache virtual host.** Create
   `/etc/apache2/sites-available/visitor-photo-slideshow.conf`:

   ```apache
   <VirtualHost *:80>
       ServerName localhost
       DocumentRoot /var/www/visitor-photo-slideshow

       <Directory /var/www/visitor-photo-slideshow>
           Options -Indexes
           AllowOverride None
           Require all granted
       </Directory>

       # Private folders: never served over the web
       <Directory /var/www/visitor-photo-slideshow/config>
           Require all denied
       </Directory>
       <Directory /var/www/visitor-photo-slideshow/includes>
           Require all denied
       </Directory>
       <Directory /var/www/visitor-photo-slideshow/database>
           Require all denied
       </Directory>
       <Directory /var/www/visitor-photo-slideshow/storage>
           Require all denied
       </Directory>
       <Directory /var/www/visitor-photo-slideshow/tests>
           Require all denied
       </Directory>

       # Hidden files such as .env
       <FilesMatch "^\.">
           Require all denied
       </FilesMatch>

       ErrorLog ${APACHE_LOG_DIR}/visitor-photo-slideshow-error.log
       CustomLog ${APACHE_LOG_DIR}/visitor-photo-slideshow-access.log combined
   </VirtualHost>
   ```

   Use your own domain in `ServerName` if you have one. Because `AllowOverride None` is set, these
   rules do not depend on the `.htaccess` files (which remain as a safety net).

8. **Enable the site and required Apache modules.**

   ```bash
   sudo a2ensite visitor-photo-slideshow
   sudo a2dissite 000-default
   apache2ctl -M | grep -i php
   ```

   `libapache2-mod-php` enables the PHP module automatically; the last command should print a `php_module` line.
   (`a2dissite 000-default` stops Apache's sample site from answering on the same name; skip it if
   you still need that site.) No other module is required for Step 1. `mod_rewrite` and `mod_headers`
   are not needed yet; enable them later only if a step asks (`sudo a2enmod rewrite headers`).

9. **Set upload-storage permissions.** The Apache account on Ubuntu is **`www-data`**. Give the
   project to your user with group `www-data`, let Apache **read** everything, and let it **write only**
   to `storage/uploads`. Never use `chmod 777`.

   ```bash
   cd /var/www/visitor-photo-slideshow
   sudo chown -R "$USER":www-data .
   sudo find . -type d -exec chmod 750 {} \;
   sudo find . -type f -exec chmod 640 {} \;
   sudo find storage/uploads -type d -exec chmod 2770 {} \;
   sudo find storage/uploads -type f -exec chmod 660 {} \;
   ```

   The `2` in `2770` (setgid) makes new files and folders inherit group `www-data`. The parent
   `/var/www` must allow traversal (it does by default). If you set `UPLOAD_STORAGE_PATH` to a folder
   outside the project, apply the same `www-data` group rules to that folder.

10. **Apply the changes and open the application.**

    ```bash
    sudo apache2ctl configtest
    sudo systemctl restart apache2
    ```

    Browse to <http://localhost/> (or your `ServerName`). There is no start page (`index.php`) yet, so
    **403 Forbidden** (directory listing is off) is expected. The one page that exists is
    <http://localhost/register.php> (Step 3, see *Visitor registration* below).

### Ubuntu: configure and test the database connection (Step 2)

1. **Apache and PHP.** The virtual host and PHP packages from the steps above are all that is needed.
   `php-mysql` provides `pdo_mysql` for both Apache and the command line.
2. **Configure the credentials.** In `/var/www/visitor-photo-slideshow`:

   ```bash
   cp .env.example .env
   nano .env
   ```

   Set `DB_NAME`, `DB_USER` and `DB_PASSWORD` to the values you used in *Initial database setup*. Keep
   `DB_HOST=127.0.0.1`.
3. **Configure the application URL.** With the virtual host above, use `APP_URL=http://localhost` (or your
   `ServerName`). Optionally set `UPLOAD_STORAGE_PATH=/srv/private/uploads` for a folder outside the web
   folder; give it the same `www-data` group rules as `storage/uploads`.
4. **Set file permissions.** Apache (`www-data`) must be able to **read** `.env`, and nobody else should:

   ```bash
   cd /var/www/visitor-photo-slideshow
   sudo chown "$USER":www-data .env
   sudo chmod 640 .env
   ```

   (The commands in step 9 above do the same for every file, so repeat them if you create `.env` later.)
5. **Run the database check**, first as yourself and then as the web-server account:

   ```bash
   php tests/check_database.php
   sudo -u www-data php tests/check_database.php
   ```

   Every line should start with `[PASS]` and the last line should be `RESULT: PASS`. If the first works and
   the second reports that the configuration file cannot be read, fix the permissions in step 4.
6. **Make sure `.env` is not public:** `curl -I http://localhost/.env` must answer **403**.
7. **Alternative to `.env`.** You may put settings in the virtual host instead, for example
   `SetEnv DB_USER vps_user` and `SetEnv DB_PASSWORD "..."`, then `sudo systemctl restart apache2`.
   The command-line check does not see these, because they exist only inside Apache.

Ubuntu-specific problems:

| Problem | Fix |
|---------|-----|
| `The database server could not be reached` | `sudo systemctl status mysql`; start it with `sudo systemctl start mysql`. |
| Access denied for `root` | Ubuntu's MySQL `root` uses socket login. Use the dedicated `vps_user` from *Initial database setup*. |
| Works in the terminal, fails in the browser | Compare `php tests/check_database.php` with `sudo -u www-data ...`; usually a `.env` permission problem. |
| Changed `php.ini` or a `SetEnv` line, no effect | `sudo systemctl restart apache2`. |

*Step 2 was tested on Ubuntu 24.04 with PHP 8.3, MySQL 8.0 and Apache 2.4 in the AI's sandbox, not on your machine; see `AI_LOG.md`.*

---

## Visitor registration (Step 3)

A first-time visitor opens `register.php`, types a name and presses **Register**. The system creates a
unique **visitor code**, stores only a one-way hash of it, and shows the code once so the visitor can save it
for future visits. (Recognising a returning visitor by that code is a later step and is **not built yet**.)

| Platform | Address of the page |
|----------|---------------------|
| Windows (XAMPP) | <http://localhost/visitor-photo-slideshow/register.php> |
| Ubuntu (virtual host from this README) | <http://localhost/register.php> |

### Files added in Step 3

| File | Purpose |
|------|---------|
| `register.php` | The page: form, result, request handling. All HTML is written by PHP; there is no CSS and no JavaScript. |
| `includes/security.php` | Secure session start, CSRF token, security headers, `html_escape()` (a wrapper around `htmlspecialchars()`). |
| `includes/visitors.php` | Name validation, visitor-code generation, hashing, and the database insert with collision retry. |

`config/`, `includes/bootstrap.php`, `tests/check_database.php` and `database/schema.sql` are **unchanged**. The
`visitors` table already had everything needed (`id`, `name`, `visitor_code_hash` with a `UNIQUE` key, `created_at`),
so there is no schema change and nothing to re-import.

### How registration works

1. `GET register.php` shows the form. It contains a hidden **CSRF token** stored in the visitor's PHP session.
2. On submit (`POST`) the server checks the CSRF token first. A missing or wrong token is rejected with HTTP 403 and nothing is read or stored.
3. The name is validated **on the server** (see the rules below). Problems are listed above the form and the typed name stays in the field (HTTP 422).
4. The code is made with PHP `random_bytes()`: 20 random bytes (160 bits), written as 32 letters and digits (`A-Z`, `2-7`) in 8 groups of 4, for example `K7QD-2MXA-...` (39 characters with the hyphens).
5. The **SHA-256 hash** of the code (64 hex characters; hyphens and letter case are ignored) is inserted into `visitors.visitor_code_hash` with a **prepared statement**. The code itself is never written to the database.
6. If the database reports that the hash already exists (the column is `UNIQUE`), nothing is stored, a **new code** is made and the insert is retried, up to 5 times. The database does the check, so it is also safe when two visitors register at the same moment.
7. The code and name are kept in the visitor's **session**, the CSRF token is used up, and the browser is redirected (HTTP 303) to `register.php?registered=1` (**Post-Redirect-Get**).
8. The result page shows the success message and the code from the session. Refreshing it only repeats a `GET`: it shows the **same** code and creates **no** new record.
9. The result stays available for **15 minutes**, then it is removed. The button **"I have saved my code - register another visitor"** removes it at once and returns to the form.

The database holds only the hash, so the code cannot be shown again later. During those 15 minutes the code
also exists in the server-side session file (PHP's session storage, not the database); that is how the result page can survive a refresh.

**Name rules** (`visitor_name_validate()`): text, valid UTF-8; extra spaces are removed; 1 to 100 characters;
must start with a letter; only letters (any alphabet, for example `रवि कुमार` or `José`), spaces, hyphens, apostrophes and full stops; at least 2 letters.
Digits, symbols and HTML are refused. Two visitors may have the same name.

**Security measures:** session cookie that is `HttpOnly`, `SameSite=Lax` and `Secure` when the site is served over HTTPS (or `APP_URL` starts with `https://`), strict session mode, no session id in URLs;
CSRF token compared with `hash_equals()` and valid for one successful action; every value printed in a page goes through `htmlspecialchars()`; prepared statements only;
`Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and `Cache-Control: no-store` headers; database and configuration errors are written to the server log and the visitor sees only
"Registration could not be completed because of a technical problem. Please try again later." The hash, the database credentials and PHP error text are never shown.

### Run and test on Windows (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. Make sure Steps 1 and 2 are done (database imported, `.env` filled in) and that the database check still passes:

   ```bat
   cd C:\xampp\htdocs\visitor-photo-slideshow
   C:\xampp\php\php.exe tests\check_database.php
   ```

2. Copy the new files into the project: `register.php` into the project folder, and `security.php` and `visitors.php` into its `includes` folder. Replace `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md` and `AI_LOG.md` with the updated ones.
3. Open <http://localhost/visitor-photo-slideshow/register.php>.
4. Run the tests in the table below.

### Run and test on Ubuntu

1. Copy the same files into `/var/www/visitor-photo-slideshow` (`register.php` in the folder, the other two in `includes/`) and repeat the permission commands from *Ubuntu installation*, step 9 (new files must be readable by `www-data`).
2. Run the database check: `php tests/check_database.php`.
3. Open <http://localhost/register.php> and run the tests below. The curl commands in tests 7 work in a terminal as they are.

### Tests to run

The expected result is the same on both platforms. The browser pages have no `required` attribute on purpose, so you can see the **server's** validation.

| # | Test | Expected result |
|---|------|-----------------|
| 1 | Type a valid name such as `Asha Patel` and press **Register**. | The address changes to `register.php?registered=1` and a page says **Registration successful**. |
| 2 | In phpMyAdmin open `visitor_photo_slideshow` > `visitors` > **Browse** (see below). | One new row with your name, a 64-character `visitor_code_hash` and a `created_at` time. |
| 3 | Check that the table holds the hash and not the code (the SQL below). | No column shows your code. The `SHA2` query returns your row; a query with a wrong code returns nothing. |
| 4 | Look at the result page. | The code (8 groups of 4, for example `K7QD-2MXA-...`) and the sentence telling you to save it for future visits. No hash is shown. |
| 5 | Press **F5** several times on the result page. Then press the browser's **Back** button and submit the old form again. | The same code every time, and the row count in phpMyAdmin stays the same. (After Back and submit you are taken back to the same result.) |
| 6 | Register with an empty name, then with `123`, then with `<b>x</b>`. | The form appears again with a message such as "Please enter your name." No new row. |
| 7 | Send a wrong CSRF token (below). | HTTP **403**, the message "The form could not be verified", and no new row. |
| 8 | Run the Step 2 check (`tests\check_database.php`). | Every line `[PASS]` and `RESULT: PASS`. |

**Test 7, option A (any system with curl; Windows 10 and 11 include `curl.exe`).** The command sends a form without a valid token:

```
curl -i -X POST -d "csrf_token=wrong&action=register&name=Test+User" http://localhost/visitor-photo-slideshow/register.php
```

(On Ubuntu use `http://localhost/register.php`; on Windows type `curl.exe`.) The first line of the answer must be `HTTP/1.1 403 Forbidden`.

**Test 7, option B (browser only).** Open the form, open the browser's developer tools (F12), find the cookie `vps_session` (*Application* or *Storage* tab > *Cookies*) and delete it. Without reloading the page, type a name and press **Register**. You must see "The form could not be verified" and no new row appears.

### Verify the visitor record in phpMyAdmin

1. Open <http://localhost/phpmyadmin>, click **visitor_photo_slideshow** in the left list, then the **visitors** table, then **Browse**.
2. The newest row shows `id`, `name`, `visitor_code_hash` (64 characters of `0-9a-f`) and `created_at` (UTC).
3. Open the **SQL** tab and run this to see the length of the hash and the newest rows:

   ```sql
   SELECT id, name, visitor_code_hash, CHAR_LENGTH(visitor_code_hash) AS hash_length, created_at
   FROM visitors ORDER BY id DESC LIMIT 5;
   ```

4. To prove that the stored value is the hash of the code you were shown, paste your code between the quotes. It returns your row only if the code is correct (it can be typed with or without hyphens):

   ```sql
   SELECT id, name FROM visitors
   WHERE visitor_code_hash = SHA2(REPLACE('PASTE-YOUR-CODE-HERE', '-', ''), 256);
   ```

5. To remove your test rows: tick them in **Browse** and press **Delete**. (Do this only for test data. Once consents and photographs exist, the foreign keys will refuse to delete a visitor who still has them.)

### Common problems (Step 3)

| Problem | Usual cause and fix |
|---------|---------------------|
| Every submit shows "The form could not be verified" | The browser does not keep the session cookie (cookies blocked), or the PHP session folder is not writable. Windows: check `session.save_path` in `php.ini` (XAMPP normally uses `C:\xampp\tmp`). Ubuntu: `/var/lib/php/sessions` must be writable by `www-data`. |
| White page or "Something went wrong. Please try again later." | Read the error log (XAMPP: `C:\xampp\apache\logs\error.log`; Ubuntu: `/var/log/apache2/error.log`). Typical causes: `.env` missing or wrong, or the PHP extension `mbstring` or `pdo_mysql` is not enabled. |
| "Registration could not be completed because of a technical problem" | The page works but the database refused the insert. Run `php tests/check_database.php`; check the error log. |
| The code is gone after a while | By design: the result is removed from the session after 15 minutes. |
| Page shows the form and "The result of the registration is no longer available" | The 15 minutes passed, or the session was lost. If the code was not saved, register again (the old row stays in the table; it cannot be recovered). |

### Not built in Step 3

Returning-visitor identification, consent, photo upload, gallery, slideshow and the administrator pages. `VISITOR_CODE_PEPPER` in `.env.example` is **not used yet**:
Step 3 stores a plain SHA-256 hash, as the Step 3 brief asks (see `ASSUMPTIONS.md`, G1).

*Step 3 was tested in the AI's sandbox on Ubuntu 24.04 with PHP 8.3.6, Apache 2.4.58 (mod_php), the PHP built-in web server, and both MariaDB 10.11.14 and MySQL 8.0.46. It has not been tested on Windows, XAMPP, phpMyAdmin or in a real web browser; see `AI_LOG.md`.*

---

## Returning visitor identification (Step 4)

A visitor who registered earlier opens `identify.php`, types the visitor code received at registration and
presses **Identify me**. If the code is right, the system finds the **existing** visitor row, remembers who the visitor
is in a secure PHP session and shows a welcome message. **No new visitor and no new code is ever created here.**

| Platform | Address of the page |
|----------|---------------------|
| Windows (XAMPP) | <http://localhost/visitor-photo-slideshow/identify.php> |
| Ubuntu (virtual host from this README) | <http://localhost/identify.php> |

### Files added in Step 4

| File | Purpose |
|------|---------|
| `identify.php` | The page: form, request handling, welcome page, "End my session" button. All HTML is written by PHP; there is no CSS and no JavaScript. |
| `includes/visitor_auth.php` | Reusable helper: `visitor_find_by_code()`, `visitor_login()`, `visitor_current()`, `visitor_is_authenticated()`, `visitor_require_login()`, `visitor_logout()` and the wrong-code throttle. |
| `tests/check_identify.php` | Command-line check of the lookup and throttle logic. It runs inside a database transaction that is rolled back, so it leaves no visitor behind. |

`register.php`, `includes/bootstrap.php`, `includes/security.php`, `includes/visitors.php`, `config/`, `database/schema.sql` and
`tests/check_database.php` are **unchanged**. The `visitors` table already has everything needed, so there is **no schema change** and
nothing to re-import. (Your project folder may also contain copies of `security.php` and `visitors.php` in the project root; the pages
use only the ones in `includes/`.)

### How identification works

1. `GET identify.php` shows the form with a hidden **CSRF token** stored in the visitor's PHP session.
2. On submit (`POST`) the server checks the CSRF token first. A missing or wrong token gives HTTP 403 and nothing is looked up or counted.
3. If this session or this client address already made too many wrong attempts (see *Wrong-code protection*), the visitor is told to wait and the code is **not even looked up**.
4. The submitted code is checked for shape (after removing hyphens and spaces and making capitals: exactly 32 characters from `A-Z` and `2-7`, the format of Step 3).
5. PHP computes the **SHA-256 hash** with `visitor_code_hash()` from Step 3 (the same function that stored it, so letter case, hyphens and spaces do not matter) and a **prepared statement** looks for that hash in `visitors.visitor_code_hash`: `SELECT id, name FROM visitors WHERE visitor_code_hash = ? LIMIT 1`. Only the hash is sent to the database. The database never holds, and is never compared with, the code itself.
6. **No match** (unknown code, wrong shape, missing field): the wrong attempt is counted, and the browser is redirected to `identify.php`, which shows one neutral sentence: "That visitor code was not recognised." It never says whether a name or account exists.
7. **Match:** the session id is **replaced** (`session_regenerate_id(true)`, against session fixation), the visitor's database `id`, `name` and the time are stored in the session, the CSRF token is replaced, and the browser is redirected (HTTP 303) to `identify.php?identified=1` (**Post-Redirect-Get**).
8. The welcome page shows the name and the visitor number from the session. **Refreshing it only repeats a `GET`**: it creates no record and asks for nothing.
9. **End my session** (a `POST` with a CSRF token) empties the whole session and replaces the session id.

The session holds only `id`, `name` and the time of identification. It never holds the visitor code or the hash. An identification
lasts 2 hours (`VISITOR_SESSION_LIFETIME_SECONDS` in `includes/visitor_auth.php`), then the code must be entered again.

### Protecting future visitor pages

Every later page that is only for identified visitors starts like this (before any output):

```php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';

send_security_headers();
session_start_secure();
$visitor = visitor_require_login();   // an unidentified visitor is sent to identify.php; the script stops
// $visitor['id'] and $visitor['name'] are now safe to use
```

### Wrong-code protection (no extra database table)

Two counters, each counting wrong codes inside a window of 15 minutes. Reaching either one locks identification until the window ends.

| Counter | Where it lives | Limit |
|---------|----------------|-------|
| Per browser session | in the PHP session | 5 wrong codes |
| Per client address | a small file in the system temp folder (`sys_get_temp_dir()`, in a folder named `vps_identify_throttle`; the file name is a hash of the address) | 30 wrong codes |

The address counter still works when someone deletes the session cookie between attempts. Its limit is higher because several people
(for example a computer lab) can share one address. Only `REMOTE_ADDR` is used, not headers such as `X-Forwarded-For`, which a visitor can forge.
If the temp folder cannot be used, the problem is logged and only the session counter applies. A correct identification does not reset the address counter.

### Run and test on Windows (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. Make sure Steps 1 to 3 work (database imported, `.env` filled in, `register.php` registers a visitor).
2. Copy the new files into the project: `identify.php` into the project folder, `visitor_auth.php` into its `includes` folder, `check_identify.php` into its `tests` folder. Replace `README.md` and `AI_LOG.md`.
3. Run the command-line logic check (it changes nothing):

   ```bat
   cd C:\xampp\htdocs\visitor-photo-slideshow
   C:\xampp\php\php.exe tests\check_identify.php
   ```

   Every line should start with `[PASS]` and the last line should be `RESULT: PASS`.
4. Run the browser tests below. **Write your own result in the last column.**

On Ubuntu the same files go into `/var/www/visitor-photo-slideshow` (repeat the permission commands of *Ubuntu installation*, step 9),
and the check is `php tests/check_identify.php`.

### Tests to run

| # | Test | Expected result | Your result |
|---|------|-----------------|-------------|
| 1 | Open `register.php`, register `Asha Patel`. | **Registration successful** page with a visitor code. | |
| 2 | **Copy the visitor code** (for example into Notepad). | You have the code. It exists nowhere else. | |
| 3 | End the visitor session: open a **new private/incognito window**, or delete the cookie `vps_session` (F12 > Application/Storage > Cookies), or close the browser. | The new window does not know the earlier registration. | |
| 4 | Open `identify.php` in that window. | The form "Returning visitor" with a code field and an **Identify me** button. | |
| 5 | Type the code and press **Identify me**. | The address becomes `identify.php?identified=1` and the page says **Welcome back, Asha Patel!** with a visitor number. | |
| 6 | Compare the visitor number and name with phpMyAdmin (below). | Same `id` and `name` as the row you registered. | |
| 7 | Type a wrong code (for example `AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA`), then `abc`, then an empty field. | Each time: "That visitor code was not recognised." No welcome page. | |
| 8 | On the welcome page press **F5** several times. | The same welcome page every time; the number of rows in `visitors` does not change. | |
| 9 | Type your code in lower case and without hyphens (new private window). | Welcome page for the same visitor. | |
| 10 | Check the table in phpMyAdmin (below). | `visitor_code_hash` is 64 characters; no column contains the code. | |
| 11 | Press **End my session** on the welcome page, then open `identify.php?identified=1`. | "Your session has ended", then the form: you are no longer identified. | |
| 12 | Send a wrong CSRF token (below). | HTTP **403**, nothing looked up. | |
| 13 | Enter 5 wrong codes in a row in one window. | The 6th attempt shows "Too many unsuccessful attempts. Please wait ..." even with the right code. After 15 minutes, or in a **new private window**, a right code works again. (If you did the 30-attempt test too, delete the folder `vps_identify_throttle` in the PHP temp folder to unlock the address.) | |
| 14 | Run Steps 1 to 3 again: `tests\check_database.php`, and register another visitor. | `RESULT: PASS`; registration still works as before. | |

**Test 12 (any system with curl; on Windows type `curl.exe`):**

```
curl -i -X POST -d "csrf_token=wrong&action=identify&visitor_code=TEST" http://localhost/visitor-photo-slideshow/identify.php
```

(On Ubuntu use `http://localhost/identify.php`.) The first line of the answer must be `HTTP/1.1 403 Forbidden`.

### Verify the visitor in phpMyAdmin

1. Open <http://localhost/phpmyadmin>, click **visitor_photo_slideshow**, then **visitors** > **Browse**. Note the `id` and `name` of your visitor, and the number of rows.
2. In the **SQL** tab run this to see the newest rows and the hash length. The code itself appears in no column:

   ```sql
   SELECT id, name, visitor_code_hash, CHAR_LENGTH(visitor_code_hash) AS hash_length, created_at
   FROM visitors ORDER BY id DESC LIMIT 5;
   ```

3. To prove that the row found by the page is the one that owns your code (paste the code between the quotes; it works in any case, with or without hyphens):

   ```sql
   SELECT id, name FROM visitors
   WHERE visitor_code_hash = SHA2(REPLACE(UPPER(TRIM('PASTE-YOUR-CODE-HERE')), '-', ''), 256);
   ```

   It must return the same `id` and `name` as the welcome page.
4. After tests 5 to 11 run `SELECT COUNT(*) FROM visitors;` again: the number is the same as before you identified (identification never inserts).

### Common problems (Step 4)

| Problem | Usual cause and fix |
|---------|---------------------|
| Every submit shows "The form could not be verified" | Cookies are blocked, or the PHP session folder is not writable (same cause as for registration). |
| A right code is always "not recognised" | The code was typed wrongly (look-alike characters: the alphabet has no `0`, `1`, `8`, `9`); or the visitor was registered on another database. Use the phpMyAdmin query above with the same code. |
| "Too many unsuccessful attempts" although you typed few codes | The client-address counter is shared by everyone behind the same address, or an earlier test used it up. Wait 15 minutes or delete the `vps_identify_throttle` folder in the PHP temp folder. |
| "Identification could not be completed because of a technical problem" | The database refused the query. Run `tests/check_database.php` and read the error log. |

### Not built in Step 4

Consent, photo upload, gallery, slideshow, administrator pages, and an automatic page for identified visitors (no protected visitor page exists yet;
`visitor_require_login()` is ready for the next steps). `VISITOR_CODE_PEPPER` in `.env.example` is still **not used** (see `ASSUMPTIONS.md`, G1).
A visitor who lost the code cannot recover it. The identified state is kept for 2 hours and is not re-checked against the database on every request, so a visitor row deleted
by hand stays "identified" until the session ends.

*Step 4 has not been executed by anyone: it was written without access to PHP, a web server or a database, so no result in this section has been observed. See `AI_LOG.md`.*

---

## Photo upload (Step 5)

An **identified** visitor (Step 4) opens `upload.php`, chooses one or more photographs, ticks the **consent box** and presses **Upload photographs**.
The photographs are checked, stored in a **private folder** under random names, and recorded in the database for that visitor together with a
new consent record. A visitor who is not identified is sent to `identify.php` and nothing is processed.

| Platform | Address of the page |
|----------|---------------------|
| Windows (XAMPP) | <http://localhost/visitor-photo-slideshow/upload.php> |
| Ubuntu (virtual host from this README) | <http://localhost/upload.php> |

### Files added or changed in Step 5

| File | Purpose |
|------|---------|
| `upload.php` (new) | The page: login check, form, request handling, result. All HTML is written by PHP; no CSS, no JavaScript. |
| `includes/uploads.php` (new) | All upload logic: validation, random names, private storage, consent and photo records, clean-up after a failure. No HTML. |
| `tests/check_upload.php` (new) | Command-line check of the upload logic. Changes nothing (transaction rolled back, temporary folder deleted). |
| `config/config.php` (changed) | Reads the new setting `UPLOAD_ALLOWED_MIME_TYPES` (before Step 5 it was listed but not read). Nothing else changed. |
| `.env.example`, `README.md`, `ASSUMPTIONS.md` (H), `ARCHITECTURE.md` (sections 7 and 8), `AI_LOG.md` | Documentation. |

`database/schema.sql` is **unchanged**: `photos` and `consents` already have every column and key the upload needs, so there is nothing to re-import.
`register.php`, `identify.php`, `includes/security.php`, `includes/visitors.php` and `includes/visitor_auth.php` are unchanged.

### Limits and formats (documented assumptions, all in `.env`)

| Setting | Value | Note |
|---------|-------|------|
| Formats | JPEG/JPG, PNG, WebP | `UPLOAD_ALLOWED_MIME_TYPES` can only narrow this list |
| Size | 5 MB per photograph (5,242,880 bytes) | `UPLOAD_MAX_FILE_SIZE_BYTES` |
| Count | 10 per submission | `UPLOAD_MAX_FILES` |

PHP has its own limits that apply **before** the application runs (`upload_max_filesize`, `post_max_size`, `max_file_uploads`; see *Required PHP upload settings*).
If they are lower than the settings above, uploads fail with messages such as "larger than the server accepts". Restart Apache after changing `php.ini`.

### How an upload works

1. **Authentication.** `visitor_require_login()` (Step 4). The owner of every photograph is the visitor id in the **server-side session**; the form has no visitor field and nothing sent by the browser can change the owner.
2. A request larger than `post_max_size` is reported as too large (HTTP 413).
3. The **CSRF token** is checked (invalid: HTTP 403, no file is read).
4. **Consent:** the box must be ticked in this request, every time (not ticked: HTTP 422, nothing is stored). The text says the photographs may be shown publicly on the slideshow screen.
5. **More than 10 files:** the whole submission is rejected. Otherwise **each file** is checked: PHP upload error, really uploaded by HTTP, not empty, at most 5 MB, real content type by **PHP Fileinfo** (not the browser's type), file name ending that fits the type (`.jpg`/`.jpeg` for JPEG, `.png`, `.webp`), and `getimagesize()` confirms a real image. A file that fails is rejected and listed; the others continue.
6. Accepted files are moved into private storage under a **random name**: 48 random hexadecimal characters plus `.jpg`, `.png` or `.webp` chosen from the detected type, in a sub-folder named after the first two characters (for example `3f/3f9c...e1.jpg`).
7. In **one database transaction** a new row is inserted into `consents` (visitor id, consent version `1.0`, time) and then one row per file into `photos`: visitor id (from the session), consent id, random file name, **original file name (metadata only)**, detected MIME type, size, status `active`, upload time.
8. If anything fails technically (storage or database), the transaction is rolled back **and the files stored for this submission are deleted**; the visitor sees "could not be saved because of a technical problem" and nothing else. Details go to the server error log.
9. On success the page redirects (Post-Redirect-Get) and shows **how many photographs were uploaded**. Refreshing it uploads nothing again.

**Consent.** One upload session is one form submission. Each successful submission creates its own `consents` row (even for the same visitor and the same consent version),
and its photographs refer to it through `photos.consent_id`. The database makes a photograph without a consent of the same visitor impossible (composite foreign key).
A photograph is not public merely because it was uploaded: nothing in the project shows photographs yet, and the later slideshow step must show only photographs that have a consent record.

### Private photograph storage

By default photographs go to `storage/uploads` **inside the project folder**. That folder is protected only by web-server rules (`storage/.htaccess` on XAMPP, the virtual host on Ubuntu),
which is **not a guarantee on every server** (for example when `AllowOverride None` is set and the virtual host rules are missing). **Use a folder outside the web root** by setting
`UPLOAD_STORAGE_PATH` in `.env` to an absolute path. The code then writes there, and no URL can reach the folder. (With the default folder the application writes a warning to the server error log.)
The folder is created if it is missing (mode 0750) and must be writable by the web server account. Never use `chmod 777` or give *Everyone* full control.

**Windows (XAMPP)**

1. Create a folder **outside** `C:\xampp\htdocs`, for example `C:\vps-private\uploads`.
2. In `.env` set `UPLOAD_STORAGE_PATH=C:\vps-private\uploads` (write the path exactly like this: no quotes needed, no escape characters).
3. If Apache was started from the XAMPP Control Panel it runs as **your own Windows user**, which can normally already write there. If Apache runs as a Windows service, give that account **Modify** on that one folder (replace `<account>`):

   ```bat
   icacls "C:\vps-private\uploads" /grant "<account>:(OI)(CI)M"
   ```

**Ubuntu**

```bash
sudo mkdir -p /srv/vps-private/uploads
sudo chown "$USER":www-data /srv/vps-private/uploads
sudo chmod 2770 /srv/vps-private/uploads
```

Then set `UPLOAD_STORAGE_PATH=/srv/vps-private/uploads` in `.env`. `www-data` (the Apache account) may read and write the folder, your user can too, nobody else can, and new files inherit the group.
`/srv/vps-private` itself needs `chmod 755` or `750` with group `www-data` so that Apache can enter it. Do not put the folder inside `/var/www/visitor-photo-slideshow`.
Restart Apache after changing `.env` only if you set the value with `SetEnv`. If PHP's `open_basedir` is set, the folder must be on its list.

### Run and test on Windows (XAMPP)

1. Make sure Steps 1 to 4 work (a visitor can register and identify) and set the PHP limits from *Required PHP upload settings*; restart Apache.
2. Copy the new files: `upload.php` into the project folder, `includes\uploads.php` into `includes`, `tests\check_upload.php` into `tests`, and replace `config\config.php`, `.env.example`, `README.md`, `ASSUMPTIONS.md`, `ARCHITECTURE.md` and `AI_LOG.md`. Keep your own `.env` (nothing has to be added to it).
3. (Recommended) set up the private folder as shown above.
4. Run the command-line checks (both change nothing):

   ```bat
   cd C:\xampp\htdocs\visitor-photo-slideshow
   C:\xampp\php\php.exe tests\check_database.php
   C:\xampp\php\php.exe tests\check_upload.php
   ```

   Every line should start with `[PASS]` (a `[WARN]` about the storage folder is information) and the last line should be `RESULT: PASS`.
5. Run the browser tests below. **Write your own result in the last column.** Prepare: one small valid `.jpg`, one `.png`, one `.webp`, one `.gif`, one text file renamed to `fake.jpg`, a copy of a PNG renamed to `wrong.jpg`, a photograph larger than 5 MB, and 11 small images.

### Tests to run in the browser

| # | Test | Expected result | Your result |
|---|------|-----------------|-------------|
| 1 | In a **new private window** open `upload.php` without identifying. | You are sent to `identify.php`. Nothing is uploaded. | |
| 2 | Identify (Step 4), open `upload.php`, choose **one** valid `.jpg`, tick the consent box, press **Upload photographs**. | The address becomes `upload.php?done=1` and says "1 photograph was uploaded successfully." | |
| 3 | Choose the `.jpg`, `.png` and `.webp` together, tick consent, upload. | "3 photographs were uploaded successfully." | |
| 4 | Check phpMyAdmin (below). | The new `photos` rows have your visitor id, original names, MIME types, sizes, status `active`, and a `consent_id` of a new `consents` row (version `1.0`) for **your** visitor. | |
| 5 | Look at the stored names in phpMyAdmin and in the private folder. | Names are 48 random characters plus an extension, unrelated to the original names. Original names appear only in `original_filename`. | |
| 6 | Upload the `.gif`, the `fake.jpg` and a `.txt` file. | Each is listed as not uploaded ("Only JPEG, PNG and WebP photographs are accepted"). Nothing stored. | |
| 7 | Upload the `wrong.jpg` (a PNG named `.jpg`). | Rejected: "The file name ending does not match the type of the file." | |
| 8 | Upload the photograph larger than 5 MB. | Rejected as too large (by the application, or by PHP if your `upload_max_filesize` is lower). | |
| 9 | Choose 11 images and upload. | "You can upload at most 10 photographs at a time. Nothing was uploaded." (If PHP's `max_file_uploads` is below 11, PHP drops the extra files itself: raise it to 20.) | |
| 10 | Choose a valid photo, **do not tick** the consent box, upload. | "Please tick the consent box ..." and nothing stored (no new `photos` or `consents` row). | |
| 11 | Send a wrong CSRF token (below). | HTTP **403**, nothing stored. | |
| 12 | Press **F5** on the success page. | The form appears again, nothing is uploaded a second time (row counts unchanged). | |
| 13 | Identify as a **second** visitor in a private window and upload one photo. | The row has the second visitor's id; the first visitor's rows are unchanged. | |
| 14 | Press **End my session** on `identify.php`, then open `upload.php`. | You are sent to `identify.php` again. | |
| 15 | Failed database insertion leaves no orphaned files: run `tests\check_upload.php` (it forces failures). | The lines marked `13.` are `[PASS]`. | |
| 16 | Steps 1 to 4 still work: `check_database.php`, register, identify. | `RESULT: PASS`; registration and identification work as before. | |

**Test 11 (curl; on Windows type `curl.exe`; `photo.jpg` is any file):**

```
curl -i -X POST -F "csrf_token=wrong" -F "consent=yes" -F "photos[]=@photo.jpg" http://localhost/visitor-photo-slideshow/upload.php
```

Without a session cookie this answers with a redirect to `identify.php` (test 1 as a command). To see the 403, identify in the browser, copy the value of the
`vps_session` cookie (F12 > Application/Storage > Cookies) and add `--cookie "vps_session=<value>"` to the command.

### Verify the photo records in phpMyAdmin

1. Open <http://localhost/phpmyadmin>, click **visitor_photo_slideshow**, then open the **SQL** tab and run:

   ```sql
   SELECT p.id, p.visitor_id, v.name AS visitor, p.consent_id, c.consent_version, c.consented_at,
          p.stored_filename, p.original_filename, p.mime_type, p.file_size, p.status, p.uploaded_at
   FROM photos p
   JOIN visitors v ON v.id = p.visitor_id
   JOIN consents c ON c.id = p.consent_id
   ORDER BY p.id DESC LIMIT 20;
   ```

2. Every row must show the right visitor, a `consent_version` of `1.0`, a random `stored_filename`, the original name only in `original_filename`, and status `active`.
3. One consent row per upload: photos uploaded in the same submission share one `consent_id`:

   ```sql
   SELECT c.id, c.visitor_id, c.consent_version, c.consented_at, COUNT(p.id) AS photos
   FROM consents c LEFT JOIN photos p ON p.consent_id = c.id
   GROUP BY c.id ORDER BY c.id DESC LIMIT 10;
   ```

4. Each `stored_filename` must exist as a file in the private folder, in the sub-folder named after its first two characters.
5. A failed submission leaves **no** new `photos` or `consents` row and no new file.

### Common problems (Step 5)

| Problem | Usual cause and fix |
|---------|---------------------|
| "Uploads are not available because of a technical problem" | The storage folder cannot be created or written (check `UPLOAD_STORAGE_PATH` and permissions), or `fileinfo` / `pdo_mysql` is not enabled. Read the error log (XAMPP: `C:\xampp\apache\logs\error.log`; Ubuntu: `/var/log/apache2/error.log`). |
| "The photographs together are larger than the server accepts" | `post_max_size` is smaller than the files together. Raise it (README, *Required PHP upload settings*) and restart Apache. |
| Only some of many files arrive | `max_file_uploads` in `php.ini` is lower than the number of files. |
| Every photo is "larger than the server accepts" | `upload_max_filesize` is lower than 5 MB (the PHP default is 2 MB). |
| Every submit shows "The form could not be verified" | Cookies are blocked or the PHP session folder is not writable (same cause as for registration). |
| `tests/check_upload.php` prints a `[WARN]` about the storage folder | The folder is inside the project. Move it outside (above). |
| The command-line check passes but uploads fail in the browser | The command line uses a different `php.ini` than Apache. Compare `upload_max_filesize`, `post_max_size`, `max_file_uploads` in both (`phpinfo()` in a temporary page, then delete it). |

### Not built in Step 5

The visitor gallery, the slideshow, the administrator pages and any way to view or delete an uploaded photograph. Uploaded photographs are stored exactly as received
(EXIF data such as a GPS position is **not** removed, and images are not resized). Uploads are not limited per visitor or per day.
A consent cannot be withdrawn. Step 5 has not been executed by anyone: it was written without access to PHP, a web server or a database, so no result in this section has been observed. See `AI_LOG.md`.

---

## Visitor photo gallery (Step 6)

An **identified** visitor opens `gallery.php` and sees **their own** photographs, 20 per page, newest first: the photograph, the original file name,
the upload date and the status. Photographs from earlier visits are included, because they are found by the visitor, not by the visit.
The images are not public files: each one is loaded through `photo.php`, which checks the owner again and then sends the file from the private folder.

| Platform | Address of the page |
|----------|---------------------|
| Windows (XAMPP) | <http://localhost/visitor-photo-slideshow/gallery.php> |
| Ubuntu (virtual host from this README) | <http://localhost/gallery.php> |

### Files added or changed in Step 6

| File | Purpose |
|------|---------|
| `gallery.php` (new) | The page: login check, list, pagination links, upload link, "End my session" button. All HTML is written by PHP; no CSS, no JavaScript. |
| `photo.php` (new) | `photo.php?id=N`: sends one photograph to its owner (see below). |
| `includes/gallery.php` (new) | The visibility rule as SQL, number and page validation, safe file-path resolution, file-type detection. No HTML. |
| `tests/check_gallery.php` (new) | Command-line check of the gallery logic. Changes nothing (transaction rolled back, temporary folder deleted). |
| `includes/security.php` (changed) | `send_security_headers()` got one optional parameter (default `false`) that adds `img-src 'self'`. Every other page sends exactly the headers it sent before. |
| `upload.php`, `identify.php` (changed) | One link line each, pointing to `gallery.php`. |
| `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md` (I), `AI_LOG.md` | Documentation. |

`database/schema.sql` is **unchanged** (nothing to re-import), as are `register.php`, `config/*`, `includes/bootstrap.php`, `includes/visitors.php`, `includes/visitor_auth.php` and `includes/uploads.php`.

### How gallery retrieval works

1. `visitor_require_login()` (Step 4). An unidentified visitor is sent to `identify.php`. The visitor id comes from the **server-side session** only; `gallery.php?visitor_id=2` is ignored.
2. One `SELECT COUNT(*)` counts the photographs this visitor may see. That gives the number of pages.
3. The requested page (`gallery.php?page=N`) is validated: anything that is not a positive whole number means page 1, and a number past the end shows the last page.
4. One `SELECT ... LIMIT 20 OFFSET n` reads **only that page**. Both queries are PDO prepared statements.
5. The page writes one `<img src="photo.php?id=N">` per photograph. File paths and stored file names never appear in the page. Every output value goes through `htmlspecialchars()` (`html_escape()`).
6. If a photograph's file is missing, the row says "The image file is not available." instead of a broken image.

### Which photographs are shown (visibility and consent)

A photograph is listed and served only when **all three** are true: it belongs to the identified visitor, its `status` is `active`, and a `consents` row exists for it
(`photos.consent_id` pointing at a consent of the same visitor). `hidden` photographs are never shown. Consent records are never changed.

**Limitation (no field was invented).** The schema has only the statuses `active` and `hidden`, and no field that separates "this visitor may see their own photograph"
from "approved for the public slideshow". Uploaded does **not** mean approved, so the gallery applies the stricter slideshow rule. Consequences: a visitor cannot see their own
`hidden` photographs, there is no `deleted` or `rejected` status to filter, and an old consent text version stays valid. See `ASSUMPTIONS.md`, section I.

### How private photographs are displayed (`photo.php`)

`photo.php?id=N` checks, in this order, and stops at the first failure with a short plain-text answer:

| Check | Failure |
|-------|---------|
| Method is GET or HEAD | 405 |
| A visitor is identified (session) | 401 (no redirect: the answer is an image) |
| `id` is a positive whole number | 400 |
| The photograph exists, belongs to **this** visitor, is `active` and has its consent record | 404 (the same answer as "does not exist") |
| The stored name has the generated shape (48 hex characters + `jpg`/`png`/`webp`) and the real file lies inside the private storage folder (`realpath()`, symbolic links resolved) | 404 |
| The real file content (PHP Fileinfo) is JPEG, PNG or WebP and equals the type in the database | 404 |

Then it sends `Content-Type` (the verified type), `Content-Length`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store`, `Content-Security-Policy: default-src 'none'; sandbox`,
`Cross-Origin-Resource-Policy: same-origin` and a generated `Content-Disposition` name (`photo-<id>.<ext>`), and then calls `readfile()`. The physical path and the stored name are never sent.
A file is never executed: PHP only reads it and labels it as an image. There is no public URL into the private folder.

### How ownership is enforced

The visitor id is taken from the session in both files. The same `WHERE p.visitor_id = <session visitor> AND p.status = 'active'` (plus the consent join) decides what is listed and what may be
downloaded, so changing the number in `photo.php?id=N` finds nothing. A photograph of another visitor, a hidden one and a number that does not exist all give the same 404.

### How pagination works

20 photographs per page (`GALLERY_PER_PAGE`), `LIMIT`/`OFFSET`, newest first (`ORDER BY id DESC`, which uses the index on `photos.visitor_id`). A separate `COUNT(*)` gives the total; only the rows of
the current page are read. **Previous** and **Next** are ordinary links (`gallery.php`, `gallery.php?page=2`, ...); at the first or last page the missing direction is plain text. The page says "Page 1 of 2 (26 photographs in total)".

### Run and test on Windows (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. The project is in `C:\xampp\htdocs\visitor-photo-slideshow` and the database is imported (see the XAMPP setup above). **No re-import is needed for Step 6.**
2. Open <http://localhost/visitor-photo-slideshow/register.php>, register a visitor and **save the code**. Open `upload.php` and upload two or three photographs (tick the consent box).
3. Open <http://localhost/visitor-photo-slideshow/gallery.php>. You should see your photographs, their names and dates.
4. Optional logic check (changes nothing): `C:\xampp\php\php.exe tests\check_gallery.php` from the project folder. The last line must be `RESULT: PASS`.

### Tests to run in the browser

| # | Test | Expected result | Your result |
|---|------|-----------------|-------------|
| 1 | Identify, open `gallery.php`. | The page opens and lists your photographs with image, file name, upload date and status `Active`. | |
| 2 | Upload more photographs in a **second** upload, then open the gallery. | Photographs from both uploads are listed (newest first). | |
| 3 | End the session, identify again (a later "visit"), open the gallery. | The earlier photographs are still listed. | |
| 4 | Register a **second** visitor in a private window, upload one photograph, open the gallery. | Only that visitor's photograph appears; the first visitor's photographs do not. | |
| 5 | As visitor 1, open `photo.php?id=<id of a photograph of visitor 2>` (ids: phpMyAdmin, below). | Plain text "Photograph not found." (HTTP 404). No image. | |
| 6 | In a new private window (not identified) open `gallery.php`. | You are sent to `identify.php`. | |
| 7 | In the same window open `photo.php?id=1`. | Plain text "Please identify yourself first." (HTTP 401). | |
| 8 | In phpMyAdmin set one photograph to `hidden` (SQL below), reload the gallery, open its `photo.php?id=N`. | It is not listed; `photo.php` answers 404. Set it back to `active` afterwards. | |
| 9 | Open `photo.php?id=abc`, `photo.php?id=0`, `photo.php?id=-1`, `photo.php` and `photo.php?id=999999`. | The first four: "Invalid photograph number." (400). The last: "Photograph not found." (404). | |
| 10 | Temporarily rename one stored file in the private folder, reload the gallery, open its `photo.php?id=N`; rename it back. | The gallery row says "The image file is not available."; `photo.php` answers 404; no path is shown. | |
| 11 | Upload 26 photographs in three uploads (10 + 10 + 6; the limit is 10 per upload), open the gallery. | Page 1 shows exactly 20; "Page 1 of 2 (26 photographs in total)". | |
| 12 | Click **Next**, then **Previous**. | Page 2 shows the remaining 6; Previous returns to page 1. At page 2 "Next" is not a link; at page 1 "Previous" is not a link. | |
| 13 | Try `gallery.php?page=0`, `gallery.php?page=abc`, `gallery.php?page=99`. | Page 1, page 1, last page. No error. | |
| 14 | Register a third visitor and open the gallery before uploading. | "You have no photographs to show yet." and a link to the upload page. | |
| 15 | Press **End my session** on the gallery page, then open `gallery.php`. | You are sent to `identify.php`. | |
| 16 | Steps 1 to 5 still work: `check_database.php`, `check_identify.php`, `check_upload.php`, register, identify, upload. | `RESULT: PASS` in each; the pages work as before. | |

### Verify gallery records in phpMyAdmin

1. Open <http://localhost/phpmyadmin>, click **visitor_photo_slideshow**, open the **SQL** tab and run (replace `1` with your visitor id):

   ```sql
   -- What the gallery of visitor 1 shows: own photographs, status active, matching consent.
   SELECT p.id, p.original_filename, p.status, p.uploaded_at, c.consent_version
   FROM photos p
   INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id
   WHERE p.visitor_id = 1 AND p.status = 'active'
   ORDER BY p.id DESC;

   -- How many pages that makes (20 per page):
   SELECT COUNT(*) AS photos, CEIL(COUNT(*) / 20) AS pages
   FROM photos p
   INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id
   WHERE p.visitor_id = 1 AND p.status = 'active';

   -- Photographs the gallery does NOT show (hidden), for any visitor:
   SELECT id, visitor_id, original_filename, status FROM photos WHERE status <> 'active';
   ```

2. The first query must return exactly the rows the gallery shows, in the same order. The second gives the "Page 1 of N" numbers.
3. To test hiding: `UPDATE photos SET status = 'hidden' WHERE id = 3;` and later `UPDATE photos SET status = 'active' WHERE id = 3;`.

### Common problems (Step 6)

| Problem | Usual cause and fix |
|---------|---------------------|
| The gallery lists photographs but the images do not show | Open `photo.php?id=N` directly: 401 means the session expired; 404 with "missing" in the error log means the file is not in the folder named by `UPLOAD_STORAGE_PATH` (if you moved the folder, the files must move too); a blank page means a PHP error (read the Apache error log). |
| "The image file is not available." for every photograph | `UPLOAD_STORAGE_PATH` points to a different folder than the one the files were uploaded to. |
| Gallery is empty although photographs were uploaded | They are `hidden`, or you identified as a different visitor (each visitor sees only their own). |
| Images load slowly | Full-size files are sent (no thumbnails in this step). |

### What was actually tested (by the AI, in a Linux sandbox)

Environment: Ubuntu, **PHP 8.3.6 (command line and `php -S` built-in server)**, **MariaDB 10.11.14**, `curl`, with `database/schema.sql` imported unchanged. Private storage was a folder outside the project.
The real pages were driven over HTTP with `curl` (register, identify, upload, gallery), for several visitors, with real PNG/JPEG/WebP files. Those `curl` scripts are not part of the project.

| Requirement | How it was tested | Result |
|-------------|-------------------|--------|
| 1 Authenticated visitor opens the gallery | `curl` with the session cookie of a registered, identified visitor | Passed (HTTP 200) |
| 2, 3 Sees photographs from earlier uploads | One visitor, two separate upload submissions (two consent records); both appear | Passed |
| 4 Cannot see another visitor's photographs | Two visitors; each gallery contains only its own files; `?visitor_id=2` in the address is ignored | Passed |
| 5 Changing the photo id does not expose another visitor's photograph | Visitor 1 requested visitor 2's id and the reverse; a non-existent id | Passed (404 in every case, same answer) |
| 6 Unauthenticated gallery | No cookie | Passed (303 redirect to `identify.php`) |
| 7 Unauthenticated `photo.php` | No cookie | Passed (401) |
| 8 Hidden / restricted photographs | `status` set to `hidden` directly in the database; consent id changed to a missing one and to another visitor's consent (with foreign-key checks switched off for that test only, to simulate a damaged record); then restored | Passed (not listed, `photo.php` 404) |
| 9 Invalid photo ids | `abc`, `0`, `-1`, `1.5`, `01`, empty, 20 digits, `id[]=1`, `1 OR 1=1`, `1;DROP`, no parameter, a trailing line break | Passed (400) |
| 10 Missing physical file | File renamed away and back | Passed (gallery says "not available", `photo.php` 404, no path shown) |
| Extra: file whose content is PHP code but is named `.png` | Content replaced and restored | Passed (404, nothing executed) |
| Extra: stored name `../../../../etc/passwd` placed in the database | Row changed and restored | Passed (404, nothing leaked) |
| 11 No more than 20 per page | A visitor with 26 photographs | Passed (20 on page 1, 6 on page 2) |
| 12 Previous / Next | Link targets and plain-text ends checked on both pages; `page=abc`, `0`, `-4`, `1.5`, `page[]=2`, 20 digits, `99` | Passed |
| 13 Empty gallery | New visitor | Passed (message and upload link) |
| 14 Steps 1 to 5 still work | `tests/check_database.php`, `check_identify.php` (22 checks), `check_upload.php` (42 checks), and the live register / identify / upload flow | Passed |
| Extra: output escaping | A file named `a"><img src=x onerror=alert(1)>.png` | Passed (shown as text) |
| Extra: logout | POST with the CSRF token ends the session; a wrong token gives 403; afterwards gallery 303 and `photo.php` 401 | Passed |
| Extra: response headers | `Content-Type` (png, jpeg, webp), `nosniff`, `no-store`, fixed file name, no path in any header; HEAD gives no body; POST gives 405; the served bytes are identical to the uploaded file | Passed |
| `tests/check_gallery.php` | Run against the sandbox database | `RESULT: PASS - 64 passed, 0 failed` |

**Found and fixed during testing:** the first version of the number check used `$` in its regular expression, which in PHP also matches before a final line break, so `"1"` followed by a line break was accepted
(harmless, it became 1, but wrong). It now uses `\z`. Two of my own test lines also failed at first because the test, not the application, was wrong (a pattern that matched the CSRF token, and a hostile file name that
the Step 5 cleaning had already shortened); they were corrected and re-run.

**Not tested:** Windows, XAMPP, Apache, `.htaccess` rules, phpMyAdmin, Ubuntu with Apache (the sandbox used PHP's built-in server), and any real web browser. In particular `curl` does not enforce the
`Content-Security-Policy`, so **that a browser actually displays the thumbnails under `img-src 'self'` has not been observed**; the header text was checked, not the browser behaviour. Symbolic links were tested on Linux only
(the command-line check skips that test when links cannot be created). The browser table above is for you to fill in.

### Not built in Step 6

The slideshow, the administrator pages, deleting or hiding a photograph from the gallery, downloading originals under their own names, thumbnails and any change of a consent record. Photographs are sent at full size
and are never cached by the browser (`no-store`). `photo.php` serves only the owner; the public slideshow will need its own rule (`ARCHITECTURE.md`, section 9).

---

## Windows and Ubuntu: what differs

Both use the **same PHP source code and the same `schema.sql`**.

| Topic | Windows (XAMPP) | Ubuntu |
|-------|-----------------|--------|
| Database server | MariaDB (bundled) | MySQL 8 (or MariaDB) |
| Web folder | `C:\xampp\htdocs\visitor-photo-slideshow` | `/var/www/visitor-photo-slideshow` |
| php.ini | `C:\xampp\php\php.ini` | `/etc/php/<version>/apache2/` |
| Apache account | Your user, or the service account | `www-data` |
| Upload permissions | `icacls` / Explorer *Security* tab | `chown` + `chmod` with group `www-data` |
| Restart Apache | XAMPP Control Panel Stop/Start | `sudo systemctl restart apache2` |
| Run the database check | `C:\xampp\php\php.exe tests\check_database.php` | `php tests/check_database.php` |
| Folder protection | `.htaccess` (honoured by default) | Virtual-host `<Directory>` rules |
| Path style in `.env` | `D:\private\uploads` style | `/srv/private/uploads` style |

In application code, paths are built with `__DIR__` and `DIRECTORY_SEPARATOR`, and URLs come only from `APP_URL`.

## Moving data between Windows and Ubuntu

* **Database.** Export with phpMyAdmin (*Export* tab, SQL format) or:

  ```bash
  mysqldump --single-transaction --default-character-set=utf8mb4 -u vps_user -p --result-file=backup.sql visitor_photo_slideshow
  ```

  Use `--result-file` instead of `>` on Windows: PowerShell's `>` can write the file in UTF-16, which
  cannot be imported. Import on the other machine into an empty, already-created database with
  phpMyAdmin or `mysql -u vps_user -p visitor_photo_slideshow < backup.sql`.
* **Photographs.** Copy the contents of the upload folder (`storage/uploads` or your
  `UPLOAD_STORAGE_PATH`) together with the database export, because the database stores only file names.

## Limitations (Steps 1 to 6)

Built so far: the schema, the documentation, the configuration loader, the database connection, visitor registration, returning visitor identification (Step 4), photo uploads (Step 5) and the visitor gallery (Step 6). Steps 4 to 6 were executed only in a Linux sandbox (PHP command line and built-in server, MariaDB), never on Windows, XAMPP or in a real browser.
Steps 2 and 3 have not been tested on Windows or XAMPP, in phpMyAdmin or in a real web browser (the AI tested them with a
scripted HTTP client; see `AI_LOG.md`). Registration has no rate limiting, so someone could create many visitor records by
repeating the form, and a lost visitor code cannot be recovered. Visitor names are stored as typed. The design is meant to
grow but has not been load-tested; see `ARCHITECTURE.md` (section 12) for limits and future improvements and
`ASSUMPTIONS.md` for open questions.


---

# Step 7: Automatic public photo slideshow

## Open the slideshow

After the database and private upload storage are configured, open this address on the TV or computer:

- Windows XAMPP: `http://localhost/visitor-photo-slideshow/slideshow.php`
- Ubuntu virtual host: `https://your-host/slideshow.php` (use the host configured for this project)

No visitor login is required. The public page is intentionally viewable without visitor login: the TV or computer displaying the photos is a public viewer, not a photo contributor. The people who contribute photos must register or identify themselves before uploading and must tick the explicit public-display consent checkbox for that upload. The page exposes only an eligible photograph ID in its image URL; it does not expose visitor codes, names, original filenames or storage paths. Keep the application and private storage permissions configured as described above.

### Why can the TV show photos if the TV viewer has not registered?

That is intentional: the shared TV is a public **viewer**, so it does not need to register. The photographs must still have been uploaded by a registered visitor who explicitly consented. Opening `slideshow.php` does not create visitors or photos. If nobody has ever registered and uploaded a photo into the database currently configured in `.env`, the page should show **No approved photographs are available**.

If the slideshow displays unexpected or old test pictures, the database may already contain records from earlier testing or another run. In phpMyAdmin, select the database configured by `.env` and run these read-only checks:

```sql
SELECT COUNT(*) AS registered_visitors FROM visitors;

SELECT p.id, p.visitor_id, p.status, c.consent_version
FROM photos p
INNER JOIN visitors v ON v.id = p.visitor_id
INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id
WHERE p.status = 'active' AND c.consent_version = '1.0'
ORDER BY p.id;
```

Private upload files are not included in the submission archive. Existing local uploads remain on your own machine; do not delete them unless you have verified they are disposable and backed up anything you need.

## Selection and consent rule

The slideshow reads one record at a time from `photos`, joined to `visitors` (so the visitor registration row must exist) and to `consents` on both `consent_id` and `visitor_id`. It includes only `photos.status = 'active'` and `consents.consent_version = '1.0'`. Version `1.0` is the current upload consent wording in `includes/uploads.php`, which explicitly says the uploaded photographs may be shown publicly on the slideshow screen. A future change to that wording must be reviewed deliberately: update the accepted public-consent version only after deciding whether old consent remains valid.

Public eligibility requires all of the following: an existing visitor row; `photos.status = 'active'`; `photos.approval_status = 'approved'`; a matching consent row for the same visitor; consent version `1.0`; and `consents.withdrawn_at IS NULL`. Pending and rejected photos are not public. Hidden photos are not public. The owner-only `photo.php` endpoint is unchanged. Public images are sent by the separate `slideshow_photo.php`, which rechecks eligibility, verifies the generated storage filename, resolves the real file inside private storage, checks its actual MIME type against the database and streams it with safe headers.

## Automatic progression and discovery

`slideshow.php` fetches the next eligible photo by ID (one metadata row at a time). When there is no larger eligible ID, it wraps to the first eligible photo. The browser's standard HTML meta refresh reloads the page after the configured display duration and passes the last photo ID as a cursor. Each page request performs a fresh database lookup, so newly uploaded and approved records can enter the sequence without restarting the browser. Newly activated older IDs appear when the sequence wraps. If the current photo is hidden or deleted before the next request, the next query simply moves on.

Advancing means selecting the next eligible ID and rendering its page. Refreshing the eligible list means querying MySQL again; this implementation does that on every advancement/reload, rather than keeping a cached in-memory list. The empty state checks again after 30 seconds. Missing or invalid physical files are skipped, with only the photo ID logged.

## Configure display duration

In `.env`, set `SLIDESHOW_DEFAULT_DURATION_SECONDS=5`. The default is 5 seconds; allowed values are 2 through 3600 seconds. The setting is validated by `config/config.php`. No administrator settings page or `settings` table management is added in Step 7.

## Full-photo rendering and display size

`slideshow.css` uses the actual browser viewport (`100vw` × `100vh`) and centers the original photo on a black background. The image is constrained by `max-width: 100vw` and `max-height: 100vh`, uses automatic width/height and `object-fit: contain`, so a large landscape or portrait photograph shrinks to fit the entire screen without cropping or stretching. The page does not depend on a fixed 1920 × 1080 canvas; it adapts to the TV/computer browser window and browser zoom.

The stylesheet deliberately does not force small images to enlarge. This avoids making low-resolution source files look even softer through artificial enlargement. If a small photo does not fill the screen, that is because its original pixel dimensions are smaller than the display area; CSS cannot create missing image detail. Use the original high-resolution photograph for the sharpest result. The upload pipeline stores the accepted original file and the slideshow endpoint streams those original bytes without generating a compressed preview or thumbnail. The CSS only scales large originals down to fit the viewport; it does not crop the photo.

CSS is permitted for the slideshow only. The slideshow CSP allows its own same-origin stylesheet and image endpoint; JavaScript remains blocked and is not used.

## PHP/browser limitation

This is not true real-time push, uninterrupted animation or a client-side slideshow. Each advance is a complete HTTP page request and reload controlled by the browser's HTML meta-refresh support. A slow network, server, database or image transfer can delay the next photograph; a browser may also restrict automatic refresh in some kiosk/power-saving configurations. Newly approved images are discovered at the next database lookup, not pushed immediately.

## Step 7 verification record

Run the static slideshow rendering/eligibility check from the project folder with `php tests/check_slideshow_rendering.php`; it does not require a database. It checks that the CSS fits the browser viewport without forced upscaling or cropping and that selection/serving queries require an existing visitor row, matching consent, and active photo status.

For this correction, `php -l` reported no syntax errors in all 23 PHP files, and `php tests/check_slideshow_rendering.php` passed all 11 static rendering/eligibility checks. `getimagesize()` was also run against the nine private PNG files bundled in the ZIP; their source dimensions range from 191×178 to 337×226 pixels. A MySQL/MariaDB service and real browser/XAMPP session were not available in this environment, so database-backed selection, consent filtering against live rows, automatic browser progression, and visual TV rendering were **not executed**. The CSS behavior must still be visually confirmed in the target browser/display. Do not mark database, browser, cycling, missing-file or Steps 1–6 regression tests as passed until you execute them against your configured database and browser. Earlier Step 1–6 test claims above are historical records from the supplied project documentation, not tests rerun during this correction.

Suggested manual checks on XAMPP: create/upload with the existing public-display consent; confirm the photo appears; set its status to `hidden` and confirm it disappears after a reload; restore it; remove/rename its physical file and confirm it is skipped; hide all eligible photos and confirm the empty message; restore multiple eligible photos and watch them cycle. Test the gallery and owner-only `photo.php` separately after each change.


## Step 8: Administrator dashboard

The administrator area is under `admin/`. Open `admin/login.php`; after login the dashboard shows database-backed counts, visitor search/pagination, and photo review. All changing actions use POST and the session CSRF token. Admin previews are served only through the authenticated `admin/photo.php` endpoint; storage paths are not output.

### One-time administrator creation

There are no seeded/default credentials and no public web setup page. Run the following from the project directory after importing the updated schema and configuring `.env`:

- Windows XAMPP: `C:\xampp\php\php.exe admin\create_admin.php`
- Ubuntu: `php admin/create_admin.php`

Enter a username and a password of at least 12 characters at the terminal. Terminal input for the password is visible; avoid running this where others can see the screen. The script runs only when the `admins` table is empty and refuses to create another initial account.

### Step 8 database migration

For an existing Step 7 database, run this once in phpMyAdmin or the MySQL/MariaDB client:

```sql
ALTER TABLE photos
    ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER status;
UPDATE photos SET approval_status = 'pending';
```

The `DEFAULT 'pending'` ensures existing and newly uploaded photos are not public until an administrator reviews them. The full `database/schema.sql` also includes this column and a check constraint for new installations. Do not skip the migration on an existing Step 7 database. Uploads retain their visitor consent record but start pending. Public slideshow selection and serving both require `status='active'`, `approval_status='approved'`, and consent version `1.0`. Approval never creates consent. Hiding sets `status='hidden'` and `approval_status='rejected'`.

Photo review currently displays the latest 200 records; visitor listing is paginated at 20 records per page. No visitor code or authentication hash is selected for display.

**Verification status for Step 8:** PHP syntax checks were run in the provided build environment. A live database, browser session, XAMPP, Ubuntu Apache, and end-to-end regression tests were not available here; do not treat those tests as passed. Run the checklist below on the target installation.

1. Create the first admin using the CLI command; repeat and confirm it refuses.
2. Confirm valid login succeeds, invalid login fails, direct dashboard access redirects, and POST logout ends the session.
3. Confirm CSRF-less photo actions and logout are rejected.
4. Check dashboard counts against SQL counts; search visitors and navigate beyond page 1.
5. Upload a photo with consent: it must be pending and absent from the public slideshow. Approve it: it should display. Hide it: it must disappear.
6. Confirm a photo with no matching consent or a consent version other than `1.0` cannot be approved.
7. Re-test visitor registration, identification, upload, owner gallery and slideshow.
8. Repeat on Windows XAMPP and Ubuntu Apache/PHP/MySQL or MariaDB.


## Step 9: privacy and security controls

- Visitor and administrator sessions use cookie-only, strict-mode PHP sessions with HttpOnly,
  SameSite=Lax and Secure cookies when the application URL is HTTPS. Authentication rotates
  the session ID; logout clears session data and expires the session cookie.
- Visitor galleries and private photo delivery use the authenticated visitor ID from the server
  session and a database ownership check. Public photo delivery is a separate endpoint and only
  serves photos whose status is active, approval is approved, and linked consent is version 1.0
  and has not been withdrawn.
- Visitors can open `privacy.php` from their gallery and withdraw public-display consent. This
  marks every currently active consent row for that visitor with `withdrawn_at`; historical
  consent rows are retained. The private gallery remains available to the owner.
- All existing modifying forms use session CSRF tokens and POST. Administrator pages and photo
  previews require an authenticated administrator; approval requires active, matching visitor
  consent. Passwords are verified with PHP password hashing APIs.
- Private photo storage should be configured outside the web root with `UPLOAD_STORAGE_PATH`.
  If it remains under the project, Apache deny rules must remain enabled. Never commit `.env`,
  visitor images, database dumps, or real credentials.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production. PHP errors and exception details are never sent to browsers; details are logged server-side.
  public responses use neutral messages. Use a least-privilege database account and restrictive
  filesystem permissions (do not use world-writable permissions).

### Step 9 database upgrade

For an existing Steps 1–8 database, run `database/step9_consent_withdrawal_migration.sql` once. The migration was not run against your database while preparing this archive.
For a new database, import the updated `database/schema.sql`. The only schema addition is nullable
`consents.withdrawn_at`, which preserves prior consent rows and records withdrawal time.


## Step 10: final testing and submission status

The current source archive was reviewed and tested with the checks listed in `TESTING_REPORT.md`.
All PHP files pass `php -l` in PHP 8.4.24 CLI. The slideshow rendering checks and Step 9
static security checks pass. Database-backed checks could not complete because the available
PHP runtime does not have `pdo_mysql`; no live MySQL/MariaDB database, Windows XAMPP,
Ubuntu web server, or browser workflow was available in this review. Therefore the application
has **not** been fully end-to-end tested and must not be described as verified on both platforms.

The submission copy deliberately excludes `.env` and every private file under
`storage/uploads/`; it keeps `.env.example` and `storage/uploads/.gitkeep`. Copy `.env.example`
to `.env` and configure your own database credentials and private upload directory before use.
See `TESTING_REPORT.md` for the exact checks, outcomes, and remaining work.
