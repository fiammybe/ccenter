# Form Definition

## ADDED Requirements

### Requirement: JSON form schema
A form definition SHALL be stored as a versioned JSON document (`{"version":1,"items":[...]}`) in
`ccenter_form.defs`, with items having a unique stable `key`, `type`, `label`, optional `required`,
`attrs`, `options` (`value`, `label`, `default`), `default` and `comment`. Parsing, validation and widget
rendering SHALL be provided by classes (`mod_ccenter_FormSchema` and helpers), not procedural globals.

#### Scenario: Valid schema
- **WHEN** a schema with unique keys and known types is saved
- **THEN** it is stored as JSON and rendered with the same widgets as the previous implementation

#### Scenario: Invalid schema
- **WHEN** the schema is not valid JSON, has duplicate keys or an unknown type
- **THEN** saving is refused with a message naming the offending item

### Requirement: Legacy DSL import and export
The previous CSV-like definition syntax SHALL be importable (admin action and migration) and
exportable, producing a semantically identical schema/definition.

#### Scenario: Import the shipped sample
- **GIVEN** the sample definition of the previous version (`Your name*,size=40\nEmail*,mail,size=60\n...`)
- **WHEN** it is imported
- **THEN** the resulting items have the same keys, types, required flags, attrs, options and defaults as the previous parser produced

#### Scenario: Round trip
- **WHEN** a schema produced by import is exported to the legacy DSL and imported again
- **THEN** the schema is equal to the first one

### Requirement: JSON option variables
Per-form option variables (`optvars`) and the module default attributes (`def_attrs`) SHALL be JSON objects;
legacy `key=value` text SHALL still be read and converted on migration.

#### Scenario: Override defaults
- **GIVEN** `def_attrs` = `{"size": 60}` and a form `optvars` = `{"size": 40}`
- **WHEN** an item without explicit size is rendered
- **THEN** its size is 40

### Requirement: Supported widget types are unchanged
The engine SHALL continue to support the existing item types (text, textarea, radio, checkbox,
select, mail, file, hidden/const, numeric, tel, and others currently handled) including the
"etc" free-text option for radio/checkbox.

#### Scenario: Checkbox with "etc"
- **WHEN** a checkbox item includes the `*` option and the user fills in the free text
- **THEN** the free text is stored and displayed as in the previous version

### Requirement: Server-side validation
Required, numeric, tel, mail and file constraints SHALL be validated server-side and reported
per field in the existing `errors` template variable.

#### Scenario: Missing required field
- **WHEN** a required item is empty on submit
- **THEN** the form is redisplayed with an error for that item and nothing is stored

### Requirement: Custom templates
Forms with a custom template (`custom` = block/full/none-html) SHALL continue to render
via the existing template mechanism and the `ccenter_form_widgets.html` widget template.

#### Scenario: Full custom template
- **WHEN** a form uses `_CC_TPL_FULL`
- **THEN** the template is rendered without the site frame, as before

### Requirement: Placeholder substitution
Default values MAY contain `{X_<USERFIELD>}` placeholders (e.g. `{X_UNAME}`), which SHALL be replaced
with the current user's value (empty for guests). Substitution SHALL be plain string replacement; no
user-supplied string is ever evaluated as code.

#### Scenario: Registered user
- **WHEN** a logged-in user opens a form whose item default is `{X_UNAME}`
- **THEN** the field is pre-filled with their user name, HTML-escaped on output

#### Scenario: Guest
- **WHEN** a guest opens the same form
- **THEN** the field is empty
