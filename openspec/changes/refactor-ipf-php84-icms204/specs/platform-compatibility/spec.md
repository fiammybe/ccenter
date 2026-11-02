# Platform Compatibility

## ADDED Requirements

### Requirement: Supported PHP versions
The module SHALL run on PHP 8.2, 8.3 and 8.4 without parse errors, fatal errors,
warnings, notices or deprecations originating from module code.

#### Scenario: Syntax check on every supported version
- **WHEN** `php -l` is run on every `.php` file in the module under PHP 8.2, 8.3 and 8.4
- **THEN** every file reports no syntax errors

#### Scenario: Deprecation-free request cycle
- **WHEN** the primary user and admin flows are executed with `error_reporting = E_ALL`
- **THEN** no `E_DEPRECATED`, `E_WARNING` or `E_NOTICE` is logged for files inside the module directory

### Requirement: Supported ImpressCMS version
The module SHALL declare and enforce ImpressCMS 2.0.4 as minimum version and SHALL NOT
call any API that was removed in ImpressCMS 2.0.4.

#### Scenario: Installation on ImpressCMS 2.0.4
- **WHEN** the module is installed from the ImpressCMS 2.0.4 module administration
- **THEN** installation succeeds, tables exist, the sample form is created and blocks/templates are registered

#### Scenario: Removed API is not used
- **WHEN** the code base is searched for `xoops_gethandler`, `icms_view_FormSelect`, `xoopsDB`,
  `FILTER_SANITIZE_STRING`, and PHP 4 style constructors
- **THEN** no occurrence remains

### Requirement: Modern language usage
Module classes SHALL declare all properties, use `__construct` constructors, use typed
parameters/returns where possible, and mark nullable parameters explicitly.

#### Scenario: Static analysis
- **WHEN** PHPStan (initial level 5) and PHPCompatibility (`8.2-8.4`) are run in CI
- **THEN** both complete without errors

### Requirement: Automated verification matrix
The repository SHALL provide a reproducible Docker-based test setup covering PHP 8.2, 8.3 and 8.4
with ImpressCMS 2.0.4 and MariaDB.

#### Scenario: CI matrix
- **WHEN** CI runs for a change
- **THEN** the smoke suite runs once per PHP version and all must pass
