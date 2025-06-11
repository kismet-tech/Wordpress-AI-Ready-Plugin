# Kismet AI Ready Plugin

This plugin creates an AI-ready WordPress site with three key features:

1. **API Proxy**: Forwards `/ask` requests to Kismet backend
2. **AI Discovery**: Serves `/.well-known/ai-plugin.json` for AI agents
3. **robots.txt Integration**: Adds AI agent permissions to robots.txt

## What It Does

- **Intercepts**: `yoursite.com/ask` requests and forwards to `api.makekismet.com/ask`
- **Serves**: AI plugin discovery file at `yoursite.com/.well-known/ai-plugin.json`
- **Modifies**: robots.txt to allow AI agents access to required endpoints
- **Preserves**: Request method (GET/POST), headers, and body data
- **Returns**: Responses from Kismet backend to original requester

## Installation Methods

### Method 1: WordPress Admin Upload (Recommended)

1. **Create ZIP file**:

   - Zip the entire `kismet-ai-ready-plugin` folder
   - Make sure `kismet-ai-ready-plugin.php` is inside the folder

2. **Upload via WordPress Admin**:
   - Go to: **Plugins** → **Add New** → **Upload Plugin**
   - Choose the ZIP file
   - Click **Install Now**
   - Click **Activate**

### Method 2: Manual File Upload

1. **Upload via FTP/cPanel**:

   - Upload the entire `kismet-ai-ready-plugin` folder to `/wp-content/plugins/`
   - The structure should be: `/wp-content/plugins/kismet-ai-ready-plugin/kismet-ai-ready-plugin.php`

2. **Activate in WordPress**:
   - Go to: **Plugins** → **Installed Plugins**
   - Find "Kismet AI Ready Plugin"
   - Click **Activate**

## Testing All Features

After activation, test all three features:

### 1. Test robots.txt (AI Agent Discovery)

**Check the URL:**

```
https://yoursite.com/robots.txt
```

**Expected result:**

```
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

# Kismet AI integration
User-agent: *
Allow: /ask
Allow: /.well-known/ai-plugin.json
```

### 2. Test AI Plugin JSON (AI Agent Metadata)

**Check the URL:**

```
https://yoursite.com/.well-known/ai-plugin.json
```

**Expected result:**

```json
{
  "schema_version": "v1",
  "name_for_human": "Your Site AI Assistant",
  "name_for_model": "your_site_assistant",
  "description_for_human": "Get information about Your Site...",
  "description_for_model": "Provides hotel information...",
  "auth": {
    "type": "none"
  },
  "api": {
    "type": "openapi",
    "url": "https://yoursite.com/ask"
  },
  "logo_url": "https://yoursite.com/wp-content/uploads/2024/kismet-logo.png",
  "contact_email": "admin@yoursite.com",
  "legal_info_url": "https://yoursite.com/privacy-policy"
}
```

### 3. Test /ask API Endpoint

#### Test with Browser (Human Visitors)

Visit:

```
https://yoursite.com/ask
```

**Expected:** Beautiful animated checklist page with Kismet branding

#### Test with curl (API Requests)

**GET Request Example:**

```bash
curl "https://yoursite.com/ask?query=what are your check-in times" \
  -H "Accept: application/json"
```

**POST Request Example:**

```bash
curl -X POST "https://yoursite.com/ask" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "query": "what are your amenities?",
    "streaming": true
  }'
```

**Expected API Response:**

```json
{
  "response": "Our check-in time is 3:00 PM...",
  "site": "yoursite.com"
}
```

## Plugin Settings

Configure the AI plugin JSON by going to:
**WordPress Admin** → **Settings** → **Kismet AI Plugin**

### Option 1: Custom JSON URL

Enter a URL to your own complete ai-plugin.json file.

### Option 2: Individual Field Customization

Customize individual fields:

- Business Name
- Business Description
- Logo URL
- Contact Email
- Privacy/Legal Info URL

## Troubleshooting

### robots.txt Not Working

If the robots.txt modifications aren't appearing:

1. **Check "Discourage search engines" setting:**

   - Go to **WordPress Admin → Settings → Reading**
   - **Ensure "Discourage search engines from indexing this site" is UNCHECKED**
   - This setting blocks WordPress robots.txt filters from running

2. **Clear cache and test:**

   - Use cache-busting URL: `yoursite.com/robots.txt?v=123`
   - Try incognito/private browser window
   - Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)

3. **Check for physical robots.txt file:**
   - Look in your website root directory via hosting file manager
   - If physical file exists, it overrides WordPress dynamic generation

### ai-plugin.json Returns 404

If the JSON endpoint returns "Not Found":

1. **Flush rewrite rules:**

   - Go to **WordPress Admin → Settings → Permalinks**
   - Click "Save Changes" (don't change anything)
   - This refreshes WordPress URL rewrite rules

2. **Deactivate and reactivate plugin:**
   - Forces rewrite rule registration

## Testing URLs

### 1. Test robots.txt

**URL:** `https://yoursite.com/robots.txt`

**Expected result:**

```
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

# Kismet AI integration
User-agent: *
Allow: /ask
Allow: /.well-known/ai-plugin.json
```

### 2. Test AI Plugin JSON

**URL:** `https://yoursite.com/.well-known/ai-plugin.json`

**Expected result:**

```json
{
  "schema_version": "v1",
  "name_for_human": "Your Site AI Assistant",
  "name_for_model": "your_site_assistant",
  "description_for_human": "Get information about Your Site...",
  "description_for_model": "Provides hotel information...",
  "auth": {
    "type": "none"
  },
  "api": {
    "type": "openapi",
    "url": "https://yoursite.com/ask"
  },
  "logo_url": "https://yoursite.com/wp-content/uploads/2024/kismet-logo.png",
  "contact_email": "admin@yoursite.com",
  "legal_info_url": "https://yoursite.com/privacy-policy"
}
```

## Features

- ✅ Modular architecture with separated concerns
- ✅ robots.txt integration (non-destructive)
- ✅ AI plugin discovery JSON generation
- ✅ Customizable business information
- ✅ API proxy with GET/POST support
- ✅ Branded page for human visitors
- ✅ WordPress admin settings interface
- ✅ Proper error handling and security
- ✅ Debug logging for troubleshooting

## Plugin Validation (Before Installation)

### Recommended: Test Before Installing on Live Sites

> **🏆 Best Practice**: For comprehensive testing, see our [Local WordPress Testing Guide](../testing/local-testing.md) which walks you through setting up an exact replica of your live site using Local by Flywheel.

**1. PHP Syntax Check**

```bash
# Check for PHP syntax errors in all plugin files
find wordpress-plugin/kismet-ai-ready-plugin -name "*.php" -exec php -l {} \;
```

**2. WordPress Plugin Check (Official)**

```bash
# Install WP-CLI plugin check (requires WP-CLI)
wp plugin install plugin-check --activate
wp plugin check wordpress-plugin/kismet-ai-ready-plugin
```

**3. WordPress Coding Standards**

```bash
# Install PHPCS with WordPress standards
composer global require "squizlabs/php_codesniffer=*"
composer global require wp-coding-standards/wpcs

# Run WordPress coding standards check
phpcs --standard=WordPress wordpress-plugin/kismet-ai-ready-plugin/
```

**4. Local Testing Environment**

- Install on **Local by Flywheel**, **XAMPP**, or **Docker WordPress**
- Test all endpoints: `/ask`, `/.well-known/ai-plugin.json`, `/robots.txt`
- Verify admin settings panel works correctly

### Quick Validation Checklist

- [ ] PHP syntax check passes
- [ ] Plugin activates without errors on test site
- [ ] All three endpoints respond correctly
- [ ] Admin settings panel loads and saves
- [ ] No PHP errors in WordPress debug log

## Architecture

The plugin uses a modular approach:

- **Main Plugin** (`kismet-ai-ready-plugin.php`): Coordinates all functionality
- **Robots Handler** (`includes/class-robots-handler.php`): robots.txt modifications only
- **AI Plugin Handler** (`includes/class-ai-plugin-handler.php`): JSON generation and settings
- **Ask Handler** (`includes/class-ask-handler.php`): /ask page and API proxy

This separation ensures that issues with one feature don't affect others, making debugging and maintenance much easier.

## Environment Compatibility Report

After installing and activating the plugin, you will see a **'Kismet Env'** menu item in the WordPress admin sidebar.

- Click this menu to view the full environment compatibility report.
- The report displays errors, warnings, and recommendations from the environment checks.
- This ensures you and your clients can always verify the plugin's compatibility and health after any install or update.
