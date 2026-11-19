# AI Log

This file records how AI was used to build this project, what it produced, what was checked, and
what the student decided. Add a new entry for every development step.

---

## Step 1: Project foundation and database design

| Item | Detail |
|------|--------|
| Date | 2026-11-19 |
| AI tool | Claude (Anthropic), chat interface with a file-creation sandbox |
| Prompts given | (1) "Step 1: Project foundation and database design" (role, tasks 1-8, restrictions). (2) "Additional requirement: cross-platform compatibility and scalability" (Windows XAMPP and Ubuntu, portable configuration, scalability rules). |
| Scope | Folder structure, `schema.sql`, `README.md`, `ASSUMPTIONS.md`, `ARCHITECTURE.md`, `.gitignore`, `.env.example`, this log. No application code. |


### Step 1 review correction

A review found that the original `UNIQUE (visitor_id, consent_version)` constraint prevented the same
visitor from recording consent again for a later upload session when the consent wording had not
changed. The corrected schema now uses a non-unique index on `(visitor_id, consent_version)`. The
unique `(id, visitor_id)` key remains because `photos` uses it for the composite foreign key that
ensures a photograph references consent belonging to the same visitor. A new consent row is planned
for every upload session, and all photographs in that session reference that row.

The original verification results below describe the original schema before this correction. They
were not re-run as part of this ZIP correction. The corrected SQL still needs to be imported and
verified in the student's own MySQL/MariaDB environment.

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
5. Initial schema draft allowed only one consent per visitor per consent version. A later review found this conflicted with the requirement to record consent for each upload session; the unique visitor/version constraint was removed and replaced with a non-unique lookup index.
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

### Verification performed

Performed in the AI's sandbox (Ubuntu 24.04):

| Check | Result |
|-------|--------|
| Import `schema.sql` into an empty, pre-created database on **MySQL 8.0.46** | Passed; 5 tables, InnoDB, `utf8mb4_unicode_ci` |
| Same import on **MariaDB 10.11.14** | Passed |
| Re-import into a populated database | Stops with "Table already exists"; nothing overwritten |
| Foreign keys | 4 constraints present: `fk_consents_visitor`, `fk_photos_visitor`, and the two-column `fk_photos_consent` |
| Rules actually enforced (both servers) | Duplicate visitor code rejected; photo using another visitor's consent rejected; photo without consent rejected (MySQL); duplicate stored filename rejected (MySQL); invalid status rejected; deleting a visitor that has photos rejected |
| No `CREATE DATABASE`, `USE`, `DROP` or `INSERT` in `schema.sql` | Confirmed by search |
| `.gitignore` | Tested with real Git: `.env`, uploaded images, database dumps, logs and OS files are ignored; `.env.example`, `schema.sql` and `.gitkeep` files are tracked |
| No PHP, JavaScript or other code files were created | Confirmed |

**Not verified** (the AI could not do these; the student should):

* Importing through the **phpMyAdmin** interface, and anything on **Windows or XAMPP**.
* The Apache virtual-host configuration and permission commands in the README.
* That the Mermaid diagram renders (it uses standard `erDiagram` syntax but was not rendered).
* A dump from MariaDB imported into MySQL 8 (a MariaDB dump was produced but not imported into MySQL).

### Student review (to be completed by the student)

* What I checked and understood:
* What I changed, and why:
* Decisions I accepted or rejected:
* Things I could not explain yet:
