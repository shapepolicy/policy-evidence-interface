# Policy Evidence Interface

A Drupal module that exposes published content to AI clients through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) over HTTP or Drush stdio.

The client target is Drupal `10.6.7`, PHP `8.3.29`, and Drush `12.5.3`; Drupal 11 is also supported. Drupal 10.6.7 is retained for compatibility but has been superseded by a [security release](https://www.drupal.org/sa-core-2026-007).

## Features

- Tools for site information, content types, node search, node details, and PDF-page text extraction.
- Resources for site information, content types, and recently updated nodes.
- Plugin APIs for adding MCP tools and resources.
- Drupal permission-based access control.

PDF fields may be named `field_pdf_article`, `field_pdf`, or `field_file`. Scanned PDFs may require OCR.

## Setup with DDEV

From an existing DDEV Drupal project root, run:

```bash
ddev start
ddev composer config repositories.policy-evidence-interface vcs https://github.com/shapepolicy/policy-evidence-interface.git
ddev composer require "drush/drush:12.5.3" "shapepolicy/policy-evidence-interface:dev-main"
ddev drush en policy_evidence_interface -y
```

Composer installs Simple OAuth and the PDF parser automatically. Keep `composer.json`, `composer.lock`, and `vendor/` at the Drupal project root and commit both Composer files. Grant **Access MCP Server** only to trusted roles.

Confirm the MCP command is available with `ddev drush help mcp:server`. CI runs the unit tests and checks module installation and Drush discovery against the client stack.

## Use with Claude Desktop

The simplest local connection uses MCP over stdio and does not require OAuth registration. Find the DDEV project name with `ddev describe`, then open **Claude Desktop → Settings → Developer → Edit Config** and add the following entry to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "policy-evidence": {
      "command": "wsl.exe",
      "args": ["--", "ddev", "exec", "--project", "YOUR_DDEV_PROJECT", "vendor/bin/drush", "mcp:server"]
    }
  }
}
```

This example is for Claude Desktop on Windows with DDEV in WSL. If DDEV is installed directly on the host, use `"command": "ddev"` and begin `args` with `"exec"`. Replace `YOUR_DDEV_PROJECT`, fully quit and reopen Claude Desktop, then enable **policy-evidence** from the chat's **+ → Connectors** menu.

Try asking Claude: “List the Drupal content types” or “Search published nodes for housing.” Claude will request approval before using a tool.

Available tools are `get_site_info`, `list_content_types`, `search_nodes`, `get_node`, and `read_node_pdf`. Available resources are `drupal://site-info`, `drupal://content-types`, and `drupal://recent-nodes`. The server implements MCP protocol version `2024-11-05`.

## License

[MIT](LICENSE)
