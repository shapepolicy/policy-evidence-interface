# Policy Evidence Interface

An open-source Drupal module connecting AI systems to public policy research. Developed through ANU TechLauncher for client ShapePolicy, in partnership with Australian Policy Online (APO).

Tools cover site information, content types, published-node search/details, and PDF extraction. Resources expose site information, content types, and recent nodes.

## Setup

Requires DDEV and Docker. **New site only**—skip this block for existing sites; installation replaces the database.

```bash
mkdir drupal && cd drupal
ddev config --project-type=drupal11 --docroot=web --php-version=8.4
ddev start
ddev composer create-project 'drupal/recommended-project:^11'
ddev composer require 'drush/drush:^13'
ddev drush site:install -y
```

With DDEV running, install from the Drupal root using the default installer paths. Preserve existing module checkouts before migration.

```bash
ddev composer config repositories.policy-interface vcs https://github.com/shapepolicy/policy-evidence-interface.git
ddev composer require shapepolicy/policy-evidence-interface:dev-main --prefer-source
ddev drush en policy_evidence_interface -y
```

Edit, commit, and push from `web/modules/contrib/policy-evidence-interface`. Composer installs the Git checkout and dependencies automatically.

## Connect

Local: `ddev exec vendor/bin/drush mcp:server`.

Remote: `https://example.com/_mcp`. Requires configured Simple OAuth, role `mcp_connector`, and **Access MCP Server** permission. Review OAuth discovery/access controls before production.

## Update

Save local work, then run from the Drupal root:

```bash
ddev composer update shapepolicy/policy-evidence-interface --prefer-source
ddev drush cr
```

Track project `composer.json` and `composer.lock` separately from the module's Git history.

[MIT](LICENSE)
