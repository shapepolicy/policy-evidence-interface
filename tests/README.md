# Tests

PHPUnit tests for the Policy Evidence Interface module. They test the code as it is and do not change any module code.

## Layout

| Path | Contents |
| --- | --- |
| `src/Unit/` | Tests that run without a Drupal site: the rate limiter, tool and resource definitions, and node ID checks |
| `src/Kernel/` | Tests that run against a small Drupal site in the test database: the tools, resources, `/_mcp` handler, OAuth discovery endpoints, `drush mcp:server` handling, settings form and module install |
| `src/Kernel/PolicyEvidenceKernelTestBase.php` | Shared set-up: an `article` type with `body`, `field_tags` and `field_pdf_article`, a `page` type without them, and the tag `restricted` in the disallowed list |
| `fixtures/` | Small PDFs: `three-pages.pdf` (one sentence per page), `numbers-only.pdf` (digits only) and `broken.pdf` (not a complete PDF) |
| `modules/pei_test/` | Hidden test-only module that denies viewing nodes titled "Private …"; used by two known-issue tests |

## Test groups

- `policy_evidence_interface`: the normal tests. They must all pass.
- `policy_evidence_interface_known_issue`: tests for the bugs found while writing the tests (`src/Unit/KnownIssuesTest.php` and `src/Kernel/KnownIssuesTest.php`). Each checks the correct behaviour, so it fails until the bug is fixed. The comment on each test explains the bug.

## Running the tests

Kernel tests need a database in `SIMPLETEST_DB`. In this project's DDEV site, from the Drupal root:

```bash
ddev exec "SIMPLETEST_DB=sqlite://localhost//tmp/pei.sqlite vendor/bin/phpunit -c web/core --group policy_evidence_interface web/modules/contrib/policy-evidence-interface/tests"
```

Use `SIMPLETEST_DB=mysql://db:db@db/db` to run them on MariaDB instead. Run the known issues with `--group policy_evidence_interface_known_issue`.

In any other Composer-based Drupal project, install `drupal/core-dev` (matching the core version) and run the same `vendor/bin/phpunit` command without `ddev exec`.

## Notes

- Strict config schema checking stays on, except for `policy_evidence_interface.settings`. Drupal finds no schema for it, because it reads schema files only from `config/schema/` and the module keeps its file in `schema/`.
- `read_node_pdf` needs a real file path, so the kernel tests point `public://` at a temporary directory and delete it afterwards.
- The tests turn off Drupal's "user 1 bypasses access" rule (`$usesSuperUserAccessPolicy = FALSE`) and run the tools as a user with only "access content".
- Not covered: Drupal routing and Simple OAuth token checks on `/_mcp` (the tests set the logged-in user directly) and the stdin read loop of `drush mcp:server` (the tests call its message handler).
