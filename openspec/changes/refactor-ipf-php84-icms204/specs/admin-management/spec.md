# Admin Management

## ADDED Requirements

### Requirement: Form administration through IPF
Administrators SHALL manage forms (create, edit, clone, activate/deactivate, reorder by weight, delete)
through an `icms_ipf_view_Table` list and an IPF-generated edit form.

#### Scenario: Create a form
- **WHEN** an admin submits the edit form with valid values
- **THEN** a form is created via `icms_ipf_Controller`, a success message is shown and it appears in the list

#### Scenario: Delete a form
- **WHEN** an admin confirms deletion of a form
- **THEN** the form, its messages, its logs, uploaded attachments, related comments and notification
  subscriptions are removed

### Requirement: Message administration through IPF
Administrators SHALL list messages in an `icms_ipf_view_Table` with filters (form, status),
quick search, sorting and pagination, and SHALL be able to change status and delete messages.

#### Scenario: Filter by status
- **WHEN** the admin filters on status "closed"
- **THEN** only closed messages are listed

### Requirement: Export
Administrators SHALL be able to export messages as CSV for the existing ranges (this month,
last month, this year, last year, all) with identical column layout and encoding.

#### Scenario: Export this month
- **WHEN** the admin exports "this month"
- **THEN** a CSV is downloaded containing only messages created in the current month

### Requirement: Standard admin chrome
The admin SHALL use the standard ImpressCMS 2.0 admin menu and header; bespoke
`mymenu.php`, `admin_header.php`, `myformselect.php` and `mypagenav.php` SHALL be removed.
Group-member selection SHALL use a core form element.

#### Scenario: Primary contact selection
- **WHEN** the admin edits a form
- **THEN** the primary contact can be chosen with a core user/group-user element, with no custom classes

### Requirement: Help and About
Help and About pages SHALL use core mechanisms (`icms_ipf_About`).

#### Scenario: About page
- **WHEN** the admin opens About
- **THEN** module credits and version 2.0 are displayed
