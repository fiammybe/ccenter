# Project Context

## Purpose
Contact Center (`ccenter`) is an ImpressCMS module for creating and managing web forms
("Contact us" style). It stores submissions, sends confirmation mails, and lets staff
accept, reply to, evaluate and close submissions.

## Tech Stack
- PHP 8.2, 8.3, 8.4 (target; legacy code base originated on PHP 5)
- ImpressCMS 2.0.4 (PDO-only database layer, bundled Smarty 2, `<{ }>` template delimiters)
- ImpressCMS Persistable Framework (IPF): `icms_ipf_Object`, `icms_ipf_Handler`,
  `icms_ipf_Controller`, `icms_ipf_view_Table`, `icms_ipf_permission_Handler`
- MySQL / MariaDB

## Project Conventions

### Code Style
- PSR-12 formatting. `declare(strict_types=1)` is NOT used (core and IPF rely on loose scalar coercion); use parameter/return types without strict mode.
- Classes live in `class/` and follow the ImpressCMS module convention
  `mod_ccenter_<Name>` / `mod_ccenter_<Name>Handler`, file `class/<Name>.php` and `class/<Name>Handler.php`.
- Language constants: `_MI_CCENTER_*` (modinfo), `_AM_CCENTER_*` (admin), `_MD_CCENTER_*` (main), `_CO_CCENTER_*` (common).
- No `global` access to core objects; use `icms::$user`, `icms::$module`, `icms::$db`, `icms::handler()`.

### Architecture Patterns
- Persisted entities are IPF objects with an IPF handler; no raw SQL string building for values.
- Business logic (form definition parsing, mail, attachments, status transitions) lives in
  dedicated classes, not in procedural `functions.php`.
- Entry-point scripts (`index.php`, `message.php`, ...) are thin controllers.

### Testing Strategy
- `php -l`, PHPCompatibility (`8.2-8.4`) and PHPStan run in CI on every change.
- End-to-end smoke tests (Playwright) against ImpressCMS 2.0.4 + MariaDB in Docker,
  matrix over PHP 8.2 / 8.3 / 8.4.
- Pure-PHP unit tests (PHPUnit) for the form-definition engine.

### Git Workflow
- Feature branches, small commits, no history rewrite on shared branches.

## Domain Context
- **Form**: definition (title, DSL `defs`, accepting groups, primary contact, contact group,
  store mode, custom template, option variables).
- **Message**: one submission against a form, with status `-` (new), `a` (accepted), `b` (replied),
  `c` (closed), `x` (deleted), plus evaluation value/comment.
- **Log**: history of actions per form/message.
- Guests can access their own message through a one-time password (`onepass`).

## Important Constraints
- Existing installations and data must keep working: database schema is **not** changed
  by the refactor (tables `ccenter_form`, `ccenter_message`, `ccenter_log`).
- The serialized `body` format of stored messages stays backward compatible.
- Must not depend on any API removed in ImpressCMS 2.0.4
  (`xoops_gethandler`, `icms_view_FormSelect`, MySQL (non-PDO) connection type, ...).

## External Dependencies
- ImpressCMS core 2.0.4 (`htdocs/libraries/icms/**`)
- ImpressCMS notification, comment and mail subsystems
