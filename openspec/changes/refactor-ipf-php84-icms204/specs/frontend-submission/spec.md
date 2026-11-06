# Frontend Submission

## ADDED Requirements

### Requirement: Stable URLs
Front-end entry points (`index.php?form=`, `list.php`, `charge.php`, `reception.php`,
`message.php?id=`, `status.php`, `file.php`, `export.php`) SHALL keep their URLs and parameters
so existing links, bookmarks and notification mails continue to work.

#### Scenario: Old notification link
- **WHEN** a link from a mail sent by the previous version is opened
- **THEN** the corresponding message is displayed

### Requirement: Form submission flow
Submitting a form SHALL follow form → (optional confirm) → store, and on store SHALL: persist the
message according to `store` mode (log only / store / never store), send the auto-reply to the submitter
when an email is present, notify the person in charge or the contact group, trigger `global` and `form`
`new` notifications, subscribe the submitter and the person in charge to message comments, and redirect.

#### Scenario: Registered user submits a stored form
- **WHEN** a logged-in user submits a valid form with store mode "store"
- **THEN** a message with status `-` is created, mails are sent to the configured recipients and the user is redirected to the message

#### Scenario: Guest submits
- **WHEN** a guest submits a valid form
- **THEN** a one-time password is generated, included in the mailed message URL, and stored with the message

#### Scenario: Store mode "never"
- **WHEN** the form's store mode is "never store"
- **THEN** no message row is written and the mail still contains the submitted values

### Requirement: Attachments
Uploaded files SHALL be validated (size, type), stored under the module's upload directory
per message, served only through `file.php` with access checks, and removed when the message is deleted.

#### Scenario: Unauthorised download
- **WHEN** a user without access requests `file.php` for a message attachment
- **THEN** the file is not served

### Requirement: Message handling by staff
Authorised staff SHALL be able to accept, reply to, close and (admin) delete messages;
the first view by the person in charge SHALL change status `-` to `a`; each change SHALL be logged
and the submitter notified per the `status` notification event.

#### Scenario: First view
- **WHEN** the person in charge opens a new message
- **THEN** its status becomes `a` and a log entry is written

### Requirement: Evaluation and comments
Submitters SHALL be able to evaluate a closed message once (value + comment), and ImpressCMS
comments on a message SHALL update status/notifications through the existing comment callbacks.

#### Scenario: Evaluate
- **WHEN** the submitter posts an evaluation with a valid token or one-time password
- **THEN** value and comment are stored and cannot be changed afterwards

### Requirement: Blocks
The "receipt" block and the "form" block SHALL keep their options and output, be clonable,
and use handlers rather than raw SQL.

#### Scenario: Receipt block
- **WHEN** a staff member views a page with the receipt block configured for statuses `-` and `a`
- **THEN** only their (or unassigned) matching messages are listed, ordered as configured
