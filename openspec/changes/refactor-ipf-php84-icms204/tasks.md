# Tasks

## 0. Test environment (baseline first)
- [x] 0.1 Docker Compose: MariaDB + PHP 8.2/8.3/8.4 (Apache) + ImpressCMS 2.0.4 package
- [x] 0.2 Script to install ImpressCMS and the module non-interactively
- [x] 0.3 Playwright smoke suite for the current flows (record baseline behaviour)
- [x] 0.4 PHPUnit bootstrap, fixtures for `defs`, `optvars` and `body` (legacy formats) from the current implementation
- [x] 0.5 CI workflow (`.github/workflows/ci.yml`): `php -l`, PHPCompatibility 8.2-8.4, PHPUnit, PHPStan (level 1 for now, raise per phase), smoke matrix. NOTE: written but not yet run on GitHub; PHPCompatibility could not be installed in the offline sandbox

## 1. Compatibility baseline (still procedural)
- [x] 1.1 Fix parse errors in `admin/mymenu.php` and `admin/myformselect.php`
- [x] 1.2 Replace `xoops_gethandler`, `FILTER_SANITIZE_STRING` and other removed APIs; use the legacy DB facade `icms::$xoopsDB` consistently (`icms::$db` is the bare PDO object). Raw SQL itself goes away in phase 2
- [x] 1.3 Convert PHP 4 constructors (`ListCtrl`, `XoopsBreadcrumbs`); declare properties
- [x] 1.4 Fix nullable-parameter and null-to-string deprecations found by CI
- [x] 1.5 Replace `global $icmsUser`, `$icmsModule`, `$icmsModuleConfig` (remaining `global $icmsConfig` reads for language/sitename move to phase 2)
- [x] 1.6 Exit criterion: smoke suite green on 8.2/8.3/8.4 with `E_ALL`

## 2. Model layer (IPF)
- [x] 2.1 `Form`, `Message`, `Log` objects (vars on the existing columns; form controls come with the admin rewrite in phase 4)
- [x] 2.2 Handlers; `FormHandler::deleteWithMessages()` cascades to messages, log, attachments, comments and subscriptions
- [x] 2.3 `Json`, `FormSchema` (parse, validate, `items()`), `MessageBody`, `OptionVars`, with unit tests. Widget rendering and POST handling (`assign_form_widgets`, `assign_post_values`, `cc_make_widget`) still live in `functions.php`; they move with the front-end in phase 5
- [x] 2.3a `FormDefinitionImporter` / `Exporter` (round-trip tests; the importer also fixes quoted cells with embedded quotes)
- [x] 2.3b Dual-read of legacy `defs` / `optvars` / `body` / `def_attrs`; writers emit JSON (new submissions, admin save, installer, migration)
- [x] 2.4 `Status`, `StoreMode`, `Mailer`, `Attachments`, `LogHandler::record()`
- [x] 2.5 `PlaceholderResolver` (plain string replacement, no cascading)
- [ ] 2.6 Remove `functions.php` helpers as callers are migrated. Done: `cc_csv_parse`; thin wrappers now: `get_form_attribute`, `eval_user_value`, `unserialize_*`/`serialize_text`, `cc_notify_mail`, `cc_attach_path`, `move_attach_file`, `cc_get_message`, `cc_log_message`, `change_message_status`. Still to go with phases 4 and 5: `ListCtrl`, `CcBreadcrumbs`, widget/POST functions, `get_attr_value`, `cc_display_values`, raw SQL in the list pages and admin lists

## 3. Access control
- [x] 3.1 `form_view` permission (ImpressCMS group permissions, registered with `addPermission`); listings and the form block use `FormHandler::getAccessible()`. `setGrantedObjectsCriteria()` itself is not used: it ignores forms without permission rows, which the fallback for not yet converted forms needs
- [x] 3.2 `AccessPolicy` (view/handle/take over/change status), unit tested; used by `cc_check_perm`, `change_message_status`, `status.php`, `export.php`. The SQL conditions of the staff lists in `reception.php` follow the same rule and move with the front-end in phase 5
- [x] 3.3 CSRF tokens on every POST that changes data (form posting, status/take over/evaluation, form administration, message administration); `status.php` accepts POST only. The only GET that changes something is the automatic "accepted" status when the person in charge opens a new message (kept on purpose)

## 4. Admin
- [x] 4.1 Form admin: list on `icms_ipf_view_Table` (counts per status, sorting, quick search, edit/clone/delete/messages), cloning, deleting with cascade. The editor is the IPF form of the object (`getSecureForm()`): module controls for the definition (lines or JSON, `?format=json`), the option variables and the description, `mod_ccenter_Controller` (posts only what the form has, converts definition and option variables), `form_view` as IPF permission control, contact person from the contact group (`onSelect` reloads), preview as custom button, invalid input refused with a message
- [x] 4.2 Message admin: table with form filter, status combinations (preferences), quick search in the stored text and address, sorting, paging that keeps the filters, bulk status change and delete (with token), detail page with history, delete link with confirmation
- [x] 4.3 Export (CSV) via `MessageHandler::getForExport()`; periods follow the clock of the visitor (`mod_ccenter_Period`, unit tested); columns follow the form definition; an unknown range means everything
- [x] 4.4 `myformselect.php`, `mypagenav.php`, `getusers.php` (AJAX user lookup) and `admin_header.php` are gone; the contact person is a core select with the members of the contact group ("Change" refreshes the list after another group is chosen)
- [x] 4.5 About via `icms_ipf_About`, help page, admin menu through `cc_display_admin_menu()`

## 5. Front-end
- [x] 5.1 `index.php`: listing through `FormHandler::getAccessible()`; storing a submission moved to `mod_ccenter_Submission::store()` (the widget/POST/template functions stay in `functions.php`: moving them is a separate step)
- [x] 5.2 `list.php`, `charge.php`, `reception.php` on `MessageHandler`/`FormHandler` methods (criteria, whitelisted sort keys), `icms_view_PageNav`, `mod_ccenter_ListState` (replaces `ListCtrl`) and `mod_ccenter_Breadcrumbs` (the themes read the `xoops_breadcrumbs` array, so `icms_view_Breadcrumb`, which renders its own HTML, is not used)
- [x] 5.3 `message.php`, `status.php`, `file.php`, `export.php` (no raw SQL left; `is_cc_evaluate` and `cc_check_comment` use handler methods)
- [x] 5.4 Blocks (receipt, form) and notification callback on handler methods. Checked by running the block functions, not by an e2e test (blocks need to be placed on a page)
- [x] 5.5 Templates keep names and variables; the form title in the message lists is now escaped

## 6. Install / update / release
- [ ] 6.1 `icms_version.php` 2.0 manifest, `object_items`, no globals
- [ ] 6.2 `oninstall.php` via handler (sample form as JSON) [done in phase 2]; `onupdate.php` idempotent incl. chunked JSON conversion of `defs`, `optvars`, `body` and the `def_attrs` preference [done in phase 2, `mod_ccenter_Migration`]; `grpperm` migration to group permissions [done in phase 3]; the old schema steps in `onupdate.php` still use string-built SQL
- [ ] 6.3 Language files (the English `def_attrs` description already shows the JSON form; the other languages follow): consistent constant sets, English fallback; remove `language/japanese` (EUC-JP)
- [ ] 6.4 README, release notes, upgrade notes (removed helpers, requirements)
- [ ] 6.5 Full regression on 8.2/8.3/8.4, upgrade test from 1.0 data set

## 7. Decisions (resolved)
- [x] 7.1 No upstream fix for `FILTER_SANITIZE_STRING`
- [x] 7.2 Keep english, german, nederlands, ja_utf8, portuguese; drop EUC-JP `japanese`
- [x] 7.3 Version 2.0
- [x] 7.4 Migrate `grpperm` to IPF permissions (column kept)
- [x] 7.5 No `declare(strict_types=1)`
- [x] 7.6 Everything on JSON, including `def_attrs`
