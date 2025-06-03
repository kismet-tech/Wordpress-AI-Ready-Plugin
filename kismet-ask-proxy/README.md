# Kismet Ask Proxy WordPress Plugin

This plugin intercepts requests to `/ask` on WordPress sites and forwards them to `api.makekismet.com/ask`.

## What It Does

- **Intercepts**: `yoursite.com/ask` requests
- **Forwards to**: `api.makekismet.com/ask`
- **Preserves**: Request method (GET/POST), headers, and body data
- **Returns**: Response from Kismet backend to original requester

## Installation Methods

### Method 1: WordPress Admin Upload (Recommended)

1. **Create ZIP file**:

   - Zip the entire `kismet-ask-proxy` folder
   - Make sure `kismet-ask-proxy.php` is inside the folder

2. **Upload via WordPress Admin**:
   - Go to: **Plugins** → **Add New** → **Upload Plugin**
   - Choose the ZIP file
   - Click **Install Now**
   - Click **Activate**

### Method 2: Manual File Upload

1. **Upload via FTP/cPanel**:

   - Upload the entire `kismet-ask-proxy` folder to `/wp-content/plugins/`
   - The structure should be: `/wp-content/plugins/kismet-ask-proxy/kismet-ask-proxy.php`

2. **Activate in WordPress**:
   - Go to: **Plugins** → **Installed Plugins**
   - Find "Kismet Ask Proxy"
   - Click **Activate**

## Testing

After activation, test by visiting:

```
yoursite.com/ask
```

This should forward to `api.makekismet.com/ask` and return the response.

## Troubleshooting

### Plugin Not Showing Up

- Check file location: `/wp-content/plugins/kismet-ask-proxy/kismet-ask-proxy.php`
- Ensure PHP opening tag `<?php` is the first line

### Getting 502 Errors

- Check WordPress error logs
- Verify `api.makekismet.com` is accessible from your server
- Check PHP error logs for details

### Enable Debug Logging

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `/wp-content/debug.log`

## Features

- ✅ Supports GET and POST requests
- ✅ Forwards request headers and body
- ✅ Proper error handling
- ✅ WordPress security best practices
- ✅ Debug logging for troubleshooting
- ✅ CORS header forwarding

## Technical Details

The plugin uses WordPress's `wp_remote_request()` function to make secure HTTP requests to the Kismet backend while preserving all necessary request data.
