# Policy Evidence Interface

A Drupal module that lets AI clients such as Claude and ChatGPT search and read a site's published content and attached PDFs through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). Built for [Shape Policy](https://shapepolicy.au) and [APO](https://apo.org.au) as an ANU TechLauncher project.

## Requirements

Drupal 10.6 or later, PHP 8.3 or later, Drush 12.5 or later, and a Composer project that uses Drupal's package repository (as `drupal/recommended-project` does). CI tests Drupal 10.6 with PHP 8.3; the test suite also passes on Drupal 11.4 with PHP 8.4. Composer installs [Simple OAuth](https://www.drupal.org/project/simple_oauth) and [`smalot/pdfparser`](https://github.com/smalot/pdfparser).

## Installation

The module is not on Packagist, so first add this repository to the Drupal project. From the Drupal project root:

```bash
composer config repositories.policy-evidence-interface vcs https://github.com/shapepolicy/policy-evidence-interface
```

Then install the latest 1.x release:

```bash
composer require shapepolicy/policy-evidence-interface:^1.0
```

```bash
drush pm:enable policy_evidence_interface --yes
```

The optional `policy_evidence_interface_basic_tools` submodule adds a `list_content_types_sub` tool as an example of a tool defined in another module. Enable it only if you want that tool.

### Updating

Read the [release notes](https://github.com/shapepolicy/policy-evidence-interface/releases) first, because a release can need new permissions. For example, v1.0.1 needs **View published content** for the `mcp_connector` role. Then:

```bash
composer update shapepolicy/policy-evidence-interface
```

```bash
drush cache:rebuild
```

## Configuration

### Rate limits

All tools are disabled until a user with **Administer site configuration** sets limits at **Configuration → System → Policy evidence interface settings**. Set a **Global limit** and a limit for each tool you want to use, per **Window duration**. Over HTTP, limits count per Drupal user; all stdio sessions share one limit. A limit of `0` disables the tool. Unchecking **Enable rate limiting** turns off all tool calls.

### Hiding nodes by tag

On the same page, enter taxonomy term IDs in **Disallowed Taxonomy Tag IDs**; none are blocked after install. Nodes tagged with any of them in `field_tags` are hidden from `search_nodes`, `get_node` and `read_node_pdf`, so those content types need a `field_tags` field.

### OAuth for HTTP clients

1. Create a role with the machine name `mcp_connector` and give it to MCP users.
2. Give **Access MCP Server** to the Anonymous, Authenticated and `mcp_connector` roles, and **Grant OAuth2 codes** and **View published content** to `mcp_connector`. `get_node` and `read_node_pdf` only return nodes the signed-in user may view.
3. Go to **Configuration → Web services → Consumers**. On the **Settings** tab, set a public and private key outside the web root.
4. On the **Scopes** tab, add a dynamic scope `mcp_connector_scope` with the Authorization Code and Refresh Token grants and role granularity for `mcp_connector`.
5. On the **Clients** tab, add a consumer labelled exactly `mcp connector` with the same grants and scope, **Use PKCE?** checked, **Is Confidential?** unchecked, and each AI client's redirect URI (for Claude, `https://claude.ai/api/mcp/auth_callback`).

## Connecting

- **HTTP:** add `https://example.com/_mcp` as a custom connector in your AI client. It discovers the OAuth server and signs the user in through Drupal.
- **stdio:** local clients can run `vendor/bin/drush mcp:server` from the Drupal project root. This runs without OAuth, so anyone who can run Drush on the server can use every tool. Drush runs as the anonymous user, so `get_node` and `read_node_pdf` only return nodes that anonymous visitors can view, and nothing unless the Anonymous role has **View published content**.

### Troubleshooting

- **Tools missing or the connection drops:** run `drush cache:rebuild`, then `echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | vendor/bin/drush mcp:server` to see any plugin errors.
- **Sign-in succeeds but the client rejects the token:** turn off DNS proxying (for example Cloudflare's proxy) for the site.
- **Signed in but no access:** check that the user has the `mcp_connector` role.

## Tools and resources

| Tool | Description |
| --- | --- |
| `search_nodes` | Search published node titles (`keyword`, optional `content_type` and `limit`, which is kept between 1 and 50). |
| `get_node` | Read a published node by `nid`, if the user may view it. |
| `read_node_pdf` | Extract one page (`page_start`) of the PDF attached to node `nid` in a `field_pdf_article`, `field_pdf` or `field_file` field, if the user may view the node. |
| `get_site_info` | Site name, URL and Drupal version. |
| `list_content_types` | The site's content types. |

Resources: `drupal://site-info`, `drupal://content-types` and `drupal://recent-nodes`.

## Extending

Other modules can add tools in `src/Plugin/McpTool` by extending `McpToolBase` with an `@McpTool(id = "...", description = "...")` annotation, and resources in `src/Plugin/McpResource` by extending `McpResourceBase`. See `modules/policy_evidence_interface_basic_tools` for an example. Rebuild the cache and set the new tool's limit before use.

## Testing

The PHPUnit unit and kernel tests are in the `policy_evidence_interface` group and run in CI on every push and pull request. [tests/README.md](tests/README.md) explains how to run them locally.

## Documentation

- [`doc/rolling-mcp-doc.pdf`](doc/rolling-mcp-doc.pdf): setup, OAuth, rate limits, architecture, troubleshooting and manual test procedures.
- [`doc/rolling-milestone-doc.pdf`](doc/rolling-milestone-doc.pdf): project milestones from discovery to handover.
- [Releases](https://github.com/shapepolicy/policy-evidence-interface/releases): what changed in each version.

## License

[MIT](LICENSE)
