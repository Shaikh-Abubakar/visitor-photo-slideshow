# Visitor Photo Slideshow System

A PHP and MySQL web application, built as College Assignment 1, that lets visitors upload photographs,
record their consent, and have their photographs shown on a public slideshow.

> **Current status: Step 2 of a larger project (PHP configuration and database connection).**
> The folder structure, database schema, documentation, configuration loader and database connection
> exist. **No application pages exist yet** (no registration, upload, gallery, slideshow or admin
> screens). Those are built in later steps.

## Project description

Visitors are identified by a unique **visitor code** (no phone number or email needed). A returning
visitor can upload more photographs and keeps the earlier ones. A photograph can appear on the public
slideshow only if the visitor's consent has been recorded. Consent is recorded again for **every upload
session**: each session creates its own consent record, even when the consent text has not changed, and
the photographs of that session refer to it. Administrators manage visitors, photographs and settings.

## Main objectives

* Let visitors upload several photographs at a time (up to 10 per request, 5 MB each, JPG/JPEG/PNG/WebP).
* Record a consent record for every upload session, and show a photograph publicly only when its consent record exists.
* Recognise returning visitors by code and keep their previous photographs.
* Show an automatic slideshow (default 5 seconds per photograph).
* Give administrators a paginated way to manage content.
* Run from **one codebase** on Windows (XAMPP) and Ubuntu.
* Be designed to grow (see `ARCHITECTURE.md`), without claiming unlimited scale.

## Technology stack

| Part | Choice |
|------|--------|
| Language | PHP 8.2 or newer (`pdo_mysql`, `fileinfo`, `mbstring`) |
| Database | MySQL 8+ or compatible MariaDB (InnoDB, utf8mb4) |
| Database access | PDO with prepared statements |
| Web server | Apache 2.4 (XAMPP on Windows, `apache2` on Ubuntu) |
| Not used | Frameworks, Composer packages, JavaScript, Node.js, Python |

## Planned project modules

| Module | Purpose | Step |
|--------|---------|------|
| Foundation and database | Structure, schema, documentation | **1 (done)** |
| Configuration and database connection | Read `.env`, connect with PDO | **2 (done)** |
| Visitor registration and code | Create visitor, generate and hash code | planned |
| Consent | Record a consent record (with the consent text version) for every upload session | planned |
| Photo upload and storage | Validate, store privately, save metadata | planned |
| Visitor gallery | Returning visitors see their photographs (paginated) | planned |
| Public slideshow | Show consented, active photographs | planned |
| Administration | Login, visitor and photo management, settings | planned |

## Project structure

```
visitor-photo-slideshow/
├── config/
│   ├── config.php     configuration loader (reads .env and environment variables)
│   └── database.php   reusable PDO connection function
├── includes/
│   └── bootstrap.php  common start-up file that every page loads
├── admin/             administrator pages (empty for now)
├── database/
│   └── schema.sql     database tables
├── storage/
│   └── uploads/       private photograph storage (contents are never committed)
├── tests/
│   └── check_database.php   command-line check of the configuration and database
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

Other variables in `.env.example` (`VISITOR_CODE_PEPPER`, `UPLOAD_ALLOWED_MIME_TYPES`,
`SLIDESHOW_DEFAULT_DURATION_SECONDS`) are for later steps and are not read yet.

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
   In Step 1 there is no start page yet, so you will see a folder listing or an error; that is expected.
   The app's pages arrive in later steps.

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

    Browse to <http://localhost/> (or your `ServerName`). In Step 1 there is no start page yet, so
    **403 Forbidden** (directory listing is off) is expected. Pages arrive in later steps.

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

## Limitations (Steps 1 and 2)

Nothing is built beyond the schema, the documentation, the configuration loader and the database connection.
Step 2 has not been tested on Windows or XAMPP, or with MariaDB. The design is meant to grow but has not been
load-tested; see `ARCHITECTURE.md` (section 12) for limits and future improvements and
`ASSUMPTIONS.md` for open questions.
