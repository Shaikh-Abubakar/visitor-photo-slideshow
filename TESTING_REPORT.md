# Step 10: Final Testing Report

**Review date:** 2026-11-24  
**Project:** Visitor Photo Slideshow System  
**Review environment:** PHP 8.4.24 CLI on Linux; Fileinfo enabled; `pdo_mysql` unavailable.

## 1. Test results

| Test ID | Feature | Procedure performed | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| PHP-01 | PHP syntax | Ran `php -l` against every `*.php` file in the project (34 files). | Every PHP file parses without syntax errors. | All 34 files reported no syntax errors under PHP 8.4.24 CLI. | PASS |
| SEC-01 | Security source checks | Ran `php tests/check_step9_security.php`. | Static checks find the expected session, logout, consent-withdrawal, and privacy safeguards. | 11 of 11 source-level checks passed. This is not a live attack or browser test. | PASS |
| SLIDE-01 | Slideshow rendering and eligibility | Ran `php tests/check_slideshow_rendering.php`. | Slideshow uses viewport sizing, contains rather than crops images, and checks visitor/consent/approval conditions. | 11 of 11 static checks passed. | PASS |
| SLIDE-02 | Image dimensions and fit | Ran the corrected `php tests/check_slideshow_dimensions.php`. | PHP validates positive image dimensions; CSS preserves aspect ratio and constrains the image to the viewport. | 7 of 7 static checks passed. | PASS |
| GAL-01 | Gallery input and pagination logic | Ran `php tests/check_gallery.php` with temporary placeholder DB environment variables. | Invalid photo IDs/page values are rejected or normalized; page boundaries are correct. | 24 database-independent checks passed. The script then could not run its database-dependent checks because no PDO MySQL driver was available. | PASS |
| DB-01 | Database connection | Ran `php tests/check_database.php` with temporary placeholder DB environment variables. | Configuration loads and a PDO MySQL connection can be established. | Configuration loaded, but connection failed because `pdo_mysql` is not installed/enabled in this runtime. No database was reached. | NOT TESTED |
| VIS-01 | Returning visitor identification | Ran `php tests/check_identify.php`. | Existing visitor-code lookup and session behavior work against the configured database. | The script stopped at its database connection check; no identification flow was exercised. | NOT TESTED |
| UP-01 | Multiple photo upload | Ran `php tests/check_upload.php`. | Valid images are accepted; invalid, oversized, excessive, or non-consented uploads are rejected; database records and files remain consistent. | Fileinfo and PHP-version prechecks passed. Database-dependent checks could not run. CLI upload limits were `2M` per file and `8M` per request, below the documented application settings. | NOT TESTED |
| ADM-01 | Administrator workflow | Inspected admin routes and ran static security checks; no live database/browser login was available. | Login, dashboard, search, approval/rejection, private preview, CSRF enforcement, and logout work end-to-end. | No live administrator account or browser workflow was exercised. | NOT TESTED |
| PRIV-01 | Visitor ownership and consent withdrawal | Inspected implementation and ran static Step 9 checks. | Visitors can access only their own photos; withdrawal prevents public display; history remains. | Static source checks passed, but no database ownership/withdrawal request was executed. | NOT TESTED |
| SLIDE-03 | Public slideshow operation | Inspected selection/serving code and ran static checks. | Only approved, active, consented, non-withdrawn photos display; sequence advances and wraps; empty state is handled. | Query/source conditions passed static checks. No live database, browser, timing, or empty-state request was exercised. | NOT TESTED |
| DB-02 | Schema and relationships | Inspected `database/schema.sql` and the Step 9 migration text. | Required tables, keys, indexes, and foreign-key relationships import and behave correctly on MySQL/MariaDB. | The SQL files were inspected, but no schema import, migration, constraint test, or live database inspection was performed. | NOT TESTED |
| OS-01 | Windows XAMPP | No Windows/XAMPP environment was available. | Application installs and works on Windows XAMPP. | Not executed. | NOT TESTED |
| OS-02 | Ubuntu Apache/PHP/MySQL | Only PHP CLI on Linux was available; no target Apache/MySQL deployment was configured. | Application installs and works on Ubuntu Apache/PHP/MySQL or MariaDB. | Not executed as a web deployment. | NOT TESTED |
| CLEAN-01 | Submission archive cleanup | Inspected the supplied ZIP, removed `.env` and all 10 files under `storage/uploads/`, retained `.env.example` and `.gitkeep`, then inspected the output archive. | No local credentials or visitor photos are included. | The delivery copy excludes `.env` and private upload images. | PASS |
| GIT-01 | Git ignore rules | Inspected `.gitignore`. | Ignore local environment files, uploaded photos, dumps, logs, and temporary files while retaining `.env.example`, schema, and `.gitkeep`. | Rules cover `.env`, `.env.*` (with `.env.example` exception), `/storage/uploads/*` (with `.gitkeep` exception), database backups, logs, and OS/editor files. No real Git staging/commit test was performed. | PASS |

## 2. Environment observations

- PHP CLI: `8.4.24`.
- PHP Fileinfo: enabled.
- PDO MySQL (`pdo_mysql`): not installed/enabled.
- CLI `upload_max_filesize`: `2M`.
- CLI `post_max_size`: `8M`.
- CLI `max_file_uploads`: `20`.
- The application permits up to 10 files of 5 MB each. The README recommends PHP limits of `upload_max_filesize = 6M` and `post_max_size = 64M`; the current CLI settings do not meet those recommendations. Apache may use a different `php.ini`, so check the web SAPI separately.

## 3. Issues found and actions taken

1. **Obsolete slideshow dimensions test:** the original test called `slideshow_fit_dimensions()`, which is not defined in the current implementation. The current application validates image dimensions with `getimagesize()` and fits the image in the browser using the existing slideshow stylesheet. The test was rewritten to check those actual implementation rules; it now passes 7/7 static checks.
2. **Outdated documentation:** README and ARCHITECTURE contained earlier Step 7 wording that said administrator approval was not implemented. The current Step 8/9 code and schema do include approval and consent withdrawal. Those descriptions were corrected.
3. **Local secrets and private files in source ZIP:** the supplied ZIP contained a `.env` file and 10 files under private upload storage. The delivery copy removes them and retains `.env.example` and `storage/uploads/.gitkeep`.
4. **Database runtime unavailable:** `pdo_mysql` is missing. This cannot be corrected safely inside the project source; install/enable the extension in the target PHP environment and run the database tests there.

## 4. Unresolved issues and known limitations

- No live MySQL or MariaDB connection was made. Schema import, migration, foreign-key behavior, transactions, and real record consistency remain unverified.
- Visitor registration, returning-visitor identification, upload rejection cases, photo ownership enforcement, administrator workflows, and consent withdrawal were not exercised through a browser.
- Automatic slideshow timing, cycling, newly approved photo discovery, and empty-state behavior were not tested in a real browser or on a TV display.
- Neither Windows XAMPP nor an Ubuntu Apache deployment was tested. Cross-platform compatibility remains unconfirmed.
- The CLI PHP upload limits are too low for the documented 10 × 5 MB upload configuration. Confirm and adjust the Apache/XAMPP PHP settings.
- The default upload directory is inside the project. Configure `UPLOAD_STORAGE_PATH` to a directory outside the web root for stronger protection; if using the in-project default, preserve and verify the Apache deny rules.
- The archive's existing `slideshow.css` is inherited from Step 7 and is still linked by `slideshow.php`. Step 10 did not create or modify CSS or JavaScript. If the final project restriction means that **no CSS file may exist or be used at all**, the current inherited slideshow styling remains a compliance issue; removing it would change the prior slideshow implementation and its image-fit behavior.
- The slideshow uses HTML meta refresh, not a JavaScript-based real-time player. Network and image transfer time can affect the actual display interval.
- The admin photo review list is limited to the latest 200 records; the dashboard does not provide a full moderation-history interface.
- The project has not been load-tested or security-penetration-tested.

## 5. Submission readiness decision

**Not yet confirmed ready for final submission.** The source-level checks listed as PASS succeeded, and the delivery archive was cleaned. However, essential database-backed and target-platform tests remain NOT TESTED. Complete the Windows XAMPP database/browser workflow at minimum, and the Ubuntu workflow if the submission requires both environments. Record the real outcomes here before claiming full application verification.
