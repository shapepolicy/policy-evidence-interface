# Policy Evidence Interface

A Drupal module that exposes published site content to AI clients through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). It supports JSON-RPC over HTTP and newline-delimited JSON-RPC over Drush stdio.

## Target environment

- Drupal `10.6.7`
- PHP `8.3.29`
- Drush `12.5.3`
- Composer

These versions are compatible: Drush 12 supports Drupal 10 and PHP 8.1 or newer. The module manifest also permits Drupal 11, although the target environment uses Drupal 10.6.7.

Drupal 10.6.7 is retained here to reproduce the client environment, but it has
been superseded by [security releases](https://www.drupal.org/sa-core-2026-007)
in the 10.6 line. Plan a client-tested Drupal patch update; changing the CI pin
should follow the client upgrade.

## Features

- Tools for site information, content types, node search, node details, and page-level PDF text extraction.
- Resources for site information, content types, and the 20 most recently updated nodes.
- Annotation-based plugin APIs for adding MCP tools and resources.
- Access control through Drupal's `access mcp server` permission.

PDF extraction supports file fields named `field_pdf_article`, `field_pdf`, or `field_file`. Scanned or font-encoded PDFs may require OCR and are reported as non-extractable.

## Deploy to an existing Drupal site

Run these commands from the Drupal project root, where the site's `composer.json`
and `composer.lock` live:

```bash
composer config repositories.policy-evidence-interface vcs https://github.com/shapepolicy/policy-evidence-interface.git
composer require "shapepolicy/policy-evidence-interface:dev-main"
vendor/bin/drush en policy_evidence_interface -y
vendor/bin/drush updb -y
vendor/bin/drush cr
```

Composer installs Simple OAuth and the PDF parser automatically. Enabling this
module also enables its Drupal module dependencies; do not install OAuth or a
second `vendor/` directory inside this module. Commit the Drupal root project's
`composer.json` and `composer.lock` for deployment.

Grant the **Access MCP Server** permission only to trusted roles.

For production, deploy the Drupal root project and run:

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/drush updb -y
vendor/bin/drush cr
```

## Fresh client-stack setup

The shortest reproducible setup for the client's versions is:

```bash
composer create-project "drupal/recommended-project:10.6.7" policy-evidence-drupal
cd policy-evidence-drupal
composer require "drush/drush:12.5.3"
composer config repositories.policy-evidence-interface vcs https://github.com/shapepolicy/policy-evidence-interface.git
composer require "shapepolicy/policy-evidence-interface:dev-main"
vendor/bin/drush site:install standard
vendor/bin/drush en policy_evidence_interface -y
vendor/bin/drush cr
```

The site installation command asks for the database and administrator details.
If the site already exists, skip `site:install`.

## Testing and delivery

For standalone module development, run these commands from this repository:

```bash
composer install
composer test
```

The GitHub Actions workflow runs unit tests with Drupal 11 and validates a clean
install, module enable, Drush discovery, and MCP launch on the client's exact
Drupal 10.6.7 / PHP 8.3.29 / Drush 12.5.3 stack. Pull requests to `main` run
once; a successful push to `main` also publishes a module source ZIP. Production
deployment uses the Drupal root Composer commands above so dependencies and the
site lock file remain controlled by the client project.

## Connecting

The HTTP endpoint starts with Drupal when the module is enabled; it needs no
separate server process:

```text
https://example.com/_mcp
```

For a local stdio MCP client, run this single command from the Drupal project
root and leave it running:

```bash
vendor/bin/drush mcp:server
```

For DDEV, use the equivalent command:

```bash
ddev drush mcp:server
```

The server implements MCP protocol version `2024-11-05`.

## Available MCP interfaces

| Type | Name or URI | Purpose |
| --- | --- | --- |
| Tool | `get_site_info` | Return site metadata and Drupal version |
| Tool | `list_content_types` | List configured node bundles |
| Tool | `search_nodes` | Search published node titles |
| Tool | `get_node` | Read a published node and its fields |
| Tool | `read_node_pdf` | Extract one page from an attached PDF |
| Resource | `drupal://site-info` | Site configuration |
| Resource | `drupal://content-types` | Configured node bundles |
| Resource | `drupal://recent-nodes` | 20 most recently updated published nodes |

## License

[MIT](LICENSE)
