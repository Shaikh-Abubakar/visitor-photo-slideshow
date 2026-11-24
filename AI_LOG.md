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

---

## Step 3: Visitor registration

| Item | Detail |
|------|--------|
| Date | 2026-11-24 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude app chat with a code-execution sandbox: Ubuntu 24.04, PHP 8.3.6 (command line, the built-in web server, and mod_php under Apache 2.4.58), **MariaDB 10.11.14** (first) and then **MySQL 8.0.46** (the sandbox had neither installed; the AI installed them for these tests) |
| Prompts given | "Implement Step 3: Visitor Registration" (role, context, strict technology restriction: PHP 8 and MySQL/MariaDB only, no JavaScript, CSS or frameworks; instructions 1-7; testing requirements 1-8; completion report; stop condition: no returning-visitor identification, uploads, gallery, slideshow, admin or Step 4). |
| Scope | New: `register.php`, `includes/security.php`, `includes/visitors.php`. Updated: `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md`, this log. **No change** to `database/schema.sql`, `config/config.php`, `config/database.php`, `includes/bootstrap.php` or `tests/check_database.php` (confirmed byte for byte against the supplied copies). |
| Not inspected | The AI received nine files (the four documents, `config.php`, `database.php`, `bootstrap.php`, `check_database.php`, `schema.sql`), **not** the project ZIP the prompt mentions. `.env.example`, `.gitignore`, the `.htaccess` files and the folder tree were not available. The AI rebuilt the folder layout described in `README.md` for testing, so the new files must be copied into the student's real project. |

### What the AI produced

* `register.php` (about 230 lines): the form, the request handling (CSRF check, validation, insert, Post-Redirect-Get) and the result page. All HTML is written by PHP; no CSS, no JavaScript, no separate HTML file.
* `includes/security.php`: secure session start, CSRF token functions, security headers, `html_escape()`.
* `includes/visitors.php`: `visitor_name_validate()`, `visitor_code_generate()`, `visitor_code_hash()`, `visitor_register()` (prepared `INSERT`, collision retry).
* Documentation: README Step 3 section (XAMPP and Ubuntu test steps, phpMyAdmin verification), architecture (flow, new files), assumptions G1-G16 and open question E4.

The AI **inspected `schema.sql`** before writing database code and confirmed the `visitors` columns: `id BIGINT UNSIGNED AUTO_INCREMENT`, `name VARCHAR(100) NOT NULL`, `visitor_code_hash CHAR(64) NOT NULL` with `UNIQUE KEY uq_visitors_code_hash`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`. It also checked the imported table with `SHOW COLUMNS` and `SHOW INDEX`. No schema change was needed.

### Design decisions the AI made (not stated in the prompt)

All are listed with reasons in `ASSUMPTIONS.md`, section G. The student should review them, above all the first:

1. **Plain SHA-256 instead of the HMAC of Step 1 (G1).** The Step 3 prompt says "SHA-256"; `ARCHITECTURE.md` and `ASSUMPTIONS.md` (B2) from Step 1 said HMAC-SHA-256 with a secret `VISITOR_CODE_PEPPER`, which the Step 2 loader does not read. The AI followed the Step 3 prompt and wrote the difference into the documents. **This needs the student's decision before real visitors register** (`ASSUMPTIONS.md`, E4).
2. Code format: 160 random bits from `random_bytes()`, Base32, 8 groups of 4 (G2); the hash is taken over the code without hyphens and in capitals (G3).
3. Name rules: 2 to 100 characters, letters/space/hyphen/apostrophe/full stop only (G4). These may refuse some real names.
4. Collision handling by the database's unique key plus retry, not by looking the hash up first (G5).
5. The result stays in the session for 15 minutes, so the code can be refreshed (G6); this means the plaintext code sits in the server's session file for that time (G7).
6. Single-use CSRF token; stale or double submissions are redirected to the pending result (G8).
7. Two helper files beyond the `register.php` named in the prompt (G13).

### Problems and mistakes (honest account)

* **A real defect in the AI's code, found by its own test.** After a successful registration the first version called `session_regenerate_id(true)`. A test that sent two simultaneous `POST` requests from one session (a double click) created **two visitors** (`rows=2`). Cause: the second request was already waiting for the old session file; renewing the id deleted that file, so the waiting request read the old data (old token still valid, no result yet) and registered again. Correction: the call and the helper function were removed (registering gives no extra rights, so renewing the id was not needed), and the token rotation is now written to the same session the waiting request reads. After the fix the test passed on 5 repeated runs (built-in server) and 5 runs with 4 simultaneous requests each (Apache), on both database servers.
* **Mistakes in the AI's test scripts (not in the project code):** (1) one case meant to send invalid UTF-8 first sent a valid name by accident, so the next request with the old token was correctly redirected and the test reported a failure (61 of 62 passed); the script was corrected and 62 of 62 passed. (2) A check for the host name in a failed response also looked at the response headers, which contain the `Host` header that the test itself sent; it was restricted to the page body.
* **Conflict between documents:** SHA-256 (Step 3 prompt) against HMAC (Step 1 design). See decision 1.
* **Observation on an earlier step:** when `includes/bootstrap.php` is requested directly on the PHP built-in server, which has no folder deny rules, it answers 200 with an empty body (it runs the bootstrap and prints nothing). Under Apache with the README's virtual host it answered 403. It is a Step 2 file and was left unchanged; the deny rules in the README are what protect it.
* Sandbox set-up problems (the database server stopped when the first shell ended; a `pkill` pattern matched its own shell; MySQL needed a re-initialised data directory) were environment issues, not project defects.

### Verification performed (Step 3)

**Who ran these tests:** the AI, in its own sandbox (above). **The student has not run any of them.** The tests used scripted HTTP clients and `php` on the command line, **not a real web browser**. The test scripts are not part of the project. All test visitor rows were deleted afterwards. Unless stated, each suite was run on **both MariaDB 10.11.14 and MySQL 8.0.46** and on the final code; all results below are the final results of those runs.

| Check | Actual result |
|-------|---------------|
| PHP syntax (`php -l`) on all PHP files | No syntax errors (PHP 8.3.6) |
| Logic suite (`visitors.php`, 51 checks) | 51 passed, 0 failed, on both servers: Base32 encoder against all 7 RFC 4648 test vectors; 20,000 generated codes all in the expected format and all different, all 32 symbols evenly used; hash is 64 lower-case hex and equals `sha256` of the code without hyphens; lower-case/no-hyphen forms give the same hash; 10 names accepted (including Devanagari, accents, apostrophes) and 16 rejected; `NULL`, array and int input rejected; invalid UTF-8 rejected |
| Collision retry (forced with a test generator) | A generator that returned an existing code twice, then a new one: 3 attempts, one new row, the first visitor untouched. A generator that always returned the same code: gave up after exactly 5 attempts, stored no row, the exception message contained neither code nor hash. A different database error (name too long, error 1406) was **not** swallowed as a collision |
| Prepared statement | A name containing `'); DROP TABLE visitors;--` (sent past the validator on purpose) was stored literally and the table still existed |
| HTTP suite, 62 checks, PHP built-in server **and** Apache 2.4.58 + mod_php (README virtual host and permission commands, running as `www-data`) | 62 of 62 passed on each, on both database servers |
| Test 1-4 (brief): valid name, row inserted, hash not code, code displayed | `POST` answered 303 to `register.php?registered=1` with an empty body; the result page showed the success text and a code in the format `XXXX-XXXX-...` (39 characters); one new row; name stored with extra spaces removed; the stored hash was 64 hex characters and equal to SHA-256 of the code without hyphens; a **full database dump** contained the code neither with nor without hyphens; the hash was not on the page |
| Test 5: refresh | 3 refreshes of the result page and a fresh open of `register.php` showed the **same code**; row count unchanged. Re-sending the old form (stale token) and sending a valid token while a result was pending both created no row and redirected to the result |
| Double click | 2 simultaneous `POST`s from one session (built-in server, 5 runs) and 4 (Apache, 5 runs): exactly **1** row each time (after the correction above; the first version produced 2) |
| 20 simultaneous visitors | 20 different sessions registered at once: 20 rows, 20 different codes, 20 different hashes |
| Test 6: validation | Rejected with HTTP 422 and no row: empty, spaces only, tabs/newlines, one letter, digits, leading symbol, `<b>Bob</b>`, `<script>`, 101 characters, SQL text, control character, missing field, name sent as an array, invalid UTF-8. 100 characters accepted. Hostile input was shown escaped (`&lt;b&gt;`) when the form was redisplayed. Devanagari, accented, apostrophe/hyphen/full-stop names were accepted and stored unchanged |
| Test 7: CSRF | Wrong, missing, empty, array-valued token, and a real token of another session without a cookie: all HTTP 403 and no row. The form shown with the 403 carried a working token. The README's literal `curl` command returned `HTTP/1.1 403 Forbidden` on the built-in server and on Apache, and added no row |
| Other requests | `PUT` and `DELETE` gave 405 with an `Allow` header; unknown action 400; result URL without a session showed the form with an explanatory notice and no code; a result aged by 901 seconds (the session file was edited) was no longer shown |
| Failure handling | Wrong database password and unreachable database server: HTTP 500, generic message, form shown again with the typed name, no row; the response contained no password, user name, SQL, class name or file path; the server log held the technical reason but not the password |
| Production mode (`APP_ENV=production`) | Registration worked; no PHP warnings or notices in the output |
| Session cookie | `HttpOnly; SameSite=Lax` on http; with `APP_URL=https://...` the `Secure` flag was added (the header was inspected; no real HTTPS connection was made) |
| HTML | Form, result and error pages: tags balanced (checked with Python's HTML parser); no `<script>`, `<style>`, `style=`, image or external resource in the output |
| Private files over HTTP (Apache, README rules) | `/.env`, `/config/config.php`, `/includes/visitors.php`, `/includes/security.php`, `/includes/bootstrap.php`, `/tests/check_database.php` and `/database/schema.sql` all answered 403; the Apache logs contained no database password |
| Test 8: Steps 1 and 2 still work | `php tests/check_database.php`: every line `[PASS]`, exit code 0, on both servers. The five existing PHP/SQL files and the four documents were byte-identical to the supplied copies before the documents were edited. `schema.sql` imported into a fresh database (5 tables). The check script answered 403 with an empty body when requested over HTTP |
| phpMyAdmin query documented in the README | The `SELECT ... WHERE visitor_code_hash = SHA2(REPLACE('<code>','-',''),256)` query returned the registered visitor (hash length 64); the same query with a wrong code returned no row. **Run on MySQL 8.0.46 only.** |

**Not verified** (by anyone):

* Anything on **Windows, XAMPP or phpMyAdmin**, including XAMPP's bundled MariaDB and its PHP version and the Windows steps written in the README.
* Behaviour in a **real web browser** (the Back button, how cookies are handled, how the pages look). The README's browser tests (test 5 with Back, test 7 option B) have not been performed by anyone.
* **PHP 8.2** (only 8.3.6 was available; the code uses nothing newer by reading, not by running).
* A real **HTTPS** connection. The `SHA2()` query on **MariaDB** (it exists there, but it was not run). Windows' session locking (the double-click protection was tested with PHP's default file sessions on Linux only). Custom session handlers. Load.
* The `.htaccess` files and `.gitignore` (not supplied).

**Tests performed by the student:** none recorded yet.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including the seven above and `ASSUMPTIONS.md`, G1 to G16; especially G1, SHA-256 or HMAC):
* Tests I ran myself, where (for example `register.php` on XAMPP and phpMyAdmin), and the results:
* Things I could not explain yet:

---

## Step 4: Returning visitor identification

| Item | Detail |
|------|--------|
| Date | 2026-11-24 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude app chat with a code-execution sandbox. **The sandbox had no PHP, no MySQL/MariaDB, no web server and no network access, so none of the code could be run.** |
| Prompts given | "Implement Step 4: Returning Visitor Identification" (role, context, strict technology restriction: PHP 8 and MySQL/MariaDB only, no JavaScript, CSS or frameworks; instructions 1-8; testing requirements 1-11; completion report; stop condition: no uploads, gallery, slideshow, admin or Step 5). The project ZIP was supplied with the prompt. |
| Scope | New: `identify.php`, `includes/visitor_auth.php`, `tests/check_identify.php`. Updated: `README.md`, this log. **Not changed:** `register.php`, `includes/bootstrap.php`, `includes/security.php`, `includes/visitors.php`, `config/*`, `database/schema.sql`, `tests/check_database.php`, `.env`, `.env.example`, `ASSUMPTIONS.md`, `ARCHITECTURE.md`. |
| Inspected first | The whole ZIP was unpacked and read before writing code: `includes/visitors.php` (Step 3 code format and hashing), `includes/security.php` (session, CSRF, headers), `includes/bootstrap.php`, `config/*`, `register.php`, `database/schema.sql`, `README.md`, `AI_LOG.md`, `ASSUMPTIONS.md`. |

### What the AI produced

* `includes/visitor_auth.php`: `visitor_find_by_code()` (prepared `SELECT id, name ... WHERE visitor_code_hash = ?`, using Step 3's `visitor_code_hash()` unchanged), `visitor_login()` (session id regenerated, only id, name and time stored), `visitor_current()`, `visitor_is_authenticated()`, `visitor_require_login()`, `visitor_logout()`, and a wrong-code throttle (session counter plus a per-address counter file in the system temp folder).
* `identify.php`: form, CSRF check, throttle check, lookup, Post-Redirect-Get for success and for failure, welcome page, logout button. HTML written by PHP only.
* `tests/check_identify.php`: a command-line logic check that runs in a transaction and rolls it back.
* README: a Step 4 section (how it works, XAMPP and Ubuntu steps, 14 tests with an empty "Your result" column, phpMyAdmin queries, common problems).

### How the Step 3 format was followed

The code format and the hash rule were not re-implemented: Step 3's `visitor_code_normalize()` (capitals, no hyphens or white space) and `visitor_code_hash()` (SHA-256, 64 lower-case hex characters) are called as they are. The only new rule is a shape check before the database is touched: after normalisation a code must be exactly 32 characters from `A-Z2-7`, which is what 20 random bytes in Base32 always give (Step 3, `VISITOR_CODE_BYTES`). `visitor_register()` is not called by any page of Step 4.

### Design decisions the AI made (not stated in the prompt)

The student should review these.

1. **Failures also use Post-Redirect-Get** (message kept in the session for one page view), not only success, so refreshing never re-posts a wrong code.
2. **Two throttle counters, no table:** 5 wrong codes per session and 30 per client address, in a 15-minute window. The address counter is a file in `sys_get_temp_dir()` because a session counter alone is defeated by deleting the cookie. The limits (5, 30, 15 minutes) are my choice. Behind a shared address (a college lab, or everyone testing on `127.0.0.1`) 30 wrong codes lock that address for 15 minutes. While locked, the code is not looked up.
3. **A successful identification does not reset the address counter**, so one valid code cannot be used to reset it.
4. **Identification lasts 2 hours** and is not re-checked against the database on every request.
5. **An identified visitor must press "End my session" before identifying as someone else**; a second identification while identified is ignored.
6. **Logout empties the whole session and replaces the id** (`session_regenerate_id(true)`) instead of destroying and restarting it. This also removes a pending registration result (which holds a plaintext code) from the session.
7. **The welcome page shows the visitor number** (the database `id`) so it can be compared with phpMyAdmin. It never shows the hash.
8. **The pending registration result of Step 3 is left alone** by identification (it still expires after 15 minutes, or on logout).
9. **A bounded `tests/check_identify.php` was added** although the prompt did not name it, so the logic part of the tests can be run on the student's machine with one command.
10. **The session id is regenerated here, unlike in Step 3** (where it caused a double-click problem). A double click on **Identify me** may therefore run the identification twice; this is harmless because it only repeats "set the same identity" and inserts nothing. This reasoning has **not** been tested.

### Problems and mistakes (honest account)

* **No test could be run.** PHP, MySQL and MariaDB were not installed in the sandbox and could not be installed (no network). Not even `php -l` (syntax check) was possible. The only check made was a crude count of matching brackets in the three new PHP files, which says nothing about correctness. A typo or a logic error may still be present; the first run on the student's machine is the first real test.
* **The prompt's testing requirements 1-11 and the "Which tests actually passed" part of the completion report therefore cannot be answered with results.** Nothing is claimed as passed.
* **Stray files in the supplied ZIP:** the project root contains `visitors.php` and `security.php`, byte-identical to `includes/visitors.php` and `includes/security.php`. Nothing loads them (they exit with 403 if requested directly). They were left untouched.
* **Step 3's `register.php` defines `redirect_to()`, `page_start()` and `page_end()` as global functions inside the page,** so they could not be reused without editing it. Step 4 has its own `visitor_auth_redirect()` and `identify_page_*()` functions with different names, so a future page may load both without a name clash.
* **Step 1/3 documents still say HMAC with `VISITOR_CODE_PEPPER` in places** (`ASSUMPTIONS.md` B2, `ARCHITECTURE.md`); Step 3 decided on plain SHA-256 (G1) and Step 4 follows that. The open question E4 (SHA-256 or HMAC) is still undecided and matters more now: changing it after visitors exist invalidates every code.

### Verification performed (Step 4)

| Check | Result |
|-------|--------|
| Any PHP syntax check, any execution of the new code, any database test, any browser test | **Not performed.** No PHP or database in the sandbox. |
| Bracket count (`{ } ( ) [ ]`) in `identify.php`, `includes/visitor_auth.php`, `tests/check_identify.php` | Each file has equal numbers of opening and closing brackets (crude text check on stripped text only). |
| Code read-through against the requirements | Done by reading: the code is hashed in PHP and only the hash is queried; the query is a prepared statement; the code is not echoed into the page, not written to the session, not logged; session id regenerated on login; CSRF checked before anything else; output escaped with `html_escape()` (`htmlspecialchars`); paths built with `__DIR__`; no database credentials in the new files; no schema change. This is a review, not a test. |

**Not verified** (by anyone): everything that runs, including all of the testing requirements 1-11 of the prompt; the Windows, XAMPP and phpMyAdmin steps; the throttle file locking on Windows; behaviour in a real browser.

**Tests performed by the student:** none recorded yet. Record them in the "Your result" column of the README table and below.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including the ten above):
* Tests I ran myself, where (for example `identify.php` on XAMPP and phpMyAdmin, and `tests/check_identify.php`), and the results:
* Things I could not explain yet:

---

## Step 5: Secure multiple photo uploads

| Item | Detail |
|------|--------|
| Date | 2026-11-24 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude app chat with a code-execution sandbox. **The sandbox had no PHP, no MySQL/MariaDB, no web server and no network access, so none of the code could be run.** Python with Pillow was available and was used only to create the three tiny test images embedded in `tests/check_upload.php` (and to confirm that Pillow can re-open them). |
| Prompts given | "Implement Step 5: Secure Multiple Photo Uploads" (role, context, strict technology restriction, instructions 1-10, testing requirements 1-14, completion report, stop condition: no gallery, slideshow, admin dashboard or photo management, no Step 6). No ZIP was attached to this prompt; the AI continued from the project folder of Step 4 in the same conversation (which includes the Step 4 files written by the AI, also never run). |
| Scope | New: `upload.php`, `includes/uploads.php`, `tests/check_upload.php`. Changed: `config/config.php` (one block: reads `UPLOAD_ALLOWED_MIME_TYPES`), `.env.example` (comment and position of that setting), `README.md`, `ASSUMPTIONS.md` (section H), `ARCHITECTURE.md` (sections 7 and 8, one line in section 10), this log. **Not changed:** `register.php`, `identify.php`, `includes/bootstrap.php`, `includes/security.php`, `includes/visitors.php`, `includes/visitor_auth.php`, `config/database.php`, `database/schema.sql`, `.env`, `tests/check_database.php`, `tests/check_identify.php`. |

### Schema inspected before writing code (by reading `database/schema.sql`)

* `photos`: `id`, `visitor_id`, `consent_id` (NOT NULL), `stored_filename VARCHAR(64) UNIQUE`, `original_filename VARCHAR(255)`, `mime_type VARCHAR(50)`, `file_size INT UNSIGNED`, `status VARCHAR(20) DEFAULT 'active'` with `CHECK (status IN ('active','hidden'))`, `uploaded_at DEFAULT CURRENT_TIMESTAMP`. Foreign keys: `fk_photos_visitor`, and the composite `fk_photos_consent (consent_id, visitor_id)` that forces the consent to belong to the same visitor.
* `consents`: `id`, `visitor_id`, `consent_version VARCHAR(20)`, `consented_at DEFAULT CURRENT_TIMESTAMP`; **no** unique key on `(visitor_id, consent_version)` (a new row per upload session is allowed), `uq_consents_id_visitor` for the composite key.
* Result: every column the upload needs exists. **No schema change was made or needed.** `tests/check_upload.php` re-checks the column lists and the two foreign keys of `photos` against the real database.

### What the AI produced

* `includes/uploads.php`: validation (PHP error code, `is_uploaded_file`, size, Fileinfo MIME, extension for the type, `getimagesize`), cleaning of the original name, random stored names (`random_bytes(24)`, 48 hex characters), path building that accepts only that name shape, storage folder check, and `upload_process()` (move files, one transaction with a new `consents` row and the `photos` rows, deletion of the stored files on any failure).
* `upload.php`: login check first, then request size, CSRF, consent, files; Post-Redirect-Get; result shown once.
* `tests/check_upload.php`: a command-line check that exercises `upload_process()` for tests 1-9 and 13 of the brief (rolled back; temporary folder deleted).
* Documentation: README (Step 5 section with Windows and Ubuntu private-storage set-up, 16 browser tests with an empty "Your result" column, phpMyAdmin queries), `ASSUMPTIONS.md` H1-H13, `ARCHITECTURE.md`.

### Design decisions the AI made (not stated in the prompt)

All are listed with reasons in `ASSUMPTIONS.md`, section H. The student should review them, above all:

1. **Initial status `active`** (H6): the schema defines `active` as "eligible for the slideshow", consent is mandatory, and there is no approval step. The brief says photographs must not become public "merely because they were uploaded"; nothing shows photographs yet, but the **later slideshow step must itself check consent and status.** If an approval step is wanted, change `UPLOAD_INITIAL_STATUS` to `hidden`.
2. **One upload session = one form submission = one new `consents` row** (H5), created only if at least one photograph is stored.
3. **More than 10 files rejects the whole submission, a bad single file rejects only itself** (H3); the accepted files are then stored all together or not at all (H4).
4. **Extra checks beyond the brief:** the extension must fit the detected type, and `getimagesize()` must confirm a real image (H2).
5. **The default storage folder stays inside the project** (H9) so the XAMPP quick start works; the README recommends a folder outside the web root and the code logs a warning when the folder is inside the project. The brief says not to rely on `.htaccess` alone; with the default folder the application still does, until the student sets `UPLOAD_STORAGE_PATH`.
6. **`config/config.php` was changed** (one block) so `UPLOAD_ALLOWED_MIME_TYPES` is really read; before this step it was only listed in `.env.example`.
7. **Stored files sit in a sub-folder named after the first two characters of the name** (as `ARCHITECTURE.md` section 7 planned); the architecture text was corrected from 32 to 48 hex characters.

### Problems and mistakes (honest account)

* **No test could be run.** PHP, MySQL and MariaDB were not installed and could not be installed (no network). Not even `php -l` was possible. Only a crude count of matching brackets in the new and changed PHP files was made, which says nothing about correctness. The new files may contain a typo or a logic error.
* **The testing requirements 1-14 of the prompt and "Which tests actually passed" therefore have no results.** Nothing is claimed as passed. `tests/check_upload.php` was written to be run by the student; it has itself never been run, so it may contain mistakes (a failing line may point at the test, not at the application).
* **A test that could not fail was written and then removed** while reviewing `tests/check_upload.php`: one check ended in `|| true`, so it would always pass. It now has a real condition.
* **Not testable from the command line, so only by hand:** login redirect, CSRF rejection, missing consent, the 413 "too large" case, and everything in a real browser (README tests 1, 10-14). The consent check lives in `upload.php`, not in `upload_process()`.
* **PHP limits:** `upload_max_filesize`, `post_max_size` and `max_file_uploads` in `php.ini` apply before the application runs. If `max_file_uploads` is below the application limit, PHP silently drops extra files, and the application cannot see that. The README says so.
* **Step 4 is still unrun** (see the Step 4 entry), and Step 5 depends on it: if identification fails, uploads cannot be tested.
* **The command line may use another `php.ini` than Apache,** so `tests/check_upload.php` can report different limits than the web server uses.

### Verification performed (Step 5)

| Check | Result |
|-------|--------|
| Any PHP syntax check, any execution of the new code, any database test, any browser test | **Not performed.** No PHP or database in the sandbox. |
| Bracket count (`{ } ( ) [ ]`) in `upload.php`, `includes/uploads.php`, `tests/check_upload.php`, `config/config.php` | Equal numbers of opening and closing brackets in the three new files. For `config/config.php` the crude check reports unequal numbers **both in the supplied original and after my edit, with identical counts** (the check is fooled by quote characters in that file's strings), so it shows only that my added block is balanced, not that the file is. Crude text check only. |
| Test images | Created with Pillow (JPEG, PNG, WebP, GIF, 4 x 4 pixels), re-opened with Pillow successfully, embedded as base64. **Not checked with PHP's `finfo` or `getimagesize`.** |
| Schema read-through | Done by reading `schema.sql`: see the section above. |
| Code read-through against the requirements | Done by reading: authentication before anything else, the visitor id taken only from the session, no visitor field in the form, CSRF and consent before files, random names from `random_bytes`, original name never used in a path, prepared statements only, one transaction, files deleted on failure, output escaped with `html_escape()`, no `chmod 777`, no paths or database errors shown, paths built with `DIRECTORY_SEPARATOR`. This is a review, not a test. |

**Not verified** (by anyone): everything that runs, including all of the testing requirements 1-14; Windows, XAMPP and phpMyAdmin steps; `icacls` and `chmod` commands; PHP's behaviour with real browser uploads; WebP detection by the Fileinfo database of the student's PHP build.

**Tests performed by the student:** none recorded yet. Record them in the "Your result" column of the README table and below.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including the seven above and `ASSUMPTIONS.md`, H1 to H13):
* Tests I ran myself, where (for example `upload.php` on XAMPP, phpMyAdmin, `tests/check_upload.php`), and the results:
* Things I could not explain yet:

---

## Step 6: Visitor photo gallery

| Item | Detail |
|------|--------|
| Date | 2026-11-24 |
| AI tool | Claude Sonnet 5.5 (Anthropic), used in the Claude app chat with a code-execution sandbox. **Unlike Steps 4 and 5, this sandbox allowed installing PHP 8.3.6 (command line) and MariaDB 10.11.14 from the Ubuntu repositories, so the code was actually run.** The sandbox is Linux; PHP's built-in web server and `curl` were used instead of Apache and a browser. |
| Prompts given | "Implement Step 6: Visitor Photo Gallery" (role, context, strict technology restriction, instructions 1-8, testing requirements 1-14, completion report, stop condition: no slideshow, no administrator pages, no Step 7). The project ZIP (uploaded as `visitor-photo-slideshow__2_.zip`) was attached; its files were inspected before any change. |
| Scope | New: `gallery.php`, `photo.php`, `includes/gallery.php`, `tests/check_gallery.php`. Changed: `includes/security.php` (`send_security_headers()` got an optional parameter, default `false`, that adds `img-src 'self'`), `upload.php` and `identify.php` (one link line each), `README.md`, `ARCHITECTURE.md`, `ASSUMPTIONS.md` (section I), this log. **Not changed:** `register.php`, `config/*`, `includes/bootstrap.php`, `includes/visitors.php`, `includes/visitor_auth.php`, `includes/uploads.php`, `database/schema.sql`, `.env`, `tests/check_database.php`, `tests/check_identify.php`, `tests/check_upload.php`, and the files in `storage/uploads`. |

### Project and schema inspected before writing code

* Read: `database/schema.sql`, `config/config.php`, `config/database.php`, `includes/bootstrap.php`, `includes/security.php`, `includes/visitor_auth.php`, `includes/uploads.php`, `includes/visitors.php`, `upload.php`, `identify.php`, the three test scripts, `.env.example`, `storage/.htaccess`, and the documentation. `.env` was not printed or changed; the tests used environment variables instead.
* `photos`: `id`, `visitor_id`, `consent_id` (NOT NULL), `stored_filename` (UNIQUE), `original_filename`, `mime_type`, `file_size`, `status VARCHAR(20)` with `CHECK (status IN ('active','hidden'))`, `uploaded_at`; index `idx_photos_visitor (visitor_id)`; composite foreign key `fk_photos_consent (consent_id, visitor_id)` to `consents (id, visitor_id)`.
* `consents`: `id`, `visitor_id`, `consent_version`, `consented_at`.
* **Finding:** the schema has no field that separates "visitor may see their own photograph" from "approved for the public slideshow", and no `deleted` or `rejected` status. As the brief asks, the limitation is explained (`ASSUMPTIONS.md`, I1-I3) and the safest behaviour was implemented without inventing a field: only own, `active` photographs with a matching consent record are shown.
* **Finding:** `send_security_headers()` (Step 3) sends `Content-Security-Policy: default-src 'none'`, which blocks every `<img>`. The gallery cannot show a photograph under it, so the function got one optional parameter. Nothing else in that policy was loosened, and no other page changed behaviour.

### What the AI produced

* `includes/gallery.php`: positive-integer validation (`\z`, at most 15 digits), page arithmetic, three prepared queries (count, one page, one photograph) that share the same visibility rule, `gallery_resolve_file()` (generated-name shape, `realpath()`, inside the real storage folder, regular readable file) and `gallery_detect_mime()` (Fileinfo).
* `gallery.php`: login check, page validation and clamping, table of photographs, Previous/Next links, upload link, POST logout form with CSRF token (reusing the Step 4 logout in `identify.php`), a neutral message on a technical failure.
* `photo.php`: the seven ordered checks, then `readfile()` with `Content-Type`, `Content-Length`, `nosniff`, `no-store`, a sandboxing CSP, `Cross-Origin-Resource-Policy` and a generated file name. The session is closed before sending.
* `tests/check_gallery.php`: 64 checks (validation, visibility, ownership, pagination arithmetic and real pages, path safety including a symbolic link, a PHP file named `.png`, a directory with a photograph's name); rolled back; temporary folder deleted.

### Design decisions the AI made (not stated in the prompt)

All are listed with reasons in `ASSUMPTIONS.md`, section I. The student should review them, above all:

1. **Hidden photographs are not shown to their owner (I2).** Safer than showing them, but a visitor cannot see a photograph that an administrator or a database edit hid.
2. **Consent is "a consent record exists for that visitor"; its version is not compared with the current text (I3).** If the consent wording changes, someone must decide what happens to old consents.
3. **404 for everything that is not a clean success (I5)**, so photo numbers cannot be probed. 401 (not a redirect) for an unidentified request to `photo.php`.
4. **`no-store` for photographs (I8):** safer on shared computers, more traffic.
5. **No thumbnails (I9):** the browser scales full-size files; a page of 20 large photographs is heavy. `loading="lazy"` is used.
6. **Existing files were touched in three places (I10):** the optional header parameter and two link lines.

### Problems and mistakes (honest account)

* **A real bug was found by `tests/check_gallery.php` and fixed:** the number check used `$` in a regular expression; in PHP `$` also matches before a final line break, so `"1"` followed by a line break was accepted as a valid number. Harmless in practice (it cast to 1), but wrong. It now uses `\z`, and the check passes. The same loose `$` exists in the Step 5 function `upload_stored_path()`; it was **not** changed (out of scope), and it cannot be exploited by the gallery because the stored name comes from the database and a name with a line break would not resolve to a file.
* **Several of my own test lines failed at first, for four reasons, because the test was wrong and not the application:** a "no stored file name in the page" pattern matched part of the 64-character CSRF token; a hostile `</script>` file name had already been cut down by the Step 5 name cleaning (everything before the last `/` is dropped), so it never reached the gallery; a hostile visitor name was refused by the Step 3 name rules (so it can never reach the database through the application); and a quoting mistake in one script line. Each was corrected and the checks were re-run; none of them hid an application fault.
* **An early sandbox slip:** the first test run found no web server because the background processes ended with the shell; they were restarted and the whole run was repeated.
* **Browser behaviour was not observed.** `curl` does not apply a `Content-Security-Policy`. That the thumbnails display under `img-src 'self'`, and that links and the button look right, is checked only by reading the header and the generated HTML.

### Verification performed (Step 6)

| Check | Result |
|-------|--------|
| `php -l` on every PHP file of the project | No syntax errors |
| `tests/check_gallery.php` (PHP 8.3.6, MariaDB 10.11.14) | `RESULT: PASS - 64 passed, 0 failed` (after the `\z` fix; the first run had 1 failure, the newline case) |
| `tests/check_database.php`, `tests/check_identify.php`, `tests/check_upload.php` (Steps 2, 4, 5) | PASS; 22 and 42 checks passed, 0 failed |
| Live flow over HTTP (`php -S`, `curl`): register 5 visitors, identify, 5 upload submissions, gallery, `photo.php`, logout | All behaved as described in the README table *What was actually tested* (testing requirements 1-14 each executed; hidden, damaged-consent, missing-file, fake-image and path-traversal cases simulated by editing the sandbox database or files and then restoring them) |
| Real photograph bytes | The bytes sent by `photo.php` were identical to the uploaded files (PNG, JPEG, WebP) |

**Not verified** (by anyone): Windows, XAMPP, Apache `.htaccess` behaviour, Ubuntu with Apache, phpMyAdmin steps, any real browser (including how images render under the Content-Security-Policy), and the `icacls`/`chmod` commands of earlier steps.

**Tests performed by the student:** none recorded yet. Record them in the "Your result" column of the README table and below.

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected (including the six above and `ASSUMPTIONS.md`, I1 to I12):
* Tests I ran myself, where (for example `gallery.php` on XAMPP, phpMyAdmin, `tests/check_gallery.php`), and the results:
* Things I could not explain yet:

