# Change: Refactor ccenter to IPF, PHP 8.2–8.4 and ImpressCMS 2.0.4

## Why
The module is a half-finished port of a XOOPS-era code base:
- Two files do not parse on PHP 8.3 (`admin/mymenu.php`, `admin/myformselect.php`).
- Code calls APIs that no longer exist in ImpressCMS 2.0.4 (`xoops_gethandler()`,
  `icms_view_FormSelect`) and relies on PHP 4 constructors (`ListCtrl`, `XoopsBreadcrumbs`)
  that are silently never called on PHP 8.
- `class/Message.php` and `class/MessageHandler.php` are empty stubs; the last commit
  ("move to IPF") only touched two admin scripts, which still build raw SQL.
- Raw SQL with interpolated values, mixed `icms::$xoopsDB` / `icms::$db` usage,
  `FILTER_SANITIZE_STRING` (deprecated in 8.1), undeclared properties (deprecated in 8.2).

## What Changes
- Make the module run warning-free on PHP 8.2, 8.3 and 8.4 under ImpressCMS 2.0.4.
- Introduce IPF objects and handlers for `form`, `message` and `log`, mapped onto the
  **existing** tables (no schema change).
- Replace raw SQL with handlers and `icms_db_criteria_*`.
- Rebuild admin screens on `icms_ipf_view_Table`, `getForm()` and `icms_ipf_Controller`.
- Replace the homegrown group-permission string with IPF permissions
  (one-time, reversible migration).
- Replace `MyPageNav`, `MyFormSelect`, `XoopsBreadcrumbs`, `mymenu.php`, `admin_header.php`
  with core classes (`icms_view_PageNav`, `icms_view_Breadcrumb`, standard admin menu).
- Move form-definition parsing, mailing, attachments and status logic out of `functions.php`
  into testable classes; keep their behaviour.
- **Replace the three ad-hoc text formats with JSON** (stored in the existing text columns):
  form schema (`defs`, now CSV-like DSL), option variables (`optvars`, `key=value`) and message
  body (`body`, `name: value` lines). The legacy DSL stays available as import/export only.
- Add CSRF protection to all state-changing requests.
- Add a Docker-based test matrix (PHP 8.2/8.3/8.4 × ImpressCMS 2.0.4) and static analysis.
- Release as module version 2.0.

**BREAKING (for developers, not for data):**
- Public procedural helpers in `functions.php` are removed or relocated to classes.
- Minimum requirements become PHP 8.2 and ImpressCMS 2.0.4.

## Impact
- Affected specs (all new): `platform-compatibility`, `data-model`, `form-definition`,
  `access-control`, `admin-management`, `frontend-submission`, `install-update`.
- Affected code: essentially every file; `functions.php` is dissolved.
- Out of scope: new features, Smarty 3 template syntax, database schema changes,
  support for ImpressCMS < 2.0.4, core changes (including the `FILTER_SANITIZE_STRING` deprecation in `icms_ipf_view_Table`), `declare(strict_types=1)`.
