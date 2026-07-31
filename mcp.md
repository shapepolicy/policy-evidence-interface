# MCP Implementation Documentation

<https://github.com/shapepolicy/policy-evidence-interface/tree/MCP-Endpoint-Harry>  

Was developed after attempting off the shelf and being sketched out by the custom O Auth implementation on off the shelf implementation.

## Known issues

- Security insecure will need oAuth rate limiting and other hardining/ admin controls to be added. Some of the tools can access items above normal user privilege, through json-rcp input manipulation (TO BE IMPLEMENTED NEXT SEMESTER)
- dependancy you will need to composer require the file for the pdfparser to dependancies to be loaded in.
- fragile if a tool/resource doesn’t work it will break the MCP connection (behavior of Drupal plugins) see debug for help with getting useful debug.

## Running

Attempt a composer install this should install the one external dependency that the project requires if not you will have to install smalot/parser manually with composer install.

In terminal navigate to Drupal project root then within run. (if using DDEV you need to append that to the head, further details at: <https://anu-team-wgvj67mh.atlassian.net/wiki/spaces/DE/pages/9273349>)

```shell
composer config repositories.policy-interface vcs <https://github.com/shapepolicy/policy-evidence-interface>
```

```shell
composer require shapepolicy/policy-evidence-interface:dev-main
```

Install the module clear the cache. Then you will need to edit the endpoint access permissions.  

After this clear the cache and you should be able to access the MCP endpoint.

## Features

Able to setup Local connectors through https or stdio:

MCP endpoint connection through https is possible requires npx to launch proxy server:

```json
"mcpServers": {
    "drupalLocalHttp": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://d11mymcp.ddev.site/_mcp"
      ],
      "env": {
        "NODE_OPTIONS": "--use-system-ca"
      }
    }
}
```

MCP endpoint connection through stdio

```json
"mcpServers": {
    "drupalLocalStdio": {
      "command": "wsl",
      "args":[
        "--cd", "/home/harry/workspace/d11mymcp",
        "ddev", "exec", "vendor/bin/drush", "mcp:server"
      ]
    }
  }
```

Able to setup Global connectors connectors: (note this is insecure so not recommended)

MCP Tool/Resource factory:

Allows for the easy addition of new tools and resources into the module. Using Drupal core plugins. WARNING initial comment is effects code behavior as it’s how the Drupal core/plugin discovers the tool/resource and what data is pulled in such as description. See example comment block below. Make sure that the plugins are put into the correct folder.

```php
/**

* Returns basic information about the Drupal site.
*
* @McpTool(
*   id = "get_site_info",
*   tool_name = "get_site_info",
*   description = "Returns general information about this Drupal site including name, slogan, Drupal version, and base URL."
* )
 */
```

Below both resource and tool have the same output but the resource appears to be partially cached into the LLM so that it doesn't need to query server over and over.

- Tool Example: <https://github.com/shapepolicy/policy-evidence-interface/blob/main/src/Plugin/McpTool/GetSiteInfo.php>
- Resource Example: <https://github.com/shapepolicy/policy-evidence-interface/blob/main/src/Plugin/McpResource/SiteInfo.php>

Current tools in main branch: (note these tools are insecure and can result in users accessing content above their intended access permission)

- get_site_info — returns general site info (name, version, URL, etc.)
- list_content_types — lists all content types on the site
- search_nodes — searches published nodes by keyword, optionally filtered by content type
- get_node — fetches full details of a node by its ID
- read_node_pdf — extracts text from a PDF attached to a node

## Expected Behavior

connection is connected and working:

- able to see server is running when viewing developer
- Is able to see resources from the server
- Is able to access available tools and return json-rcp description interpretation

## Debugging

Server Disconnect:

Server is not connecting i.e Claude is returning that it is disconnecting. The server is either actually disconnecting or isn’t connecting at all you will need to open up the Claude developer options and examine the logs for what is breaking .

Server Connected But Claude says there are no connections/tools aren’t being got:

This is an issue with one of the plugins to debug what plugin and how you will need to run below this will output the available tools that the MCP has access to.  

```shell
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | ddev exec vendor/bin/drush mcp:server
```

Manual tool/resource testing

If the tools are loading correctly you may need to manually test it out see below code snippet running the read node PDF tool:

<https://github.com/shapepolicy/policy-evidence-interface/blob/main/src/Plugin/McpTool/ReadNodePdf.php>

```shell
harry@DESKTOP-RNC9CK5:~/workspace/d11mymcp$ curl -X POST <https://d11mymcp.ddev.site/_mcp> -H "Content-Type: application/json" -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"read_node_pdf\",\"arguments\":{\"nid\":4,\"page_start\":1}}}"
```

Tool mismatch or not found:

Make sure that the correct branch is loaded and you have rebuilt the cache.

## MCP endpoint

Until now this part implements a minimal MCP-style endpoint for searching and retrieving policy evidence stored as Drupal nodes. The endpoint receives JSON requests, dispatches them to the correct tool, and return JSON response.

## Off the shelf MCP module

Off the shelf module. <https://www.drupal.org/project/mcp_server>

Issues:

- Security issue developer was using custom OAuth github branch made by them self <!!!Very Scary!!!>
- Internal versioning issues meaning that drush en was required to enable modules
- Modules reuirements didn’t fully install after running an composer on the module
- Module was able to get an Claude code connection working however it wasn’t able to be tested Properoly when connecting into Cluade desktop.
