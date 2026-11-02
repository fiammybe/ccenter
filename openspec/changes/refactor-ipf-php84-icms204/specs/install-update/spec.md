# Install and Update

## ADDED Requirements

### Requirement: Module manifest for ImpressCMS 2.0.4
`icms_version.php` SHALL follow ImpressCMS 2.0 conventions: no `global` core variables
(use `icms::$user`), constants from language files, `object_items` for IPF items,
icons, submenu entries only for authenticated users, and version 2.0.

#### Scenario: Module list
- **WHEN** the admin opens the ImpressCMS module list
- **THEN** ccenter appears with version 2.0, icon, and working Admin/Main/Preferences links

### Requirement: Safe installer
`oninstall.php` SHALL create the sample form through the form handler, escaping values via the
connection, without `register_shutdown_function` or global constants defined at runtime.

#### Scenario: Fresh install
- **WHEN** the module is installed
- **THEN** exactly one sample form exists, owned by the installing user, visible to Anonymous and Registered Users

### Requirement: Idempotent updater
`onupdate.php` SHALL bring any previous schema (0.x–1.0) to the current one, using PDO and
no string-built SQL for values, reporting each step in the update message list, and SHALL be safe to run repeatedly.

#### Scenario: Update from 1.0
- **WHEN** the module is updated from 1.0
- **THEN** missing columns/tables are added, legacy permissions are migrated, and a second update changes nothing

### Requirement: Uninstall
Uninstalling SHALL drop module tables (via the standard mechanism), remove permissions, and not leave uploads
referenced by deleted messages unreachable (documented cleanup).

#### Scenario: Uninstall
- **WHEN** the module is uninstalled
- **THEN** its tables and permissions are removed and no PHP errors occur

### Requirement: Languages
Language files for `english`, `german`, `nederlands`, `ja_utf8` and `portuguese` SHALL define the same
set of constants; missing translations SHALL fall back to English.

#### Scenario: Missing translation
- **WHEN** a constant is absent in the selected language
- **THEN** the English text is shown instead of a raw constant name
