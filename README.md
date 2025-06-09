# Kismet WordPress Plugin Architecture Analysis

## Overview

The Kismet WordPress Plugin uses a sophisticated, **modular architecture** with **bulletproof safety checks** to serve AI-specific endpoints. It handles critical routes like `/ask`, `/llms.txt`, `/robots.txt`, and `/.well-known/*` that AI bots access.

## 🔍 **Key Discovery: Request Interception Methods**

After thorough investigation, here's exactly how the plugin intercepts server requests:

### **Method 1: WordPress `init` Hook (Primary Method)**
```php
// In each handler's constructor:
add_action('init', array($this, 'handle_requests'));

// Handler checks REQUEST_URI directly:
public function handle_requests() {
    $request_uri = $_SERVER['REQUEST_URI'];
    if (strpos($request_uri, '/ask') === 0) {
        // Handle the request
        $this->process_request();
        exit; // Prevents WordPress from continuing
    }
}
```

**How it works:**
- Hooks into WordPress's early request lifecycle
- Checks `$_SERVER['REQUEST_URI']` on **every page request**
- Intercepts and handles matching routes before WordPress routing
- **This captures ALL requests**, including bot requests that don't run JavaScript

### **Method 2: Hybrid Physical Files + .htaccess + WordPress Fallback**

The plugin uses a **sophisticated triple-approach system**:

#### **A. Physical File Creation (.htaccess Rewrites)**
During activation, the plugin creates:

1. **PHP Handler Files:**
   - `.well-known/ai-plugin-handler.php`
   - `.well-known/mcp/servers-handler.php` 
   - `llms-handler.php`

2. **Strategic .htaccess Rules:**
```apache
# In .well-known/.htaccess
RewriteRule ^ai-plugin\.json$ ai-plugin-handler.php [L]

# In .well-known/mcp/.htaccess  
RewriteRule ^servers\.json$ servers-handler.php [L]

# In root .htaccess
RewriteRule ^llms\.txt$ llms-handler.php [L]
```

3. **Result:** When bots request `/.well-known/ai-plugin.json`, Apache/nginx serves `ai-plugin-handler.php` which loads WordPress and calls the handler.

#### **B. WordPress Rewrite Fallback**
If physical files fail, handlers fall back to:
```php
add_rewrite_rule('^llms\.txt$', 'index.php?kismet_llms_txt=1', 'top');
add_action('template_redirect', array($this, 'handle_llms_request'));
```

#### **C. Intelligent Testing System**
`Kismet_Route_Tester` determines which approach works:
```php
$test_results = $this->route_tester->determine_serving_method('/llms.txt');
// Tests both approaches with temporary files
// Recommends: 'physical_file' or 'wordpress_rewrite'
```

## 🏗️ **Plugin Architecture**

### **Core Components**

1. **Main Plugin File** (`kismet-ask-proxy.php`)
   - Initializes all handlers
   - Manages activation/deactivation
   - Coordinates environment testing

2. **Endpoint Handlers** (`includes/endpoints/`)
   - `class-ask-handler.php` - Handles `/ask` API requests
   - `class-llms-txt-handler.php` - Manages `/llms.txt`
   - `class-robots-handler.php` - Enhances `/robots.txt`
   - `class-ai-plugin-handler.php` - Serves `/.well-known/ai-plugin.json`
   - `class-mcp-servers-handler.php` - Serves `/.well-known/mcp/servers.json`

3. **Safety Infrastructure** (`includes/shared/`)
   - `class-route-tester.php` - Tests route accessibility before deployment
   - `class-file-safety-manager.php` - Safe file creation and management

4. **Environment Detection** (`includes/environment/`)
   - Tests hosting capabilities
   - Determines optimal serving methods
   - Provides compatibility reports

### **Request Flow**

```
Incoming Request → WordPress `init` Hook → Handler Checks REQUEST_URI → 
Handler Processes → Response Sent → exit (WordPress processing stops)
```

## 🎯 **CRITICAL DISCOVERY: Dual Request Interception Methods**

The plugin architecture provides **TWO distinct interception points** for tracking:

### **🔄 Request Flow Analysis:**

#### **Path A: Physical File Route** (Primary for many hosting environments)
```
Bot Request → Apache/nginx → .htaccess rewrite → PHP handler → WordPress load → Handler class
```

#### **Path B: WordPress Rewrite Route** (Fallback)  
```
Bot Request → Apache/nginx → WordPress → init hook → Handler class
```

### **🎯 Perfect Integration Points for AI Bot Tracking:**

#### **1. ✅ Universal Handler Coverage**
Both paths converge at the **Handler classes**, which provide:
- Complete `$_SERVER` data access
- Full request context
- Modular, isolated tracking integration

#### **2. ✅ Comprehensive Route Coverage**
- `/ask` - AI question endpoint (WordPress rewrite only)
- `/llms.txt` - LLM policy file (dual approach)
- `/robots.txt` - Bot discovery (dual approach)  
- `/.well-known/ai-plugin.json` - ChatGPT manifest (dual approach)
- `/.well-known/mcp/servers.json` - MCP discovery (dual approach)

#### **3. ✅ Strategic Tracking Placement**
**Best practice:** Add tracking at the **Handler class level** to capture both paths:

```php
// In each handler (Ask, LLMS, Robots, etc.)
public function handle_request() {
    // ADD TRACKING HERE - captures both physical file and WordPress routes
    $this->track_request();
    
    // Existing handler logic continues...
}

## 🔧 **Implementation Strategy for Server-Side Tracking**

### **Approach: Hook into Existing Handlers**

Create a tracking utility that each handler calls:

```php
// New file: includes/tracking/class-server-tracking.php
class Kismet_Server_Tracking {
    private $backend_url;
    
    public function track_request($endpoint, $additional_data = []) {
        $metric_data = [
            'metricName' => $this->get_metric_name($endpoint),
            'value' => 1,
            'labels' => [
                'endpoint' => $endpoint,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $this->get_client_ip(),
                'is_bot' => $this->detect_ai_bot(),
                'timestamp' => time(),
                'source' => 'wordpress_plugin'
            ]
        ];
        
        // Send to existing handleAddMetric endpoint
        $this->send_to_backend($metric_data);
    }
}
```

### **Integration Points:**

1. **Ask Handler** - Track every `/ask` request
```php
// In class-ask-handler.php
public function handle_ask_requests() {
    $request_uri = $_SERVER['REQUEST_URI'];
    if (strpos($request_uri, '/ask') === 0) {
        // ADD TRACKING HERE
        $this->tracker->track_request('ask_endpoint', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'has_json_accept' => strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        ]);
        
        // Existing logic continues...
    }
}
```

2. **LLMS/Robots Handlers** - Track file requests
```php
// In each file handler
private function track_file_access($file_type) {
    $this->tracker->track_request($file_type . '_access');
}
```

## 📊 **Data Available for Tracking**

The handlers have access to comprehensive request data:

```php
$available_data = [
    'request_uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'accept_header' => $_SERVER['HTTP_ACCEPT'],
    'referer' => $_SERVER['HTTP_REFERER'],
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => time(),
    'is_https' => is_ssl(),
    'query_params' => $_GET,
    'post_data' => file_get_contents('php://input')
];
```

## 🚫 **Deduplication Strategy**

Since the plugin captures ALL requests (including from users with JavaScript), we need smart deduplication:

### **Option 1: Bot-Only Tracking**
```php
public function should_track() {
    // Only track clear AI bots
    return $this->detect_ai_bot();
}

private function detect_ai_bot() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bot_patterns = [
        'ChatGPT-User',
        'ClaudeBot',
        'OpenAI',
        'Anthropic',
        'PerplexityBot'
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    return false;
}
```

### **Option 2: Request Signature Tracking**
```php
public function should_track() {
    // Track if: clear bot OR no JavaScript indicators
    if ($this->detect_ai_bot()) return true;
    
    // Check for absence of browser-like behavior
    $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
    $has_browser_accept = strpos($accept_header, 'text/html') !== false;
    
    return !$has_browser_accept; // Track non-browser requests
}
```

## 🔗 **Integration with Existing Backend**

The plugin can use the existing `handleAddMetric` endpoint:

```php
private function send_to_backend($metric_data) {
    $backend_url = 'https://api.makekismet.com/metrics'; // Your existing endpoint
    
    wp_remote_post($backend_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($metric_data),
        'timeout' => 5
    ]);
}
```

## 📋 **Implementation Steps**

1. **Create Tracking Class** (`includes/tracking/class-server-tracking.php`)
2. **Modify Each Handler** to call tracking methods
3. **Add Bot Detection** logic
4. **Configure Backend URL** in WordPress admin
5. **Test with Real Bot Traffic**

## 🏆 **Advantages of This Approach**

- ✅ **Zero Backend Changes** - Uses existing `handleAddMetric` endpoint
- ✅ **Comprehensive Coverage** - Captures all AI bot endpoints
- ✅ **Minimal Performance Impact** - Async HTTP requests
- ✅ **Bulletproof Safety** - Built on existing robust architecture
- ✅ **Easy Maintenance** - Follows existing plugin patterns

This plugin architecture provides the **perfect foundation** for comprehensive AI bot tracking without any major architectural changes. 