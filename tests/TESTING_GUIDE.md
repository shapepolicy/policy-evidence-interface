# Policy Evidence Interface - Testing Guide

Quick reference for running, writing, and maintaining tests.

## Quick Start

Run all tests for the module:

```bash
cd /path/to/drupal
php core/scripts/run-tests.sh --module policy_evidence_interface
```

Expected output with Phase 1 scaffold:
```
Tests run: 23
Passes: 23
Failures: 0
Exceptions: 0
```

## Test Organization

### Phase 1 (Current - Scaffold Verification)

**Kernel Tests** (faster, no full Drupal bootstrap)
- `PolicyEvidencePluginTest.php` — 9 tests for plugin discovery and execution
- `PolicyEvidenceSchemaTest.php` — 9 tests for tool schema definitions

**Functional Tests** (slower, full Drupal bootstrap)
- `PolicyEvidenceRoutingTest.php` — 6 tests for admin route and permissions
- `PolicyEvidenceSettingsFormTest.php` — 3 tests for form rendering

**Total: 27 tests** (23 currently active, 4 as placeholders for Phase 2)

### Phase 2+ (Implementation Validation)

Tests will expand when real business logic is implemented:
- Search results validation
- Access control enforcement
- Configuration effects
- Data sanitization
- Error handling

## Running Specific Tests

### Run one test class:
```bash
php core/scripts/run-tests.sh --module policy_evidence_interface \
  Drupal\\Tests\\policy_evidence_interface\\Kernel\\PolicyEvidencePluginTest
```

### Run one test method:
```bash
./vendor/bin/phpunit --filter testPluginDiscovery \
  modules/custom/policy_evidence_interface/tests/src/Kernel/PolicyEvidencePluginTest.php
```

### Run only Kernel tests (fast):
```bash
php core/scripts/run-tests.sh --module policy_evidence_interface --types Kernel
```

### Run only Functional tests (slower):
```bash
php core/scripts/run-tests.sh --module policy_evidence_interface --types Functional
```

## Test Coverage Checklist

### ✅ Phase 1 Coverage (All should PASS)

- [x] Plugin is discoverable by MCP plugin manager
- [x] Plugin can be instantiated
- [x] Plugin exposes exactly 2 tools
- [x] Tool names are correct (search_policies, get_policy)
- [x] search_policies returns placeholder response
- [x] get_policy returns placeholder response
- [x] Unknown tool throws InvalidArgumentException
- [x] search_policies has required `query` parameter
- [x] search_policies has optional `limit` parameter
- [x] limit has default value 10
- [x] limit has maximum value 50
- [x] limit is integer type
- [x] get_policy has required `id` parameter
- [x] id is integer type
- [x] Admin settings route exists at correct path
- [x] Settings route requires authentication
- [x] Settings route requires permission
- [x] Settings form renders without errors

### 🔜 Phase 2 Coverage (TODO - After Implementation)

- [ ] search_policies searches published nodes
- [ ] search_policies respects limit parameter
- [ ] search_policies respects enabled_content_types
- [ ] get_policy returns actual node data
- [ ] get_policy returns proper URLs
- [ ] get_policy handles missing nodes
- [ ] get_policy respects access controls
- [ ] Configuration affects plugin behavior
- [ ] Data is properly sanitized
- [ ] Error cases handled gracefully

## Adding New Tests

### For a new feature:

1. Determine test type (Kernel vs. Functional)
2. Add test method to appropriate class
3. Follow naming convention: `testFeatureName()`
4. Use descriptive assertions with clear failure messages
5. Add Phase 2 TODO comments for related future tests

### Example test (Kernel):

```php
/**
 * Test that new feature works correctly.
 */
public function testNewFeature(): void {
  $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
  
  $result = $plugin->doSomething();
  
  $this->assertEquals(
    'expected_value',
    $result,
    'doSomething() should return expected_value.'
  );
}
```

### Example test (Functional):

```php
/**
 * Test that form field for new feature is present.
 */
public function testNewFormField(): void {
  $admin = $this->drupalCreateUser(['administer site configuration']);
  $this->drupalLogin($admin);
  
  $this->drupalGet('/admin/config/services/policy-evidence-interface');
  
  $this->assertSession()->fieldExists('new_field_name');
}
```

## Test Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Plugin not found | Module not installed | Run `drush en policy_evidence_interface` |
| Service not found | MCP module missing | Add `mcp` to test `$modules` array |
| Route 404 | Functional test needs full bootstrap | Use `BrowserTestBase` not `KernelTestBase` |
| Database errors | Test DB not created | Use `php core/scripts/run-tests.sh` |
| Permission denied | Missing test permission | Add to `drupalCreateUser()` array |

## Debugging Tests

### Run with verbose output:
```bash
php core/scripts/run-tests.sh --module policy_evidence_interface --verbose
```

### Run with debug output:
```bash
./vendor/bin/phpunit -v modules/custom/policy_evidence_interface/tests
```

### Stop on first failure:
```bash
./vendor/bin/phpunit --stop-on-failure modules/custom/policy_evidence_interface/tests
```

### Run with code coverage (requires xdebug):
```bash
./vendor/bin/phpunit --coverage-text modules/custom/policy_evidence_interface/tests
```

## Test Principles

1. **One assertion focus** — Each test verifies one behavior
2. **Clear naming** — Test name describes what is being tested
3. **Setup → Execute → Assert** — Clear three-phase structure
4. **No external dependencies** — Tests use mocked/test data
5. **Fast execution** — Kernel tests << Functional tests
6. **Phase-aware** — Tests document what phase they verify

## CI/CD Integration

Tests should be run in CI before merging:

```yaml
# Example GitHub Actions
- name: Run Tests
  run: |
    cd drupal
    php core/scripts/run-tests.sh --module policy_evidence_interface
```

Failure = PR cannot merge until fixed.

## Performance Notes

- **Kernel tests**: ~0.1-0.5s per test
- **Functional tests**: ~0.5-2s per test (includes form rendering)
- **Full suite**: ~30-60s total

## References

| Resource | Link |
|----------|------|
| Drupal Testing Guide | https://www.drupal.org/docs/drupal-apis/phpunit-testing |
| KernelTestBase | https://api.drupal.org/api/drupal/core/tests/Drupal/KernelTests/KernelTestBase.php |
| BrowserTestBase | https://api.drupal.org/api/drupal/core/tests/Drupal/Tests/BrowserTestBase.php |
| PHPUnit Docs | https://phpunit.de/documentation.html |
| MCP Module | https://github.com/anthropics/drupal-mcp-module |

## Next Steps

1. ✅ Generate test files (DONE)
2. ⏳ Run tests and verify all pass
3. ⏳ Phase 2: Implement search_policies business logic
4. ⏳ Phase 2: Add search result validation tests
5. ⏳ Phase 2: Implement get_policy business logic
6. ⏳ Phase 2: Add node retrieval validation tests
7. ⏳ Phase 2+: Add data sanitization tests
8. ⏳ Phase 2+: Add access control tests
