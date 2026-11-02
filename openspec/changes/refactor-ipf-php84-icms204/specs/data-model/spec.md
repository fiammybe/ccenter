# Data Model

## ADDED Requirements

### Requirement: IPF objects and handlers
The module SHALL provide `mod_ccenter_Form`, `mod_ccenter_Message` and `mod_ccenter_Log`
extending `icms_ipf_Object`, each with a handler extending `icms_ipf_Handler` that calls the
parent constructor and is obtainable through `icms_getModuleHandler()`.

#### Scenario: Handler retrieval
- **WHEN** `icms_getModuleHandler('form', 'ccenter')` is called
- **THEN** a `mod_ccenter_FormHandler` instance is returned and `getObjects()` works against `ccenter_form`

### Requirement: Existing schema is preserved
The IPF mapping SHALL use the existing tables `ccenter_form`, `ccenter_message`, `ccenter_log`
with their current columns and primary keys (`formid`, `msgid`, `logid`). The refactor
SHALL NOT require a schema change.

#### Scenario: Upgrade on existing data
- **GIVEN** a database populated by the previous module version
- **WHEN** the module is updated
- **THEN** all forms, messages and logs are readable through the new handlers with identical values

### Requirement: No raw value interpolation in SQL
All queries that include user- or request-derived values SHALL use criteria objects or bound parameters.

#### Scenario: Hostile input
- **WHEN** a request parameter contains SQL meta-characters (e.g. `1 OR 1=1`, `'; DROP TABLE x;--`)
- **THEN** it is cast, escaped or bound, and no additional rows or side effects occur

### Requirement: JSON message body
New messages SHALL store `body` as versioned JSON: `{"version":1,"fields":[{"key","label","type","value","display"}]}`,
where `value` preserves the raw submitted value (string, array for multi-select, `{"file": name}` for uploads)
and `display` the human-readable text. JSON SHALL be encoded with unescaped unicode and slashes and SHALL fail loudly
on encoding errors.

#### Scenario: Multi-select and multiline values
- **WHEN** a message with a checkbox group and a multiline textarea is stored and read back
- **THEN** the checkbox value is an array of the selected option values and the textarea keeps its exact line breaks

#### Scenario: Label rename does not rewrite history
- **GIVEN** a stored message and a later rename of the form item's label
- **WHEN** the old message is displayed
- **THEN** it shows the label that was current at submission time

### Requirement: Legacy format compatibility and migration
Readers SHALL accept both JSON and legacy text (`name: value` body, CSV-like `defs`, `key=value` `optvars`)
by format detection. The updater SHALL convert stored data to JSON in chunks and idempotently. Legacy message
bodies SHALL be converted with `value = display = original text` and `legacy = true`.

#### Scenario: Legacy body displayed
- **GIVEN** a body stored by the previous version
- **WHEN** it is displayed before or after migration
- **THEN** the same label/value pairs are shown

#### Scenario: Re-running the migration
- **WHEN** the migration runs a second time
- **THEN** already-converted rows are skipped and nothing changes

#### Scenario: Interrupted migration
- **WHEN** the migration stops half-way
- **THEN** the module still works (dual-read) and a re-run continues where it stopped

### Requirement: Searchable JSON
The message admin search SHALL keep matching on submitted text.

#### Scenario: Accented search term
- **GIVEN** a message containing "café"
- **WHEN** an admin searches "café"
- **THEN** the message is found (the body is stored without `\uXXXX` escaping)

### Requirement: Status values
Message status SHALL be one of `-`, `a`, `b`, `c`, `x` and SHALL be defined once (class constants / enum-like
class) with labels from language files.

#### Scenario: Invalid status
- **WHEN** an invalid status is submitted
- **THEN** it is rejected and the stored status is unchanged
