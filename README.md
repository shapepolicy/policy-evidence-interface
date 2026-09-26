# Policy Evidence Interface

A Drupal module that exposes published site content to AI clients through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). It supports JSON-RPC over HTTP and newline-delimited JSON-RPC over Drush stdio.

## Target environment

- Drupal `10.6.7`
- PHP `8.3.29`
- Drush `12.5.3`
- Composer

These versions are compatible: Drush 12 supports Drupal 10 and PHP 8.1 or newer. The module manifest also permits Drupal 11, although the target environment uses Drupal 10.6.7.

## Features

- Tools for site information, content types, node search, node details, and page-level PDF text extraction.
- Resources for site information, content types, and the 20 most recently updated nodes.
- Annotation-based plugin APIs for adding MCP tools and resources.
- Access control through Drupal's `access mcp server` permission.

PDF extraction supports file fields named `field_pdf_article`, `field_pdf`, or `field_file`. Scanned or font-encoded PDFs may require OCR and are reported as non-extractable.

## Installation

From this module directory, install the PDF parser:

```bash
composer install
```

Enable the module and clear Drupal's cache:

```bash
drush en policy_evidence_interface -y
drush cr
```

Grant the **Access MCP Server** permission only to trusted roles.

## Connecting

The HTTP endpoint is:

```text
https://example.com/_mcp
```

For a local stdio MCP client, run Drupal's Drush command from the Drupal project root:

```bash
vendor/bin/drush mcp:server
```

For DDEV:

```bash
ddev exec vendor/bin/drush mcp:server
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
