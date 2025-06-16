# Static Endpoint Event Tracking: A Deep Dive into the Request Lifecycle

This document provides a detailed, file-by-file walkthrough of how a request for each of the plugin's static endpoints is handled when the "WordPress Rewrite" strategy is active. It is intended to be a comprehensive technical reference to inform and justify the implementation plan for event tracking.

For the high-level, step-by-step implementation guide, please see the companion document: `[EVENT_TRACKING_FOR_STATIC_FILES.md](mdc:wordpress-plugin/EVENT_TRACKING_FOR_STATIC_FILES.md)`.

---

## 1. Core Concepts: The `content_generator`

The key to understanding this architecture is the `content_generator`. When an endpoint is registered with the `Kismet_Endpoint_Manager`, it is not given a static block of text. Instead, it is given a **callable function**—a recipe that can be executed at any time to build the endpoint's content from scratch.

This is why the "WordPress Rewrite" strategy works so well as a fallback. It doesn't need a pre-existing static file; it can generate the content dynamically for any request that WordPress routes to it.

All of the following request traces assume the **"Use Static Files Only"** setting is **UNCHECKED** in the plugin's admin panel.

---

## 2. Request Lifecycle for `/.well-known/ai-plugin.json`

This trace follows a `GET` request to the AI plugin's main JSON file.

1.  **Entry Point (`.htaccess` -> `index.php`)**: The web server passes the request to WordPress.
2.  **Plugin Load (`kismet-ai-ready-plugin.php`)**: WordPress loads the main plugin file. The `Kismet_Ask_Proxy_Plugin` class is instantiated, which in turn instantiates `Kismet_Endpoint_Manager` and hooks the `handle_template_redirect` method to the `template_redirect` action.
3.  **Endpoint Registration (During Activation)**:
    - **File**: `includes/installers/class-ai-plugin-installer.php`
    - **Action**: The `activate()` method runs, calling `register_endpoint`.
    - **Content Generator**: The recipe is defined here as `array(Kismet_AI_Plugin_Installer::class, 'generate_ai_plugin_content')`.
4.  **Request Handling (`wp-includes/template-loader.php`)**: WordPress fires the `template_redirect` action.
5.  **Interception**:
    - **File**: `includes/shared/class-endpoint-manager.php`
    - **Action**: The `handle_template_redirect()` method runs. It finds a match for the `ai-plugin.json` endpoint and calls `$this->serve_endpoint_content()`.
6.  **Content Generation**:
    - **File**: `includes/shared/class-endpoint-manager.php`
    - **Action**: Inside `serve_endpoint_content()`, the line `$content = call_user_func($config['content_generator']);` executes our recipe.
7.  **Recipe Execution**:
    - **File**: `includes/installers/class-ai-plugin-installer.php`
    - **Action**: The `generate_ai_plugin_content()` method is executed _on this request_. It calls `get_site_url()`, `get_bloginfo()`, and `get_option()` to fetch the latest settings from the database and builds the JSON string.
8.  **Response**:
    - **File**: `includes/shared/class-endpoint-manager.php`
    - **Action**: The `serve_endpoint_content()` method receives the freshly generated JSON, sets the `Content-Type` header to `application/json`, `echo`s the content, and calls `exit;`.

---

## 3. How to Validate the Lifecycle with Logs

To see this entire flow happen in real-time, you can add temporary `error_log` statements to key files. First, ensure `WP_DEBUG` and `WP_DEBUG_LOG` are enabled in your `wp-config.php` file. Then, add the following logs. After adding them, make a request to `/.well-known/ai-plugin.json` and check your `/wp-content/debug.log` file.

1.  **Confirm Plugin Initialization**

    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/kismet-ai-ready-plugin.php`
    - **Location**: At the beginning of the `Kismet_Ask_Proxy_Plugin` constructor.
    - **Log**: `error_log('LIFECYCLE TRACE (1/5): Plugin constructor loaded, about to hook template_redirect.');`

2.  **Confirm Endpoint Registration**

    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/installers/class-ai-plugin-installer.php`
    - **Location**: Inside the `activate()` method, just after the call to `$endpoint_manager->register_endpoint(...)`.
    - **Log**: `error_log('LIFECYCLE TRACE (ACTIVATION): Endpoint for /.well-known/ai-plugin.json registered.');`
      _(Note: This log only appears in `debug.log` right after you deactivate and reactivate the plugin.)_

3.  **Confirm Request Interception**

    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
    - **Location**: At the beginning of the `handle_template_redirect()` method.
    - **Log**: `error_log('LIFECYCLE TRACE (2/5): template_redirect hook fired, entering handle_template_redirect.');`

4.  **Confirm Content Generation is Triggered**

    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
    - **Location**: Inside the `serve_endpoint_content()` method, just before the `call_user_func` line.
    - **Log**: `error_log('LIFECYCLE TRACE (3/5): Matched endpoint. About to call content generator: ' . print_r($config['content_generator'], true));`

5.  **Confirm the Specific Recipe Runs**

    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/installers/class-ai-plugin-installer.php`
    - **Location**: At the beginning of the `generate_ai_plugin_content()` method.
    - **Log**: `error_log('LIFECYCLE TRACE (4/5): Executing generate_ai_plugin_content() to build JSON.');`

6.  **Confirm Final Output**
    - **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
    - **Location**: At the end of the `serve_endpoint_content()` method, just before the `exit;` line.
    - **Log**: `error_log('LIFECYCLE TRACE (5/5): Content generated and served. Request complete.');`

---

## 4. Request Lifecycle for other Static Endpoints

The lifecycles for the other static endpoints (`mcp/servers.json`, `robots.txt`, `llms.txt`) are identical to the one above, with the only difference being the specific file and `content_generator` function used in Step 3 and Step 7.

To find these, we must look at the files that are included in the main plugin file `kismet-ai-ready-plugin.php`. The content logic for each endpoint is defined in the `includes/endpoint-content-logic/` directory.

### A. Endpoint: `/.well-known/mcp/servers.json`

- **Content Logic File**: `includes/endpoint-content-logic/class-mcp-servers-content-logic.php`
- **Content Generator Function**: `generate_mcp_servers_content()`
- **Trace Difference**: In step 7, this function is called to generate the `servers.json` content.

### B. Endpoint: `/robots.txt`

- **Content Logic File**: `includes/endpoint-content-logic/class-robots-content-logic.php`
- **Content Generator Function**: `generate_robots_content()`
- **Trace Difference**: In step 7, this function is called to generate the `robots.txt` content.

### C. Endpoint: `/llms.txt`

- **Content Logic File**: `includes/endpoint-content-logic/class-llms-content-logic.php`
- **Content Generator Function**: `generate_llms_content()`
- **Trace Difference**: In step 7, this function is called to generate the `llms.txt` content.

---

## 5. Unified Implementation Plan

This deep dive confirms that our initial analysis was correct and that the architecture is robust and consistent across all static endpoints. Because the `Kismet_Endpoint_Manager` handles all of these endpoints in the exact same way, a single set of changes in that one file is all that is required to implement event tracking for all four endpoints simultaneously.

The plan outlined in `[EVENT_TRACKING_FOR_STATIC_FILES.md](mdc:wordpress-plugin/EVENT_TRACKING_FOR_STATIC_FILES.md)` is therefore confirmed to be the correct and complete approach.

**Summary of Changes:**

1.  **File to Edit**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
2.  **Change 1 (Add Method)**: A new private method, `send_event_for_endpoint($endpoint_path)`, will be added to the class. This method will contain the logic for sanitizing the path and sending the data to the metrics server.
3.  **Change 2 (Call Method)**: A single line, `$this->send_event_for_endpoint($path);`, will be added inside the `handle_template_redirect` method.

This single call will be hit for any of the four static endpoints, and the `$path` variable it passes will ensure the correct, unique event name is generated for each one. This is the elegant, minimal-change solution that the plugin's architecture allows for.
