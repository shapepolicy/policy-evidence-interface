# Policy Evidence Interface

Drupal 10/11 module exposing published content through [MCP](https://modelcontextprotocol.io/): HTTP JSON-RPC at `/_mcp` and local Drush stdio (protocol `2024-11-05`). Tools: `get_site_info`, `list_content_types`, `search_nodes`, `get_node`, and `read_node_pdf`. Resources: `drupal://site-info`, `drupal://content-types`, and `drupal://recent-nodes`.

## 1. DDEV and Composer setup

Install [DDEV and its Docker provider](https://docs.ddev.com/en/stable/users/install/) and Git. On Windows, run these commands inside WSL2, keeping the project in the Linux filesystem.

For a **new, empty Drupal project only** ([DDEV quickstart](https://docs.ddev.com/en/stable/users/quickstart/#drupal)):

```bash
mkdir drupal && cd drupal
ddev config --project-type=drupal11 --docroot=web --php-version=8.4
ddev start
ddev composer create-project 'drupal/recommended-project:^11'
ddev composer require 'drush/drush:^13'
ddev drush site:install standard -y
ddev drush uli
```

For an existing site, start DDEV from its project root and skip the creation/install commands above: `site:install` replaces the database.

From the **Drupal project root**, install this module as a Git-tracked local package:

```bash
git clone --branch main https://github.com/shapepolicy/policy-evidence-interface.git packages/policy-evidence-interface
ddev composer config repositories.policy-interface '{"type":"path","url":"packages/policy-evidence-interface","options":{"symlink":true}}'
ddev composer require 'shapepolicy/policy-evidence-interface:@dev' --with-all-dependencies
ddev drush en policy_evidence_interface -y
ddev drush cr
```

If the package is already cloned, keep that checkout and skip `git clone`. Composer installs Simple OAuth and the PDF parser automatically and links `web/modules/contrib/policy-evidence-interface` to the package. Edit files in `packages/policy-evidence-interface`; do not copy the module or run a separate `composer install` inside it. The `@dev` constraint accommodates the checked-out development branch; it replaces stale branch-specific requirements.

## 2. Local connection — simplest option

Use stdio for Claude Desktop on the same computer. No public URL, OAuth setup, Node.js bridge, or certificate workaround is needed. Keep DDEV running; Claude launches the server automatically.

In Claude Desktop, open **Settings → Developer → Edit Config**. Merge this entry into the existing `mcpServers` object, preserving other settings. Windows + WSL2 example (replace `/home/you/drupal` with your project path):

```json
{
  "mcpServers": {
    "policy-evidence": {
      "command": "wsl.exe",
      "args": ["--cd", "/home/you/drupal", "ddev", "exec", "vendor/bin/drush", "mcp:server"]
    }
  }
}
```

On macOS/Linux, use `"command": "bash"` with `"args": ["-lc", "cd /absolute/path/to/drupal && exec ddev exec vendor/bin/drush mcp:server"]`. Ensure DDEV is available to that shell.

Fully quit and reopen Claude, then ask it to use `get_site_info`. Use the configuration file opened by Claude itself; Microsoft Store installations can use a different location. See [local MCP configuration](https://modelcontextprotocol.io/docs/develop/connect-local-servers).

For other stdio clients, launch `ddev exec vendor/bin/drush mcp:server` with the Drupal project as the working directory. Stdio runs with the local Drupal/Drush process's privileges, not a remote user's OAuth permissions; use it only on trusted development sites.

## 3. Remote connection — HTTPS + OAuth

Claude's hosted connector cannot reach `https://drupal.ddev.site/` directly. Deploy to a reachable HTTPS host, or deliberately expose a development site through a trusted tunnel. The same public origin must serve `/_mcp`, `/.well-known/*`, `/oauth/*`, and the Drupal login pages. Use a valid certificate and configure Drupal's trusted host/reverse-proxy settings for that origin.

The installation above enables the dependencies, but this module does **not** provide an automatic OAuth provisioning command. Configure these once in Drupal's Simple OAuth settings:

1. Generate OAuth keys outside `web/` and configure their paths. Keep keys, passwords, and tokens out of Git; do not regenerate working keys during routine updates.
2. Create role `mcp_connector` with **Access MCP Server** and **Grant simple_oauth codes** permissions. Assign it to a dedicated non-administrator user.
3. Create scope `mcp_connector_scope` using role granularity for `mcp_connector`; enable authorization-code and refresh-token grants.
4. Create an enabled consumer labelled exactly `mcp connector`: public/non-confidential, PKCE required, authorization-code and refresh-token grants enabled, and `mcp_connector_scope` selected as an authorization-code scope. Register these exact hosted-Claude callback URLs:

   ```text
   https://claude.ai/api/mcp/auth_callback
   https://claude.com/api/mcp/auth_callback
   ```

**Current implementation caveat:** discovery and client lookup routes are protected by the same `access mcp server` permission. For an isolated development test, granting **only that permission** to Anonymous lets discovery reach the controller; unauthenticated MCP calls are still rejected there. Never grant administrative permissions. Production deployments need a route/access-control review so discovery is public while tool access remains protected.

In Claude, open **Settings → Connectors → Add custom connector**, enter `https://YOUR-PUBLIC-HOST/_mcp`, leave optional client ID/secret blank, and authorize as the dedicated user. The module's `/oauth/getclientid` automatically returns the preconfigured consumer ID; it is **not full dynamic client registration** and does not register new callback URLs. Other OAuth clients may need their exact callback registered separately. See [Claude connector setup](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp) and [authentication requirements](https://claude.com/docs/connectors/building/authentication).

Before connecting, verify that both `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server` return JSON without login, and unauthenticated `/_mcp` returns `401` with a `WWW-Authenticate` header. A `403` usually indicates the routing/permission issue above; `invalid_client` warrants checking the consumer label, client ID, and exact redirect URI. Never disable TLS verification to fix a connection.

## 4. Version control and GitHub sync

The package is its own Git repository; Composer's [path repository](https://getcomposer.org/doc/05-repositories.md#path) links it but does not pull or push Git commits. To update a clean checkout from `main`, run from the Drupal root:

```bash
git -C packages/policy-evidence-interface switch main
git -C packages/policy-evidence-interface pull --ff-only origin main
ddev composer update shapepolicy/policy-evidence-interface --with-all-dependencies
ddev drush cr
```

Commit or stash your work before switching/pulling. Refresh Composer after dependency or branch changes; ordinary source edits appear through the symlink immediately. To publish an intentional README change on `main` (requires GitHub write access and configured HTTPS authentication):

```bash
git -C packages/policy-evidence-interface add README.md
git -C packages/policy-evidence-interface commit -m "docs: update setup and connection guide"
git -C packages/policy-evidence-interface push origin main
```

Use feature branches and pull requests for normal development or protected branches; never force-push to bypass protection. If you prefer SSH, set the package's `origin` to `git@github.com:shapepolicy/policy-evidence-interface.git` after configuring your GitHub SSH key.

If the parent Drupal project is also version-controlled, commit its `composer.json`, `composer.lock`, and shareable `.ddev` configuration there. Either ignore this independently cloned package directory in the parent repository or track it as a Git submodule; do not accidentally commit an unmanaged nested repository. A fresh machine must clone/initialize the package **before** `ddev composer install`; a path lock entry does not fetch its Git checkout. Never commit `vendor/`, generated module copies, database dumps, or credentials.

PDF extraction supports `field_pdf_article`, `field_pdf`, and `field_file`; scanned/non-extractable PDFs require separate OCR.

## License

[MIT](LICENSE)
