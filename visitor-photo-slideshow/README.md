# Visitor Photo Slideshow System

A PHP and MySQL web application, built as College Assignment 1, that lets visitors upload photographs,
record their consent, and have their photographs shown on a public slideshow.

> **Current status: Step 1 of a larger project (foundation and database design).**
> The folder structure, database schema and documentation exist. **No application pages exist yet**
> (no registration, upload, gallery, slideshow or admin screens). Those are built in later steps.

## Project description

Visitors are identified by a unique **visitor code** (no phone number or email needed). A returning
visitor can upload more photographs and keeps the earlier ones. A photograph can appear on the public
slideshow only if the visitor's consent has been recorded. Administrators manage visitors, photographs
and settings.

## Main objectives

* Let visitors upload several photographs at a time (up to 10 per request, 5 MB each, JPG/JPEG/PNG/WebP).
* Record consent and show a photograph publicly only when consent exists.
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
| Configuration and database connection | Read `.env`, connect with PDO | planned |
| Visitor registration and code | Create visitor, generate and hash code | planned |
| Consent | Record consent for each photo-upload session | planned |
| Photo upload and storage | Validate, store privately, save metadata | planned |
| Visitor gallery | Returning visitors see their photographs (paginated) | planned |
| Public slideshow | Show consented, active photographs | planned |
| Administration | Login, visitor and photo management, settings | planned |

## Project structure

```
visitor-photo-slideshow/
├── config/            private configuration code (empty for now)
├── includes/          reusable PHP functions (empty for now)
├── admin/             administrator pages (empty for now)
├── database/
│   └── schema.sql     database tables
├── storage/
│   └── uploads/       private photograph storage (contents are never committed)
├── tests/             tests (empty for now)
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
   *The code that reads `.env` is written in Step 2; until then nothing uses the file.*
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
   random `VISITOR_CODE_PEPPER`. *The code that reads `.env` is written in Step 2.*

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

## Limitations (Step 1)

Nothing is built beyond the schema and documentation. The design is meant to grow but has not been
load-tested; see `ARCHITECTURE.md` (section 12) for limits and future improvements and
`ASSUMPTIONS.md` for open questions.
