# Tests

| What | Command | Needs |
|------|---------|-------|
| Syntax on PHP 8.2/8.3/8.4 | `tests/lint.sh` | Docker images built by `setup.sh` |
| Unit tests (legacy formats) | `phpunit` or `composer test` | PHPUnit 9.6+ |
| Static analysis | `ICMS_HTDOCS=/path/to/impresscms/htdocs php tools/phpstan.phar analyse` or `composer stan` | PHPStan 2 |
| End-to-end smoke tests | see below | Docker, Node 22 |

## End-to-end

```bash
export ICMS_SRC=/path/to/impresscms          # checkout of ImpressCMS 2.0.4 (contains htdocs/)
cd tests/e2e && npm ci && cd ..              # Playwright (or point NODE_PATH at a global install)
tests/setup.sh 8.3                           # stack on http://localhost:8083, ImpressCMS installed, ccenter installed
cd e2e && BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 npm test
tests/teardown.sh 8.3
```

`setup.sh` copies ImpressCMS into `tests/.work/<php>/`, runs the installer in a headless browser
(`e2e/install.js`) and mounts this repository read-only as `modules/ccenter`.
Ports are `808<minor>` (8082, 8083, 8084), compose projects `ccenter-82`, `ccenter-83`, `ccenter-84`.

### What the smoke tests verify
Install, module update (twice), anonymous submit with confirm step and one-time password,
validation, admin forms/messages/help/about with a **cold** admin-menu cache, create/delete forms,
status workflow with logging, staff lists, evaluation, access control for outsiders, comments,
CSV export, deleting messages.

The last test fails when module code **or its compiled Smarty templates** logged any PHP warning,
notice or deprecation. To make that possible the test stack differs from a production site in three ways
(all applied to the *copy* of ImpressCMS in `tests/.work`, never to this repository):

* ImpressCMS debug mode is on (otherwise it silences PHP diagnostics),
* `Logger::handleError()` additionally forwards diagnostics to the PHP error log,
* the Protector preload is removed after the installation (its flood protection answers rapid test traffic with blank pages; installing the Protector module puts the preload back, hence "after").

Template files are stored in the database; after changing a template run the module update
(the smoke suite does this first) before judging results.

## Data tests and the upgrade test
* `tests/e2e/data.test.js` (run together with the smoke tests by `npm test`) covers how forms, options and messages are
  stored as JSON, the migration of data written by earlier versions, the delete cascade and the file download.
* `tests/e2e/security.test.js` covers access (group permissions, conversion of the old access text, an ordinary member versus the person in charge, contact group, guests) and the CSRF protection of every state-changing request. It creates a plain member `tester` (a copy of the administrator row, same password).
* `OLD_REF=<commit> ICMS_SRC=... tests/upgrade-test.sh 8.3` installs the module as it was at `OLD_REF`, creates a form and
  messages with that code, switches to the code in the working tree, runs the module update and checks that everything is
  converted, still shows the same and that a second update changes nothing. Use the phase 1 commit (`beab8da`) or a release tag.

## Unit tests
`tests/unit` pins the behaviour of the legacy text formats (CSV-like form definitions, `key=value`
option variables, `name: value` message bodies) using fixtures in `tests/unit/fixtures`.
The golden file `defs_all_types.expected.json` was generated with `UPDATE_GOLDEN=1 phpunit` after phase 1.
These tests are the safety net for the planned JSON migration.

## Things worth knowing when a test fails mysteriously
* A POST that ends in a blank page with HTTP 200 is almost always a failed token check or Protector, not the module.
* ImpressCMS keeps sessions in the database without locking; do not leave tabs open that keep making requests
  (the smoke tests close every page they open) and do not use a separate HTTP client for an authenticated session
  (form tokens are bound to the User-Agent).
