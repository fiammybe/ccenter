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

### D2. JSON persistence (replaces CSV-like / ad-hoc text formats)
Today three home-grown formats are parsed with regexes (`cc_csv_parse`, `unserialize_vars`,
`unserialize_text`). They have real defects: comma *and* tab as delimiters, quoting rules that
differ per format, uninitialised variables/references in `unserialize_text`, and lossy message
bodies (checkbox arrays are flattened to `"a, b"`, only the option *label* is stored, not its value,
and newlines are encoded with a leading tab). All three become JSON, stored in the existing
`text` columns (no DDL change; works identically on MySQL and MariaDB).

**Form schema** (`ccenter_form.defs`):
```json
{"version": 1, "items": [
  {"key": "Your name", "type": "text", "label": "Your name", "required": true,
   "attrs": {"size": 40}, "default": "{X_UNAME}"},
  {"key": "About", "type": "radio", "label": "About", "required": true,
   "options": [{"value": "Site contents", "label": "Site contents", "default": true},
               {"value": "Others", "label": "Others"}]},
  {"type": "comment", "text": "Fields marked * are required"}
]}
```
- `key` is the stable identifier. Migrated items use the legacy name verbatim so existing message
  bodies still map; new items get a generated slug. Keys are unique per form.
- `{X_<USERFIELD>}` placeholders (current `eval_user_value()`, which is token substitution, **not** `eval`)
  are kept in `default`.

**Option variables** (`ccenter_form.optvars` and module preference `def_attrs`):
a JSON object, e.g. `{"notify_with_email": 1, "redirect": "/thanks.php"}`.

**Message body** (`ccenter_message.body`), self-describing so history survives schema edits:
```json
{"version": 1, "fields": [
  {"key": "Your name", "label": "Your name", "type": "text", "value": "Jan", "display": "Jan"},
  {"key": "Topics", "label": "Topics", "type": "checkbox",
   "value": ["a", "b"], "display": "Site contents, Others"},
  {"key": "CV", "label": "CV", "type": "file", "value": {"file": "cv.pdf"}}
]}
```
- `value` is the raw submitted value (string | array | `{"file": name}`), `display` the text shown/mailed/exported.
- Legacy bodies are converted to `{"key": legacyName, "label": legacyName, "type": null,
  "value": text, "display": text, "legacy": true}` because the old format only kept display text.
- Encode with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR` so SQL `LIKE`
  search in message admin keeps working on readable text.

**Access in IPF**: IPF has no JSON data type (`XOBJ_DTYPE_ARRAY` is PHP `serialize`, which we explicitly avoid).
Columns are declared as `XOBJ_DTYPE_TXTAREA` (raw access, `'n'` format) and the object exposes
typed accessors (`getSchema()`, `setSchema()`, `getFields()`) built on a small `mod_ccenter_Json` helper.
Risk: IPF sanitizing must not mangle JSON (quotes/HTML entities) → covered by round-trip tests.

**Compatibility**: readers detect the format by first non-space character (`{`/`[` → JSON, else legacy)
and always read both. Writers always write JSON. The DSL parser stays as `FormDefinitionImporter`
(import in admin, one-time migration) and `FormDefinitionExporter` (export legacy DSL = rollback path).
CSV export of messages (`export.php`) is an *export* format and stays CSV.

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
- Data changes are the permission migration (D4) and JSON conversion (D2) → idempotent; legacy readers stay in place; DSL exporter is the rollback path for schemas.
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
6. All persisted structured data is JSON, including the `def_attrs` module preference (legacy `key=value` text is read and converted on update). The legacy CSV-like DSL survives only as admin import/export.

## Findings from phase 2 (implementation notes)
- **IPF column types.** Text columns are declared `XOBJ_DTYPE_OTHER`. `XOBJ_DTYPE_TXTBOX` censors and strips, and
  `XOBJ_DTYPE_TXTAREA` runs the value through the HTML purifier, which would damage JSON and the raw form texts.
- **`object_items` is not set** in `icms_version.php`. With it ImpressCMS runs `moduleUpgrade()` on install/update, which
  derives table changes from the IPF object definitions; the schema is deliberately left alone.
- **Criteria do not escape values** (`icms_db_criteria_Item` wraps the value in quotes as is, and drops the condition when the
  value is empty). Handlers therefore cast ids to int and only pass fixed patterns; user text never goes into a criteria value.
- **Confirm step.** The hidden fields of the confirmation page carried the *labels* of checked boxes, so the keys were lost
  before storing. They now carry the option values; the stored JSON has `value` (keys) and `display` (labels).
- **`notify_with_email` default.** ImpressCMS appends `<!-- filtered with htmlpurifier -->` markers to textarea preferences. The
  stored default `notify_with_email=0` therefore read as the truthy text `0<!-- ... -->`, i.e. the visitor's address was always
  shown/stored. The markers are now stripped, so the documented default (0 = off) applies. The address stays available in the
  message itself and in the CSV export either way.
- **Security fixes made on the way:** `file.php` accepted path elements in the file name (path traversal) and served any file type inline;
  "take over message" (`status.php op=myself`) did not check the contact group; free text typed after "other" was shown unescaped.
- **Visible differences after the conversion:** a submitted message no longer shows the "required" mark behind the labels; it shows
  the label stored with the message. The upgrade test checks everything else is identical.

## Findings from phase 3 (implementation notes)
- **Permissions.** `form_view` lives in the ImpressCMS group permissions (item id = form id). The `grpperm` column is kept and written
  in step (ids sorted), so a downgrade still works. A form without any permission row is read from `grpperm`; this makes the module usable
  in the window between replacing the files and running the module update, and an interrupted migration harmless. Webmasters (group 1)
  are not special-cased by the listing query, only by `checkRight()`.
- **Criteria and the `IN`/`NOT IN` operators** take a pre-built list, so ids are cast to int before they are joined.
- **Tokens.** A token is created for every rendered form and consumed by the check. Forms built with templates need `<{$form.token}>`
  (form, confirm) or `<{$token}>` (message page). Custom form templates get it through `{SUBMIT}`. **A site that overrides the module
  templates in the theme or in the database must add the token field to its copies**; without it posting a form shows the "form has expired"
  message. Blocks that show a form must not be cached by ImpressCMS (the token is per visitor).
- **Status changes.** Previously `status.php` let only the person in charge change a status, but the admin list called the same function
  with "any message"; the two cases are now explicit (`canChangeStatus` / `canAdminChangeStatus`).
- **Message administration** validated nothing: any status text and any user id could be stored. It now accepts only the five statuses and
  existing users.

## Findings from phase 4 (implementation notes)
- **`icms_ipf_view_Table` builds `ORDER BY` from the request without checking the column**; the quick search and the second filter put
  their value into the query as typed (only HTML-escaped). `mod_ccenter_AdminTable::guardRequest()` cleans sort column, direction, numeric
  filter values and the page size before the table reads them; a test sends `SLEEP()` payloads.
- **The table template of the system module has no security token** on its bulk action form, and its "check all" box looks for another
  name than the check boxes have. The module ships `ccenter_admin_table.html` (a copy with the token inside the form and a working check-all);
  bulk controls come in through the table footer.
- **`icms_ipf_Controller::handleObjectDeletion()` deletes on `confirm=1` without checking a token.** Deleting forms and messages uses
  `icms_core_Message::confirm()` plus `cc_check_token()` instead; the IPF edit/delete links of the table are kept (the handlers' `_page`
  points at the administration scripts).
- **`FormHandler::delete()` / `MessageHandler::delete()` cascade** (messages, log, permissions, comments, subscriptions, files), so any
  caller, including IPF's own, cleans up.
- **Removed:** the AJAX contact person lookup. A form that is created for a new contact group needs one "Change" click to list its members.
- **Language:** new texts (`_AM_CC_*`) have English fallbacks in the code; the language files get them in phase 6.

## Findings from the IPF edit form (phase 4b)
- **Extension points used:** module-defined controls (`class/form/elements/ccenter<control>element.php`, found by `setControl(['name'=>...])`), `setControl(['object'=>$this,'method'=>...])` for the contact person, `onSelect=>'submit'` (`op=changedField`) for the contact group, `addCustomButton()` for the preview, `addPermission('form_view')` for the access groups, `doStoreFromDefaultForm(..., null)` to get the object back without a redirect, and a `Controller` subclass.
- **Gaps in IPF handled in the module:** `Controller::postDataToObject()` overwrites variables that are not posted; the controller checks no token (`cc_check_token()` first); `Handler::insert()` ignores errors set on the object, so `FormHandler::beforeSave()` refuses to store when `hasError()`; `saveItem_Permissions` fails when no group is checked (`$_POST['form_view']` is defaulted to an empty array); the permission rows are written after the insert, so the `grpperm` column is mirrored afterwards (`syncLegacyColumn()`).
- **Changed names in the form:** access groups `form_view[]` (was `grpperm[]`), save button `create_button`/`modify_button` (was `formdefs`), the "Change" button is gone. The FCKeditor option was dropped (its files do not exist in 2.0.4).
