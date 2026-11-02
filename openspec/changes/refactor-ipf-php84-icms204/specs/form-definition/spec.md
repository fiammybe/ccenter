# Form Definition

## ADDED Requirements

### Requirement: Definition DSL engine as a class
Parsing of the `defs` DSL, option-variable resolution (`optvars`, module `def_attrs`),
validation and widget rendering SHALL be provided by `mod_ccenter_FormDefinition` (and helpers),
not by procedural global functions.

#### Scenario: Parse a sample definition
- **GIVEN** the sample form definition shipped with the module
- **WHEN** it is parsed
- **THEN** items with the same names, types, labels, options and attributes as the previous implementation are produced

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

### Requirement: Safe evaluation
User-provided option/attribute strings SHALL NOT be evaluated as code. The legacy `eval_user_value()`
behaviour SHALL be replaced with a whitelist-based expression resolver.

#### Scenario: Malicious value
- **WHEN** an option value contains PHP code
- **THEN** it is treated as literal text and never executed
