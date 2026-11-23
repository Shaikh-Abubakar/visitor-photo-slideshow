# AI Log

This file records how AI was used to build this project, what it produced, what was checked, and
what the student decided. Add a new entry for every development step.

---

## Step 1: Project foundation and database design

| Item | Detail |
|------|--------|
| Date | 2026-11-19 |
| AI tool | Claude (Anthropic), used in the Claude app (claude.ai chat) with a code-execution sandbox. The exact Claude model version used in this original session was not recorded at the time; the student should add it here if known. |
| Prompts given | (1) "Step 1: Project foundation and database design" (role, tasks 1-8, restrictions). (2) "Additional requirement: cross-platform compatibility and scalability" (Windows XAMPP and Ubuntu, portable configuration, scalability rules). |
| Scope | Folder structure, `schema.sql`, `README.md`, `ASSUMPTIONS.md`, `ARCHITECTURE.md`, `.gitignore`, `.env.example`, this log. No application code. |

### What the AI produced

* The folder structure and all files listed above.
* The five-table schema (`visitors`, `consents`, `photos`, `admins`, `settings`).
* Documentation, including the Mermaid relationship diagram and two installation guides.

### Design decisions the AI made (not stated in the prompts)

These are also listed in `ASSUMPTIONS.md`, section B. They should be reviewed and confirmed by the student.

1. Visitor codes are system-generated and stored as an HMAC-SHA-256 hex digest (`CHAR(64)`, unique), keyed with a secret in `.env`, so returning visitors can be found with an indexed lookup.
2. `photos.consent_id` is mandatory, and a composite foreign key `(consent_id, visitor_id)` forces the consent to belong to the same visitor as the photograph.
3. All foreign keys use `ON DELETE RESTRICT` so photo files cannot be orphaned by a cascading delete.
4. `photos.status` is limited to `active` and `hidden` (a `CHECK` constraint, not `ENUM`, for portability).
5. One consent per visitor per consent version (unique key). **Superseded in Correction 1 (2026-11-20):** a new consent record is now created for every upload session, so the unique key was removed (see the Correction 1 entry below).
6. Explicit collation `utf8mb4_unicode_ci` because MySQL 8's default collation does not exist in MariaDB (XAMPP uses MariaDB).
7. `DATETIME` values in UTC; photos paged by `id` rather than by timestamp.
8. Added files not named in the prompts: `.htaccess` deny rules in private folders and the project root, and `.gitkeep` placeholders (Git does not store empty folders).
9. `schema.sql` creates tables in parent-first order (`visitors`, `consents`, `photos`, `admins`, `settings`), which differs from the order in the prompt, because `photos` depends on `consents`.

### Problems the AI found while working

* The required layout puts `config/` and `storage/` inside the web-served folder, while the extra requirement says they must be outside the public directory. Handled with deny rules plus a configurable storage path, not by changing the required layout.
* PHP's default upload limits (2 MB per file, 8 MB per request) are lower than the assumed 5 MB per file and 10 files per request (50 MB). The README documents the required `php.ini` values.
* The "no JavaScript" restriction means the slideshow must advance with an HTML meta-refresh (full page reload per photo). Needs confirmation.
* The `consents` table, as specified, cannot record that consent was withdrawn.
* The brief asks for lower-case file names, but also requires upper-case documentation file names. Documentation files are never loaded by code, so this is safe.

### Verification performed (original Step 1 session, 2026-11-19)

**Who ran these tests:** the AI, in its own sandbox (Ubuntu 24.04). **The student has not run any of them.**
The middle column is what the AI reported at the time. The last column says whether the AI re-ran the check
during Correction 1 (2026-11-20, see the entry below), so it is clear which results have been seen twice.

| Check | Result reported by the AI at the time | Re-run in Correction 1? |
|-------|---------------------------------------|-------------------------|
| Import `schema.sql` into an empty, pre-created database on **MySQL 8.0.46** | Passed; 5 tables, InnoDB, `utf8mb4_unicode_ci` | Yes, on the corrected schema: passed |
| Same import on **MariaDB 10.11.14** | Passed | **No.** MariaDB was excluded from the Correction 1 tests at the student's instruction. Not confirmed by this log's author. |
| Re-import into a populated database | Stops with "Table already exists"; nothing overwritten | Yes: passed |
| Foreign keys | 3 foreign-key constraints present: `fk_consents_visitor`, `fk_photos_visitor`, and the two-column `fk_photos_consent`. *(The original entry said "4 constraints", which was a miscount: the schema has 3 foreign keys, plus 1 `CHECK` constraint.)* | Yes: 3 foreign keys confirmed |
| Rules actually enforced (both servers) | Duplicate visitor code rejected; photo using another visitor's consent rejected; photo without consent rejected (MySQL); duplicate stored filename rejected (MySQL); invalid status rejected; deleting a visitor that has photos rejected | MySQL: yes, all of these were re-tested on the corrected schema. MariaDB: no |
| No `CREATE DATABASE`, `USE`, `DROP` or `INSERT` in `schema.sql` | Confirmed by search | Yes, with comment lines excluded from the search |
| `.gitignore` | Reported as tested with real Git: `.env`, uploaded images, database dumps, logs and OS files ignored; `.env.example`, `schema.sql` and `.gitkeep` files tracked | **No.** The `.gitignore` file was not among the files supplied for Correction 1. |
| No PHP, JavaScript or other code files were created | Reported as confirmed | Yes, for the files changed in Correction 1 |

**Not verified** (the AI could not do these; the student should):

* Importing through the **phpMyAdmin** interface, and anything on **Windows or XAMPP**.
* The Apache virtual-host configuration and permission commands in the README.
* That the Mermaid diagram renders (it uses standard `erDiagram` syntax but was not rendered).
* A dump from MariaDB imported into MySQL 8 (a MariaDB dump was produced but not imported into MySQL).

**Tests performed by the student:** none recorded yet. Record them in the student review below.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected:
* Things I could not explain yet:

---

## Step 1, Correction 1: a consent record for every upload session

| Item | Detail |
|------|--------|
| Date | 2026-11-20 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude mobile app chat, with a code-execution sandbox (Ubuntu 24.04.5, MySQL 8.0.46 server and command-line client; PHP was not used) |
| Prompts given | "Review the existing Step 1 project. Make only the following database and documentation correction." The correction: a separate consent record for every new photo upload session, even when the consent text version has not changed; remove `uq_consents_visitor_version`; keep `uq_consents_id_visitor`; keep the photos-to-consents foreign keys valid; update `ASSUMPTIONS.md`, `README.md`, `ARCHITECTURE.md`; verify the relationships; no PHP functionality; also check this log for the AI tool name, invented test results, and the split between AI and student tests. *(A Step 2 prompt was also supplied earlier in the same conversation. It was set aside; no Step 2 work was done or is included here.)* |
| Scope | `database/schema.sql` (the `consents` table only), `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md`, this log. No application code. |
| Not inspected | `.gitignore`, `.env.example`, the `.htaccess` files and the folder tree were not among the files supplied, so they were neither inspected nor changed. |

### What the AI changed

* `schema.sql`: removed `UNIQUE KEY uq_consents_visitor_version (visitor_id, consent_version)` from `consents`, added an ordinary index `idx_consents_visitor_version` on the same two columns, and updated the comment above the table. Nothing else in the file changed.
* `uq_consents_id_visitor` and all foreign keys were left as they were.
* `ASSUMPTIONS.md` (item B4), `ARCHITECTURE.md` (relationship rules, index table, verification note, upload flow, returning visitors) and `README.md` (description, objective, module table, new consent note and upgrade statement in the database setup section) now describe the new behaviour.
* This log: actual tool name, corrected the foreign-key count, and separated AI tests from student tests.

### Design decisions the AI made (not stated in the instructions)

These should be reviewed and confirmed by the student.

1. **An ordinary index replaces the unique key, instead of simply deleting it.** In a test, deleting only the unique key made MySQL silently create an automatically named index (`fk_consents_visitor`) for the foreign key. An explicit, named index on `(visitor_id, consent_version)` does the same job visibly and also serves the planned lookup of a visitor's consents. It is not a constraint and does not limit duplicates. The student can remove it if it is not wanted; MySQL would then create the automatic one again.
2. **Planned upload flow (`ARCHITECTURE.md`, section 8):** the new `consents` row is inserted in the same transaction as the `photos` rows, so a failed upload leaves no consent row behind. Nothing is built; this is a plan.
3. **README upgrade statement:** one `ALTER TABLE` for anyone who already imported the earlier schema (tested, see below).
4. **"Upload session" is not defined yet.** The documents say a new consent record is made for every upload session; what exactly counts as a session (one form submission, one visit, ...) is left for the upload step.

### Problems the AI found while working

* The original log said "4 constraints" for foreign keys; the schema has 3 (corrected above).
* Some results in the original Step 1 entry (MariaDB, Git and `.gitignore`) could not be reproduced in this correction. They are kept, marked as "reported at the time" and "not re-run".

### Verification performed (Correction 1, 2026-11-20)

**Who ran these tests:** the AI, in its own sandbox: Ubuntu 24.04.5, **MySQL 8.0.46** (`8.0.46-0ubuntu0.24.04.5`), `mysql` command-line client. **The student has not run any of them.** Test rows were inserted only into throwaway databases in the sandbox; `schema.sql` itself still contains no data.

| Check | Actual result |
|-------|---------------|
| Problem reproduced on the original schema | A second consent row for the same visitor and version was rejected: `ERROR 1062 (23000): Duplicate entry '1-v1' for key 'consents.uq_consents_visitor_version'` |
| Effect of only deleting the unique key | MySQL created an automatic non-unique index `fk_consents_visitor` on `visitor_id` (reason for design decision 1) |
| Import the corrected `schema.sql` into an empty, pre-created database | Passed; 5 tables, all InnoDB, `utf8mb4_unicode_ci` |
| Structure compared with the original schema (`SHOW CREATE TABLE`, ignoring the `AUTO_INCREMENT` counter) | `visitors`, `photos`, `admins`, `settings` identical. `consents` differs only by that one index |
| Indexes on `consents` | `PRIMARY (id)`; `uq_consents_id_visitor` UNIQUE `(id, visitor_id)`; `idx_consents_visitor_version` non-unique `(visitor_id, consent_version)`. No unique index contains `consent_version` |
| Foreign keys | 3, all `RESTRICT` on delete and update: `fk_consents_visitor` (`consents.visitor_id` to `visitors.id`), `fk_photos_visitor` (`photos.visitor_id` to `visitors.id`), `fk_photos_consent` (`photos.(consent_id, visitor_id)` to `consents.(id, visitor_id)`). 1 `CHECK` constraint |
| Several consents for one visitor and one version | 3 rows for the same visitor and version `v1` were accepted (plus one for a second visitor) |
| Photos use the consents | A photo for each of two different consents of the same visitor accepted; two photos sharing one consent accepted |
| Rules that must still reject | All rejected as expected: photo using another visitor's consent (error 1452); photo with `NULL` consent (1048); photo with a non-existent consent (1452); consent for a non-existent visitor (1452); duplicate visitor code (1062); duplicate stored filename (1062); invalid status (3819); deleting a consent that photos reference (1451); deleting a visitor that has consents or photos (1451); moving a referenced consent to another visitor (1451) |
| Deliberate deletion in the right order | Deleting photos, then consents, then the visitor worked |
| Total of the behaviour tests above | 20 passed, 0 failed |
| Lookup index | `EXPLAIN` of a visitor-and-version lookup chose `idx_consents_visitor_version` (on an empty table; this shows the index is usable, it is not a performance test) |
| Re-import into a populated database | `ERROR 1050 (42S01): Table 'visitors' already exists`; all 5 tables were still present |
| Content of `schema.sql` | No `CREATE DATABASE`, `USE`, `DROP`, `INSERT`, `GRANT` or `CREATE USER` outside comments; 5 `CREATE TABLE` statements |
| Upgrade statement from the README, on a database made from the old schema (1 consent row) | Dropping the unique key first fails: `ERROR 1553 ... needed in a foreign key constraint`, nothing changed. The single `ALTER TABLE ... ADD KEY ..., DROP INDEX ...` succeeded, the existing row was kept, a second consent with the same visitor and version was then accepted, and the resulting structure was identical to a fresh import |

**Not verified in Correction 1** (by anyone):

* Importing through **phpMyAdmin**, and anything on **Windows or XAMPP**.
* **MariaDB** (excluded at the student's instruction).
* The Apache and permission commands in the README, and whether the Mermaid diagram renders (it was not changed).
* Git and `.gitignore`.

**Tests performed by the student:** none recorded yet.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including design decisions 1 to 4 above):
* Tests I ran myself, where (for example phpMyAdmin on XAMPP), and the results:
* Things I could not explain yet:

---

## Step 2: PHP configuration and MySQL database connection

| Item | Detail |
|------|--------|
| Date | 2026-11-21 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude mobile app chat, with a code-execution sandbox: Ubuntu 24.04.5, PHP 8.3.6 (command line, and mod_php under Apache 2.4.58), MySQL 8.0.46 |
| Prompts given | "STEP 2: PHP CONFIGURATION AND MYSQL DATABASE CONNECTION" (role, tasks 1-9, strict scope), then "Start", with the instruction to create a zip file at the end. The student also said: do not use MariaDB, only MySQL. |
| Scope | `config/config.php`, `config/database.php`, `includes/bootstrap.php`, `tests/check_database.php`, `.env.example`, and updates to `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md` and this log. No visitor registration, sessions, upload, gallery, slideshow or admin code, and no change to the database schema. |
| Not inspected | The AI received only `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md`, `AI_LOG.md` (plus an identical duplicate `AI_LOG_1.md`) and `database/schema.sql`. The real `.gitignore`, `.htaccess` files, `.env.example`, `.gitkeep` files and the folder tree were not available, so the "inspect the complete project folder" task was done only for those files. The AI rebuilt a folder with the same layout for testing. |

### What the AI produced

* `config/config.php` (353 lines): reads settings from real environment variables, then `.env`, then defaults; checks every value; helper functions `app_config()`, `app_path()`, `app_url()`.
* `config/database.php` (117 lines): `db_connection()`, one shared PDO connection with the required options, and safe handling of connection failures.
* `includes/bootstrap.php` (76 lines): common start-up that loads both files, sets error handling and the UTC time zone.
* `tests/check_database.php` (130 lines): command-line check (connect, `SELECT 1`, the five tables); refuses to run from the web.
* `.env.example` rewritten with placeholders only; documentation updated (README Windows and Ubuntu instructions, architecture, assumptions F1 to F15).

### Design decisions the AI made (not stated in the prompt)

All are listed with reasons in `ASSUMPTIONS.md`, section F, and should be reviewed by the student. The main ones:

1. Priority order: real environment variable, then `.env`, then default; an empty value counts as "not set" (F1).
2. `APP_DEBUG=true` is refused when `APP_ENV=production`; `DB_USER` is required and has no default (F3, F4).
3. Default database host `127.0.0.1`, not `localhost` (F5).
4. After connecting, the session time zone is set to UTC and the collation to `utf8mb4_unicode_ci`; the server's `sql_mode` is left alone (F6).
5. The original PDO exception is not chained into the exception that is thrown, so its stack trace (which can hold the connection arguments) cannot leak (see `ARCHITECTURE.md`, section 2).
6. `.env.example` was rewritten from the variable list in `ARCHITECTURE.md`, because the Step 1 copy was not available (F15). The student should compare it with their own copy.

### Problems the AI found while working

* `ARCHITECTURE.md` (Step 1) planned `config.php` and `database.php` in `includes/`; the Step 2 prompt requires `config/database.php`. The files were placed in `config/` and the architecture document was updated.
* For a database that does not exist, MySQL answers a user who has no rights on it with "access denied" (error 1044), not "unknown database" (1049). The error text and the README were written to cover both.
* Several failures during testing were mistakes in the AI's own test scripts (wrong expected text, a shell quoting problem), not defects in the application code; the scripts were corrected and the full set was run again.

### Verification performed (Step 2)

**Who ran these tests:** the AI, in its own sandbox: Ubuntu 24.04.5, PHP 8.3.6, MySQL 8.0.46, Apache 2.4.58. **The student has not run any of them.** MariaDB was not used, at the student's instruction. Test databases, users and passwords were created only in the sandbox. The test scripts themselves are not part of the project.

| Check | Actual result |
|-------|---------------|
| PHP syntax (`php -l`) on the four PHP files | No syntax errors (PHP 8.3.6) |
| Hard-coded paths, file-name case, strict types | No Windows or Ubuntu path in the PHP code (the only matches are one comment and one error-message example); all file names lower case; every PHP file declares `strict_types`; a search found no syntax newer than PHP 8.2 |
| Configuration loading (29 cases, run through the check script) | 29 passed, 0 failed: priority order, defaults, empty values, `.env` with a byte-order mark and CRLF line endings, a password containing `#` and quotes, `APP_ENV_FILE`, and rejection of bad values (port, host with `;`, database name, URL, boolean, environment, debug in production, relative and Windows-style storage path on Ubuntu, file size, file count, control characters, malformed lines) |
| Passwords in output | The test password was searched for in the output of all 29 configuration cases and all connection-failure cases: never found |
| PDO options actually in effect | Exceptions on; associative rows; emulated prepares off (server-side prepares confirmed with a control run that gave 0 when emulation was switched on); session time zone `+00:00`; collation `utf8mb4_unicode_ci`; character sets `utf8mb4`; a bad query raised `PDOException`; a Hindi and emoji string round-tripped |
| Connections per request | A page calling `db_connection()` twice and running queries opened 1 connection; a page that only loads the bootstrap opened 0 |
| Connection failures (each exit code 1, safe message, hint) | Wrong password; wrong user; nonexistent database (error 1044 as a limited user, 1049 as a user with global privileges); closed port; unroutable host; empty database (all five tables reported missing); one table missing (named); `pdo_mysql` not loaded (`php -n`) |
| Uncaught failure with the most revealing PHP settings (`display_errors=1`, `zend.exception_ignore_args=0`) | The output contained no password |
| Check script is read-only | Server counters for insert, update, delete, replace, create, alter, drop and truncate did not change during a run, and table checksums were identical before and after |
| Real Apache 2.4 + mod_php, with the README's virtual host copied verbatim and the README's `chown`/`chmod` commands applied (owner `root`, group `www-data`) | `/.env`, `/.env.example`, `/config/*`, `/includes/*`, `/tests/*` and `/database/schema.sql` all answered 403. A test page connected successfully as `www-data`. Production errors showed only "Something went wrong. Please try again later." (HTTP 500); with debug on the exception text appeared but no password; the Apache error log contained no password |
| Settings supplied only through Apache `SetEnv` (no `.env` file) | A test page connected successfully |
| Check script as the web account (`su` to `www-data`) | All checks passed with `.env` mode 640 (group `www-data`); with mode 600 owned by root it reported "exists but cannot be read" and failed |
| Built-in PHP web server (no Apache rules) | Requests for `tests/check_database.php`, `config/config.php` and `config/database.php` returned 403 with an empty body |
| Windows and Ubuntu path rules | 17 checks passed by passing the Windows separator to the path functions: drive letters, network paths, relative paths, trailing separators, roots. **This simulates the rule; it is not a run on Windows.** |
| Final run on the finished files | Syntax clean; 29 of 29 configuration cases; full check script on MySQL 8.0.46: all `[PASS]`, exit code 0 |

**Not verified** (by anyone):

* Anything on **Windows or XAMPP**, including the Windows instructions in the README, `C:\xampp\php\php.exe`, and the Step 1 `.htaccess` files (they were not supplied; only the Ubuntu virtual-host rules were tested).
* **MariaDB** (excluded at the student's instruction), and XAMPP's bundled database server.
* **PHP 8.2** itself (only 8.3.6 was available; the code avoids newer syntax, which was checked by search, not by running it).
* The **5-second connect timeout**: in the sandbox an unroutable address failed immediately, so the wait was not observed.
* That `.env` is ignored by Git: the real `.gitignore` was not supplied. Run `git check-ignore -v .env` in the repository.
* The README commands on the student's own Ubuntu machine, and phpMyAdmin.

**Tests performed by the student:** none recorded yet.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including the six above and `ASSUMPTIONS.md`, F1 to F15):
* Tests I ran myself, where (for example `tests/check_database.php` on XAMPP), and the results:
* Things I could not explain yet:
