# Design

## Context
ImpressCMS 2.0.4 requires PHP 7.4+, uses PDO only, bundles Smarty 2, and exposes both
`icms::$db` (PDO) and the legacy-compatible `icms::$xoopsDB`. `icms_getModuleHandler()`
instantiates `mod_<dir>_<Name>Handler` with `icms::$xoopsDB`. IPF derives table names as
`<prefix>_<module>_<item>`.

## Goals / Non-Goals
- Goals: PHP 8.2–8.4 clean (no deprecations from module code), maximal reuse of IPF,
  no data loss, no schema change, testable core logic.
- Non-Goals: new features, UI redesign, Smarty upgrade, core modifications.

## Decisions

### D1. Map IPF items onto existing tables
`new icms_ipf_Handler($db, 'form', 'formid', 'title', 'description', 'ccenter')` resolves to
table `ccenter_form`; likewise `message`/`msgid` and `log`/`logid`.
Alternative (new tables + data migration) rejected: risk without benefit.

### D2. Keep the `body` and `defs` formats
`ccenter_message.body` (serialized text) and `ccenter_form.defs` (line-based DSL) are
modelled as `XOBJ_DTYPE_TXTAREA` with raw access (`getVar('x', 'n')`). Parsing moves to
`mod_ccenter_FormDefinition` and `mod_ccenter_MessageBody`, covered by unit tests built from
fixtures of the current output.

### D3. Database access
Handlers receive the connection from `icms_getModuleHandler()`. Module code never touches
`icms::$xoopsDB` directly; ad-hoc queries (joins for reception counts) use handler methods
with `icms_db_criteria_*`, or `icms::$db` with bound parameters.

### D4. Permissions
Form access moves from `grpperm` (`|1|2|`) to IPF permissions (`form_view`) via
`icms_ipf_permission_Handler`. Migration is done in `onupdate.php`: for every form, parse
`grpperm` and grant matching group permissions; the column is kept (read-only fallback and
rollback), and dropped only in a later release.
Alternative (keep column) rejected as primary design because it blocks use of standard IPF
permission UI and `setGrantedObjectsCriteria()`.

### D5. Admin UI
List screens use `icms_ipf_view_Table` (filters: form, status; quick search; sorting),
edit screens use `getForm()` + `icms_ipf_Controller`. Known caveat: core's `Table.php`
uses `FILTER_SANITIZE_STRING` (deprecated in 8.1) and may emit deprecations we cannot fix
in the module. Decision: accept and document; core is NOT modified by this change.

### D6. Front-end
Entry scripts stay at their current URLs (`index.php?form=ID`, `message.php?id=ID`, ...)
so existing links and notification URLs keep working. Logic moves to handlers/services.

### D7. Templates
Keep Smarty 2 syntax and existing template names/variables so themes and template
overrides keep working. Variable names assigned to templates are treated as a public contract.

### D8. Namespacing / autoload
Class names `mod_ccenter_*` follow the core convention; files are loaded via core autoload
(`icms_getModuleHandler`) with `require_once` in `include/common.php` where autoload does not reach.

### D9. Test strategy
Docker Compose: MariaDB + `php:8.2|8.3|8.4-apache` + ImpressCMS 2.0.4 release package.
A Playwright smoke suite drives the install and the primary flows. PHPUnit covers the
parser/serializer. PHPCompatibility + PHPStan (level 5 initially) run in CI.

## Module structure (target)
```
class/Form.php  FormHandler.php
class/Message.php  MessageHandler.php
class/Log.php  LogHandler.php
class/FormDefinition.php  MessageBody.php  Mailer.php  Attachments.php  Status.php
include/common.php
admin/index.php (forms)  admin/message.php (messages)  admin/about.php
```

## Risks / Trade-offs
- IPF deprecations originating in core (D5) → documented in the release notes; no core change.
- Permission migration is the only data change → idempotent and reversible.
- Procedural helper removal breaks third-party code calling them → documented in release notes.
- PHP 4 constructors never ran on PHP 8: restored behaviour may differ from what users
  currently see; verify against historical behaviour (list sorting, breadcrumbs).

## Migration Plan
1. Release 2.0 contains `onupdate.php` that is safe to run repeatedly.
2. Back up tables (documented), run module update from the ImpressCMS admin.
3. Rollback: re-install previous version; `grpperm` column is intact.

## Decisions (resolved)
1. No upstream fix for `FILTER_SANITIZE_STRING` in impresscms core; deprecations from core `Table.php` are accepted and documented.
2. Language packs: keep `english`, `german`, `nederlands`, `ja_utf8`, `portuguese`; drop EUC-JP `japanese`.
3. Module version is 2.0.
4. Migrate `grpperm` to IPF permissions; the column is kept for rollback.
5. `declare(strict_types=1)` is not used anywhere in the module.
