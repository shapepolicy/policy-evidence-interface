# Policy Evidence Interface

A Drupal module that exposes published content to AI clients through the
[Model Context Protocol (MCP)](https://modelcontextprotocol.io/) over HTTP or
Drush stdio.

Client target: Drupal `10.6.7`, PHP `8.3.29`, and Drush `12.5.3`. The module also
supports Drupal 11. Drupal 10.6.7 is retained for compatibility but has been
superseded by a [security release](https://www.drupal.org/sa-core-2026-007).

## Features

- Site, content-type, node-search, node-detail, and PDF-page tools.
- Site, content-type, and recent-node resources.
- Plugin APIs for adding MCP tools and resources.
- Drupal permission-based access control.

PDF fields may be named `field_pdf_article`, `field_pdf`, or `field_file`.
Scanned PDFs require OCR.

## Install (3 commands)

Run from the Drupal project root:

```bash
composer config repositories.policy-evidence-interface vcs https://github.com/shapepolicy/policy-evidence-interface.git
composer require "shapepolicy/policy-evidence-interface:dev-main"
vendor/bin/drush en policy_evidence_interface -y
```

Composer installs Simple OAuth and the PDF parser. Keep `composer.json`,
`composer.lock`, and `vendor/` at the Drupal project root. Grant **Access MCP
Server** only to trusted roles.

With DDEV, replace `composer` with `ddev composer` and `vendor/bin/drush` with
`ddev drush`. For a new Drupal environment, complete the
[DDEV Drupal quickstart](https://docs.ddev.com/en/stable/users/quickstart/)
first, then run the same three module-installation commands.

## Test

From this module repository:

```bash
composer install
composer test
```

CI runs Drupal 11 unit tests and verifies installation and Drush discovery on
Drupal 10.6.7 with Drush 12.5.3 and a Composer PHP 8.3.29 platform. Successful
pushes to `main` publish a module source ZIP.

## Deploy

Run from the deployed Drupal project root:

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/drush en policy_evidence_interface -y
vendor/bin/drush updb -y
vendor/bin/drush cr
```

## Connect

HTTP is available at `https://example.com/_mcp` when the module is enabled. For
stdio, leave one of these commands running from the Drupal project root:

```bash
vendor/bin/drush mcp:server
# DDEV:
ddev drush mcp:server
```

The server implements MCP protocol version `2024-11-05`.

## MCP interfaces

| Type | Name or URI | Purpose |
| --- | --- | --- |
| Tool | `get_site_info` | Site metadata and Drupal version |
| Tool | `list_content_types` | Configured node bundles |
| Tool | `search_nodes` | Published node title search |
| Tool | `get_node` | Published node details |
| Tool | `read_node_pdf` | One page of attached PDF text |
| Resource | `drupal://site-info` | Site configuration |
| Resource | `drupal://content-types` | Configured node bundles |
| Resource | `drupal://recent-nodes` | 20 recently updated nodes |

## License

[MIT](LICENSE)
