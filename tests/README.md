# Policy Evidence Interface - Test Suite

This directory contains PHPUnit tests for the `policy_evidence_interface` Drupal module.

## Overview

The test suite is organized into **Kernel tests** (for plugin logic and schemas) and **Functional tests** (for routing and forms). Tests verify the **Phase 1 scaffold** (plugin discovery, tool definitions, placeholder behavior) without assuming real business logic.

## Test Structure

```
tests/
├── src/
│   ├── Kernel/
│   │   ├── PolicyEvidencePluginTest.php      # Plugin discovery and execution
│   │   └── PolicyEvidenceSchemaTest.php      # Tool schema validation
│   └── Functional/
│       ├── PolicyEvidenceRoutingTest.php     # Admin route accessibility
│       └── PolicyEvidenceSettingsFormTest.php # Settings form behavior
└── README.md (this file)
```

## Test Coverage

### Kernel Tests

#### PolicyEvidencePluginTest.php
Tests MCP plugin discovery and tool execution:

| Test | Purpose | Phase 1 Status |
|------|---------|---------|
| `testPluginDiscovery()` | Plugin ID discoverable by MCP manager | ✅ Pass |
| `testPluginInstantiation()` | Plugin can be instantiated | ✅ Pass |
| `testGetToolsReturnsExactlyTwoTools()` | Exposes 2 tools | ✅ Pass |
| `testToolNames()` | Tools named `search_policies`, `get_policy` | ✅ Pass |
| `testSearchPoliciesPlaceholderExecution()` | Returns "not yet implemented" | ✅ Pass |
| `testGetPolicyPlaceholderExecution()` | Returns "not yet implemented" | ✅ Pass |
| `testUnknownToolThrowsException()` | Invalid tool ID raises exception | ✅ Pass |
| `testSearchPoliciesWithLimitParameter()` | Accepts limit parameter | ✅ Pass |
| `testGetPolicyWithIdParameter()` | Accepts id parameter | ✅ Pass |

#### PolicyEvidenceSchemaTest.php
Tests tool input schema definitions:

| Test | Purpose | Phase 1 Status |
|------|---------|---------|
| `testSearchPoliciesToolSchema()` | Tool has inputSchema | ✅ Pass |
| `testSearchPoliciesQueryIsRequired()` | `query` is required | ✅ Pass |
| `testSearchPoliciesLimitIsOptional()` | `limit` is optional | ✅ Pass |
| `testSearchPoliciesLimitDefaultValue()` | `limit` default = 10 | ✅ Pass |
| `testSearchPoliciesLimitMaximumValue()` | `limit` max = 50 | ✅ Pass |
| `testSearchPoliciesLimitType()` | `limit` type = integer | ✅ Pass |
| `testGetPolicyToolSchema()` | Tool has inputSchema | ✅ Pass |
| `testGetPolicyIdIsRequired()` | `id` is required | ✅ Pass |
| `testGetPolicyIdType()` | `id` type = integer | ✅ Pass |

### Functional Tests

#### PolicyEvidenceRoutingTest.php
Tests admin route and access control:

| Test | Purpose | Phase 1 Status |
|------|---------|---------|
| `testSettingsRouteExists()` | Route returns 200 for authorized users | ✅ Pass |
| `testSettingsFormRenders()` | Form displays without errors | ✅ Pass |
| `testSettingsRouteRequiresAuthentication()` | Denies unauthenticated access | ✅ Pass |
| `testSettingsRouteRequiresPermission()` | Denies users without permission | ✅ Pass |
| `testSettingsFormControllerIsCorrect()` | Route controller exists | ✅ Pass |
| `testSettingsRoutePathIsCorrect()` | Route path = `/admin/config/services/policy-evidence-interface` | ✅ Pass |

#### PolicyEvidenceSettingsFormTest.php
Tests settings form functionality:

| Test | Purpose | Phase 1 Status |
|------|---------|---------|
| `testSettingsFormAccessible()` | Form is accessible to authorized users | ✅ Pass |
| `testSettingsFormHasSubmitButton()` | Form has submit button | ✅ Pass |
| `testDefaultConfigurationLoaded()` | Default config loads | ✅ Pass |

## Running the Tests

### Prerequisites

Your Drupal installation must have:
- PHPUnit 9+ installed (as a dev dependency)
- Drupal test framework set up
- `mcp` module installed
- `policy_evidence_interface` module installed

### Option 1: Using Drupal's Built-in Test Runner (Recommended)

```bash
cd /path/to/drupal/root

# Run all tests for the module
php core/scripts/run-tests.sh --module policy_evidence_interface

# Run a specific test class
php core/scripts/run-tests.sh --module policy_evidence_interface --verbose \
  Drupal\\Tests\\policy_evidence_interface\\Kernel\\PolicyEvidencePluginTest

# Run only Kernel tests
php core/scripts/run-tests.sh --module policy_evidence_interface --types Kernel

# Run only Functional tests
php core/scripts/run-tests.sh --module policy_evidence_interface --types Functional
```

### Option 2: Using PHPUnit Directly

```bash
cd /path/to/drupal/root

# Run all module tests
./vendor/bin/phpunit modules/custom/policy_evidence_interface/tests

# Run a specific test file
./vendor/bin/phpunit modules/custom/policy_evidence_interface/tests/src/Kernel/PolicyEvidencePluginTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testPluginDiscovery modules/custom/policy_evidence_interface/tests/src/Kernel/PolicyEvidencePluginTest.php
```

### Option 3: From within the Module Directory

```bash
cd modules/custom/policy_evidence_interface

# Run all tests
../../vendor/bin/phpunit tests/

# Run specific test
../../vendor/bin/phpunit tests/src/Kernel/PolicyEvidencePluginTest.php
```

## Expected Test Results

With **Phase 1 scaffold** (plugin installed, routes defined, placeholder execution), **all 23 tests should pass**.

```
Tests: 23 passed
Success: All tests passed.
```

## Phase 2+ TODO Tests

After implementing real business logic, add tests for:

### Search Functionality
- Searching published Drupal nodes by title and body
- Respecting the `limit` parameter
- Respecting configured enabled content types
- Returning canonical node URLs
- Returning node summaries/body content

### Get Policy Functionality
- Returning actual node data for valid IDs
- Returning proper error messages for missing nodes
- Respecting access control (unpublished, restricted content)
- Returning node metadata (topics, jurisdictions, etc.)

### Configuration
- Verifying max_results configuration affects search
- Verifying enabled_content_types configuration filters results
- Testing configuration validation

### Error Handling & Edge Cases
- Empty query strings
- Special characters in queries
- Non-integer node IDs
- Concurrent requests
- Data sanitization (HTML/XSS escaping)

See inline `TODO` comments in test files for Phase 2 placeholders and example test code.

## Test Dependencies

### Required Modules

| Module | Purpose |
|--------|---------|
| `system` | Core Drupal functionality |
| `mcp` | MCP plugin manager and infrastructure |
| `policy_evidence_interface` | The module being tested |

### PHP Requirements

- PHP 8.1+ (Drupal 10+ standard)
- PHPUnit 9.5+

## Troubleshooting

### Issue: "Plugin not found: policy_evidence_interface"

**Cause:** Module not installed or MCP plugin not registered.

**Solution:**
1. Ensure module is enabled: `drush en policy_evidence_interface`
2. Ensure MCP module is enabled: `drush en mcp`
3. Verify plugin is registered in `hook_install()` or `policy_evidence_interface.module`

### Issue: "Service plugin.manager.mcp not found"

**Cause:** MCP module not installed or not bootstrapped in test.

**Solution:**
1. Add `mcp` to `$modules` array in test class
2. Run `$this->installConfig(['policy_evidence_interface'])` in `setUp()`

### Issue: "Route not found / 404"

**Cause:** Functional test not booting full Drupal.

**Solution:**
1. Ensure test extends `BrowserTestBase` (not `KernelTestBase`)
2. Set `$defaultTheme = 'stark'` in test class
3. Verify routing YAML file exists and is valid

### Issue: "Test database not set up"

**Cause:** Test environment not initialized.

**Solution:**
```bash
# Use Drupal's test runner, which handles database setup
php core/scripts/run-tests.sh --module policy_evidence_interface
```

## Future Maintenance

When updating the module:

1. **Plugin changes** → Update `PolicyEvidencePluginTest.php`
2. **Schema changes** → Update `PolicyEvidenceSchemaTest.php`
3. **Routing changes** → Update `PolicyEvidenceRoutingTest.php`
4. **Form changes** → Update `PolicyEvidenceSettingsFormTest.php` (Phase 2)
5. **New features** → Add new test files in appropriate subdirectory

## References

- [Drupal PHPUnit Testing](https://www.drupal.org/docs/drupal-apis/phpunit-testing)
- [Kernel Tests (KernelTestBase)](https://api.drupal.org/api/drupal/core%21tests%21Drupal%21KernelTests%21KernelTestBase.php/class/KernelTestBase)
- [Functional Tests (BrowserTestBase)](https://api.drupal.org/api/drupal/core%21tests%21Drupal%21Tests%21BrowserTestBase.php/class/BrowserTestBase)
- [MCP Module Documentation](https://github.com/anthropics/drupal-mcp-module)
