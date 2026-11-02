# Tasks

## 0. Test environment (baseline first)
- [ ] 0.1 Docker Compose: MariaDB + PHP 8.2/8.3/8.4 (Apache) + ImpressCMS 2.0.4 package
- [ ] 0.2 Script to install ImpressCMS and the module non-interactively
- [ ] 0.3 Playwright smoke suite for the current flows (record baseline behaviour)
- [ ] 0.4 PHPUnit bootstrap, fixtures for `defs`, `optvars` and `body` (legacy formats) from the current implementation
- [ ] 0.5 CI: `php -l` matrix, PHPCompatibility 8.2-8.4, PHPStan level 5, smoke suite

## 1. Compatibility baseline (still procedural)
- [ ] 1.1 Fix parse errors in `admin/mymenu.php` and `admin/myformselect.php`
- [ ] 1.2 Replace `xoops_gethandler`, `icms::$xoopsDB`, `FILTER_SANITIZE_STRING`
- [ ] 1.3 Convert PHP 4 constructors (`ListCtrl`, `XoopsBreadcrumbs`); declare properties
- [ ] 1.4 Fix nullable-parameter and null-to-string deprecations found by CI
- [ ] 1.5 Replace `global $icmsUser` and `$GLOBALS['icmsConfig']` usage
- [ ] 1.6 Exit criterion: smoke suite green on 8.2/8.3/8.4 with `E_ALL`

## 2. Model layer (IPF)
- [ ] 2.1 `Form`, `Message`, `Log` objects with vars, controls and validation
- [ ] 2.2 Handlers (call parent constructor), events (before/after insert, delete cascade)
- [ ] 2.3 `mod_ccenter_Json` helper, `FormSchema` (parse/validate/render widgets), `MessageBody`, option variables, with unit tests
- [ ] 2.3a `FormDefinitionImporter` / `Exporter` for the legacy CSV-like DSL (round-trip tests against fixtures)
- [ ] 2.3b Dual-read of legacy `defs` / `optvars` / `body`; writers always emit JSON
- [ ] 2.4 `Status`, `Mailer`, `Attachments`, `Logger` services
- [ ] 2.5 Keep `{X_*}` placeholder substitution (`eval_user_value` is token replacement, not eval) as `PlaceholderResolver`; escape values
- [ ] 2.6 Remove `functions.php` helpers as callers are migrated

## 3. Access control
- [ ] 3.1 Register `form_view` permission, `setGrantedObjectsCriteria` in listings
- [ ] 3.2 Staff/guest access checks in one `AccessPolicy` class
- [ ] 3.3 CSRF tokens on all POST/state-changing GET requests

## 4. Admin
- [ ] 4.1 Form admin: `icms_ipf_view_Table` + `getForm()` + Controller (create/edit/clone/delete); schema edited as JSON with validation, plus "import legacy DSL" and "export legacy DSL"
- [ ] 4.2 Message admin: table with filters, quick search, status actions, delete cascade
- [ ] 4.3 Export (CSV) via handler
- [ ] 4.4 Use core user/group-user element; delete `my*.php` and `admin_header.php`
- [ ] 4.5 Help/About via core; standard admin menu

## 5. Front-end
- [ ] 5.1 `index.php` (listing + submit) on handlers/Controller
- [ ] 5.2 `list.php`, `charge.php`, `reception.php` with `icms_view_PageNav` and `icms_view_Breadcrumb`
- [ ] 5.3 `message.php`, `status.php`, `file.php`, `export.php`
- [ ] 5.4 Blocks (receipt, form) and comment/notification callbacks
- [ ] 5.5 Templates: keep names and variables; fix escaping

## 6. Install / update / release
- [ ] 6.1 `icms_version.php` 2.0 manifest, `object_items`, no globals
- [ ] 6.2 `oninstall.php` via handler (sample form as JSON); `onupdate.php` idempotent incl. `grpperm` migration and chunked JSON conversion of `defs`, `optvars`, `body` and the `def_attrs` preference
- [ ] 6.3 Language files: consistent constant sets, English fallback; remove `language/japanese` (EUC-JP)
- [ ] 6.4 README, release notes, upgrade notes (removed helpers, requirements)
- [ ] 6.5 Full regression on 8.2/8.3/8.4, upgrade test from 1.0 data set

## 7. Decisions (resolved)
- [x] 7.1 No upstream fix for `FILTER_SANITIZE_STRING`
- [x] 7.2 Keep english, german, nederlands, ja_utf8, portuguese; drop EUC-JP `japanese`
- [x] 7.3 Version 2.0
- [x] 7.4 Migrate `grpperm` to IPF permissions (column kept)
- [x] 7.5 No `declare(strict_types=1)`
- [x] 7.6 Everything on JSON, including `def_attrs`
