# Access Control

## ADDED Requirements

### Requirement: Form visibility via IPF permissions
Which groups may use a form SHALL be stored and checked through `icms_ipf_permission_Handler`
(permission `form_view`), and listings SHALL be filtered using `setGrantedObjectsCriteria()`.

#### Scenario: Anonymous user sees only permitted forms
- **GIVEN** form A is granted to Anonymous and form B only to Registered Users
- **WHEN** an anonymous visitor opens the module index
- **THEN** only form A is listed and `index.php?form=B` is refused with `_NOPERM`

### Requirement: Migration of legacy group permissions
On update, the legacy `grpperm` values (`|gid|gid|`) SHALL be migrated to IPF permissions
idempotently; the column SHALL remain untouched to allow rollback.

#### Scenario: Idempotent migration
- **WHEN** the update routine runs twice
- **THEN** the resulting permission set is identical and no duplicates exist

### Requirement: Staff access
Message handling SHALL be restricted to: module admins, the form's primary contact (`priuid`),
and members of the form's contact group (`cgroup`). Guests SHALL access only their own message
with the matching one-time password.

#### Scenario: Unauthorised message access
- **WHEN** a logged-in user who is neither owner, charge, group member nor admin opens `message.php?id=N`
- **THEN** access is refused

#### Scenario: Guest with one-time password
- **WHEN** a guest opens `message.php?id=N&p=<onepass>` with the correct password
- **THEN** the message is shown; with a wrong password it is refused

### Requirement: CSRF protection
Every state-changing request (submit, status change, evaluate, admin create/edit/delete)
SHALL validate a security token via `icms::$security`.

#### Scenario: Missing token
- **WHEN** a POST arrives without a valid token
- **THEN** it is rejected and no data changes
