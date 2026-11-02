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

### Requirement: Message body compatibility
Messages stored by earlier versions (serialized `body`) SHALL render identically, and new
messages SHALL be stored in the same format.

#### Scenario: Round trip
- **GIVEN** fixture bodies captured from the previous version
- **WHEN** they are parsed and re-serialized by `mod_ccenter_MessageBody`
- **THEN** the output equals the original

### Requirement: Status values
Message status SHALL be one of `-`, `a`, `b`, `c`, `x` and SHALL be defined once (class constants / enum-like
class) with labels from language files.

#### Scenario: Invalid status
- **WHEN** an invalid status is submitted
- **THEN** it is rejected and the stored status is unchanged
