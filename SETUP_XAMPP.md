# XAMPP setup (Windows)

This project ZIP is arranged so that `register.php` is directly inside the
`visitor-photo-slideshow` folder and its required PHP files are inside `includes/`.

## 1. Extract the project

Extract the `visitor-photo-slideshow` folder into:

`C:\\xampp\\htdocs\\`

The important paths must be:

- `C:\\xampp\\htdocs\\visitor-photo-slideshow\\register.php`
- `C:\\xampp\\htdocs\\visitor-photo-slideshow\\includes\\bootstrap.php`
- `C:\\xampp\\htdocs\\visitor-photo-slideshow\\includes\\security.php`
- `C:\\xampp\\htdocs\\visitor-photo-slideshow\\includes\\visitors.php`
- `C:\\xampp\\htdocs\\visitor-photo-slideshow\\.env`

Do not open a second nested `visitor-photo-slideshow` folder.

## 2. Start XAMPP

Open XAMPP Control Panel and start **Apache** and **MySQL**.

## 3. Create the database

1. Open `http://localhost/phpmyadmin/`.
2. Select the **SQL** tab.
3. Open `database/create_database_xampp.sql` in a text editor, copy its contents,
   paste into the SQL tab, and click **Go**.
4. Click the `visitor_photo_slideshow` database in the left sidebar.
5. Select **Import**, choose `database/schema.sql`, and click **Import/Go**.

The schema import creates the tables required by the application.

## 4. Check database credentials

The included `.env` is preconfigured for a common fresh XAMPP installation:

- host: `127.0.0.1`
- port: `3306`
- database: `visitor_photo_slideshow`
- username: `root`
- password: empty

If you set a MySQL root password, edit `DB_PASSWORD=` in `.env` and enter that
password after the equals sign. If your MySQL username or port differs, update
`DB_USER` or `DB_PORT` too. Do not share your `.env` file publicly.

## 5. Check PHP extensions

In XAMPP's `php.ini`, make sure these extensions are enabled (remove the leading
semicolon if present), then restart Apache:

- `extension=pdo_mysql`
- `extension=mbstring`

## 6. Open registration

Visit:

`http://localhost/visitor-photo-slideshow/register.php`

The registration page should display a visitor name form. Submit a name containing
at least two letters to test registration.

## Troubleshooting

- **404 Not Found:** confirm the project folder is directly under `htdocs` and
  the URL does not contain `/visitor-photo-slideshow/visitor-photo-slideshow/`.
- **DB connection error:** confirm MySQL is running, the database exists, and
  `.env` credentials match your local MySQL account.
- **could not find driver:** enable `pdo_mysql` in `php.ini` and restart Apache.
- **mb_* function missing:** enable `mbstring` in `php.ini` and restart Apache.
- **Still seeing a generic error:** with `APP_DEBUG=true` in this local development
  `.env`, reload the page; the page should show a useful configuration exception.
  Also check the Apache/PHP error log from XAMPP Control Panel > Apache > Logs.

For safety, change `APP_DEBUG=false` before deploying anywhere public.
