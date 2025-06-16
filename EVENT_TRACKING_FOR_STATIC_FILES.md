# Elegant Event Tracking for Static Endpoints (V5 - Implementation Plan)

This document provides a specific, technically accurate, and elegant solution for implementing event tracking on static file endpoints within the WordPress plugin. This version has been verified against the actual codebase and presents a step-by-step implementation plan designed for careful validation.

---

## 1. The Core Challenge & The Elegant Solution

The fundamental challenge is that high-performance static files (`/.well-known/ai-plugin.json`, etc.) are served directly by the web server (Nginx/Apache), which never executes PHP. Therefore, our PHP-based event tracking code is never called.

The most elegant solution is to leverage the plugin's existing dual-strategy architecture:

- **Static File Strategy (Performance First):** Serves a physical file. This is the fastest method and is ideal for production environments where speed is critical for AI crawlers. **No event tracking is possible with this strategy.**
- **WordPress Rewrite Strategy (Tracking & Compatibility):** Uses WordPress's internal rewrite rules to intercept a request, process it with PHP, and then serve the content. This is slightly less performant but allows us to execute code, like sending a tracking event, before serving the file content.

**The Solution:** The plugin already provides an admin toggle, "Use Static Files Only". When this is **disabled**, the plugin uses the WordPress Rewrite Strategy, providing the perfect hook to send analytics events. Our implementation will tap into this existing flow.

---

## 2. The Interception Mechanism: `template_redirect` and `Kismet_Endpoint_Manager`

After careful review of the codebase, the correct interception point is the `handle_template_redirect` method within the `Kismet_Endpoint_Manager` class.

- **What is `template_redirect`?** This is a standard WordPress action hook that fires just before WordPress determines which template file to load. The plugin correctly uses this to handle "virtual" endpoints.
- **Why `Kismet_Endpoint_Manager` is the right place?** This class is responsible for managing all non-admin endpoints. The `handle_template_redirect` method within it loops through registered endpoints and serves content for matching requests. This is the ideal, centralized location to add our tracking logic.

---

## 3. The Performance Question: Minimizing Load

The "WordPress Rewrite Strategy" exists precisely for environments where the web server _cannot_ be relied upon to serve the static file correctly.

Therefore, when using the rewrite strategy, the PHP handler **must** be responsible for serving the content. The most performant way to do this while tracking is:

1.  **Intercept the request** using `template_redirect` in `Kismet_Endpoint_Manager`.
2.  **Send a non-blocking metrics request.** The `Kismet_Metrics_Sender` class is designed for this, minimizing performance impact. The plugin sends the event and immediately moves on.
3.  **Serve the content directly from PHP.** The handler echoes the content and then calls `exit;`.
4.  **`exit;` is crucial.** It stops further WordPress loading, making the execution lightweight and fast.

This ensures that when event tracking is active, the performance impact is negligible.

---

## 4. Step-by-Step Implementation Plan

This guide details the precise code changes for `class-endpoint-manager.php`. The steps are ordered to allow for validation at each stage, minimizing the risk of hard-to-debug errors.

### Step A: Add a Placeholder for the Event Sending Method

This step adds the skeleton of our new function without any logic. This ensures we don't introduce syntax errors before adding the complex parts.

1.  **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
2.  **Location**: Inside the `Kismet_Endpoint_Manager` class, after the closing brace `}` of the `serve_endpoint_content` method (approximately line 258).
3.  **Action**: Define a new private method. It should be named `send_event_for_endpoint` and accept one argument, `$endpoint_path`. For now, leave the method body empty.
4.  **Validation**:
    - Deactivate and reactivate the plugin. It should do so without any PHP errors.
    - Navigate your WordPress site. Everything should function as normal.
    - This confirms the new method was added correctly without breaking the class structure.

### Step B: Implement the Event Sending Logic

Now we will add the logic to the new method. Because it is not yet called from anywhere, we can safely build it and ensure it's correct without affecting the plugin's execution.

1.  **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
2.  **Location**: Inside the body of the `send_event_for_endpoint` method created in the previous step.
3.  **Action**:
    - Add the complete logic for sending the event. This includes:
    - Checking if events should be sent using the `should_send_events()` helper function.
    - Verifying the `Kismet_Metrics_Sender` class exists.
    - Sanitizing the `$endpoint_path` to create a safe event name (e.g., `PLUGIN_STATIC_ENDPOINT_GET_...`).
    - Wrapping the call to `new Kismet_Metrics_Sender()` and `$metrics_sender->send_endpoint_request_data()` inside a `try/catch` block to handle potential errors gracefully.
4.  **Validation**:
    - Deactivate and reactivate the plugin again. The absence of errors confirms that the logic you added is syntactically correct and all referenced classes/functions are available.
    - The plugin's behavior should still be unchanged, as this method is not yet being used.

### Step C: Trigger the Event on Endpoint Access

This is the final step that connects our new logic to the request handling flow.

1.  **File**: `wordpress-plugin/kismet-ai-ready-plugin/includes/shared/class-endpoint-manager.php`
2.  **Location**: Inside the `handle_template_redirect` method. Find the `foreach` loop that iterates through the endpoints. The new line should go inside the `if (isset($wp_query->query_vars[$query_var]))` block, just before the call to `$this->serve_endpoint_content($config);` (approximately line 237).
3.  **Action**: Add a single line to call our new method: `$this->send_event_for_endpoint($path);`.
4.  **Validation**:
    - Deactivate and reactivate the plugin.
    - In your WordPress admin, ensure "Use Static Files Only" is **UNCHECKED**.
    - Access an endpoint like `https://your-site.com/.well-known/ai-plugin.json`.
    - **Check your metrics server logs.** You should see a new event with an `eventType` like `PLUGIN_STATIC_ENDPOINT_GET_WELL_KNOWN_AI_PLUGIN_JSON`.
    - Go back to admin and **CHECK** the "Use Static Files Only" box. Access the same URL. **No event should be sent**.
    - This final test confirms the entire implementation is working correctly and respects the user's performance preference.
