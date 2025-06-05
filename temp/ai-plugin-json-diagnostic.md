# AI Plugin JSON 404 Diagnostic Guide

**Problem:** `https://theknollcroft.com/.well-known/ai-plugin.json` returns 404 Not Found

**File Location:** `kismet-ask-proxy/includes/class-ai-plugin-handler.php`

## Diagnostic Steps (Follow in Order)

### Step 1: Verify Plugin is Active ✅

**Purpose:** Ensure the plugin is actually loaded by WordPress

**Test:**

- Go to WordPress Admin → Plugins → Installed Plugins
- Confirm "Kismet Ask Proxy" shows as "Active"
- Look for any error messages

**Expected Result:** Plugin shows as active with no errors

**If Failed:**

- Reactivate the plugin
- Check for PHP errors in WordPress debug log

---

### Step 2: Check Rewrite Rules Registration 🔍

**Purpose:** Verify WordPress knows about our custom URL rule

**Test Method A - WordPress Admin:**

- Go to WordPress Admin → Settings → Permalinks
- Click "Save Changes" (this flushes rewrite rules)
- Test `/.well-known/ai-plugin.json` again

**Test Method B - Direct Function Test:**
Add this temporary code to `kismet-ask-proxy.php` (after line 45):

```php
// TEMPORARY DEBUG - Remove after testing
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<script>console.log("Rewrite rules test");</script>';
        error_log("KISMET DEBUG: Testing if plugin hooks are working");
    }
});
```

**Expected Result:**

- Method A: ai-plugin.json starts working
- Method B: Console log appears on website frontend

**If Failed:**

- Plugin hooks aren't registering
- PHP syntax error preventing plugin load
- WordPress not calling our init hooks

---

### Step 3: Test Internal Query Parameter 🔍

**Purpose:** Check if the rewrite target works directly

**Test:** Visit this URL directly:

```
https://theknollcroft.com/?kismet_ai_plugin=1
```

**Expected Result:** Should serve JSON content or show some response

**If Success:** Rewrite rule is the problem (Step 4)
**If Failed:** Handler function is the problem (Step 5)

---

### Step 4: Debug Rewrite Rules 🔧

**Purpose:** Fix URL rewriting if Step 3 worked

**Diagnosis:** The rewrite rule `^\.well-known/ai-plugin\.json$` isn't being registered or recognized

**Possible Causes:**

1. **Rules not flushed:** WordPress doesn't know about new rules
2. **Regex pattern error:** The pattern doesn't match the URL
3. **Priority issue:** Another plugin is overriding our rule

**Fixes to Try:**

**Fix A - Force Flush Rewrite Rules:**
Add to `kismet-ask-proxy.php` after activation hook:

```php
// TEMPORARY - Force rewrite rules flush
register_activation_hook(__FILE__, function() {
    $ai_plugin_handler = new Kismet_AI_Plugin_Handler();
    $ai_plugin_handler->flush_rewrite_rules();

    // Force another flush after 5 seconds
    wp_schedule_single_event(time() + 5, 'kismet_delayed_flush');
});

add_action('kismet_delayed_flush', function() {
    flush_rewrite_rules(true); // true = hard flush
});
```

**Fix B - Test Different Regex Pattern:**
In `class-ai-plugin-handler.php`, try this pattern:

```php
add_rewrite_rule('\.well-known/ai-plugin\.json/?$', 'index.php?kismet_ai_plugin=1', 'top');
```

**Fix C - Debug Existing Rules:**
Add this temporary function to see all WordPress rewrite rules:

```php
// TEMPORARY DEBUG FUNCTION
add_action('wp_footer', function() {
    if (current_user_can('administrator') && isset($_GET['debug_rewrites'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->rules);
        echo '</pre>';
    }
});
```

Then visit: `yoursite.com/?debug_rewrites=1`

---

### Step 5: Debug Handler Function 🔧

**Purpose:** Fix JSON generation if Step 3 failed

**Diagnosis:** The `handle_ai_plugin_request()` function isn't working

**Debug Method:** Add logging to the handler function.

**In `class-ai-plugin-handler.php`, modify `handle_ai_plugin_request()`:**

```php
public function handle_ai_plugin_request() {
    error_log("KISMET DEBUG: handle_ai_plugin_request called");

    $query_var = get_query_var('kismet_ai_plugin');
    error_log("KISMET DEBUG: kismet_ai_plugin query var = " . ($query_var ? 'TRUE' : 'FALSE'));

    if ($query_var) {
        error_log("KISMET DEBUG: Serving ai-plugin.json");

        $custom_url = get_option('kismet_custom_ai_plugin_url', '');
        error_log("KISMET DEBUG: Custom URL = '$custom_url'");

        if (!empty($custom_url)) {
            error_log("KISMET DEBUG: Using custom URL proxy");
            $this->proxy_custom_ai_plugin($custom_url);
        } else {
            error_log("KISMET DEBUG: Serving generated JSON");
            $this->serve_generated_ai_plugin();
        }
        exit;
    } else {
        error_log("KISMET DEBUG: Query var not detected, continuing normal WordPress");
    }
}
```

**Expected Logs When Testing `?kismet_ai_plugin=1`:**

```
KISMET DEBUG: handle_ai_plugin_request called
KISMET DEBUG: kismet_ai_plugin query var = TRUE
KISMET DEBUG: Serving ai-plugin.json
KISMET DEBUG: Custom URL = ''
KISMET DEBUG: Serving generated JSON
```

**If No Logs:** Function isn't being called (WordPress hook issue)
**If Logs Show FALSE:** Query variable not registered properly
**If Logs Stop:** Error in JSON generation function

---

### Step 6: Test JSON Generation 🔧

**Purpose:** Verify the JSON content is being generated correctly

**Add to `serve_generated_ai_plugin()` function:**

```php
private function serve_generated_ai_plugin() {
    error_log("KISMET DEBUG: Starting JSON generation");

    $site_url = get_site_url();
    error_log("KISMET DEBUG: Site URL = $site_url");

    // ... existing code ...

    $ai_plugin = [
        // ... existing array ...
    ];

    error_log("KISMET DEBUG: Generated JSON: " . json_encode($ai_plugin));

    status_header(200);
    header('Content-Type: application/json');
    echo json_encode($ai_plugin, JSON_PRETTY_PRINT);

    error_log("KISMET DEBUG: JSON sent, exiting");
}
```

---

### Step 7: Test .htaccess Conflicts 🔍

**Purpose:** Check if server-level redirects are interfering

**Test:** Create a simple test file:

1. Upload a file called `test.json` to `/wp-content/uploads/`
2. Try accessing `yoursite.com/wp-content/uploads/test.json`
3. If that works, try creating `/.well-known/` directory manually
4. Upload `test.json` to `/.well-known/test.json`
5. Try accessing `yoursite.com/.well-known/test.json`

**If Manual File Works:** WordPress rewrite issue
**If Manual File Fails:** Server/.htaccess blocking .well-known URLs

---

## Quick Test Commands

```bash
# Test the endpoint
curl -v "https://theknollcroft.com/.well-known/ai-plugin.json"

# Test internal query parameter
curl -v "https://theknollcroft.com/?kismet_ai_plugin=1"

# Test with verbose headers
curl -I "https://theknollcroft.com/.well-known/ai-plugin.json"

# Test if WordPress is involved at all
curl -v "https://theknollcroft.com/.well-known/nonexistent.json"
```

## Common Solutions Summary

1. **Flush Permalinks:** Admin → Settings → Permalinks → Save Changes
2. **Deactivate/Reactivate Plugin:** Forces hook re-registration
3. **Check Plugin Conflicts:** Deactivate other plugins temporarily
4. **Enable WordPress Debug:** Add logging to see what's happening
5. **Clear Caching:** If using caching plugins/server cache

---

## Current Status Tracking

- [ ] Step 1: Plugin Active Verification
- [ ] Step 2: Rewrite Rules Registration
- [ ] Step 3: Internal Query Parameter Test
- [ ] Step 4: Rewrite Rules Debug
- [ ] Step 5: Handler Function Debug
- [ ] Step 6: JSON Generation Test
- [ ] Step 7: .htaccess Conflicts Test

**Next Step:** Start with Step 1 and work sequentially until we find the issue.
