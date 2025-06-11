# Local Testing - Avoiding Errors in Production

**Purpose:** Test complete plugin functionality in a safe, local environment that mirrors your live site.

**When to run:** After prebuild validation passes.

## Why Local Testing?

### **Safe Environment**

- Test without affecting live site visitors
- Break things without consequences
- Rollback instantly if needed

### **Identical to Production**

- Same WordPress version as your live site
- Same themes and plugins as production
- Same hosting environment characteristics

### **Comprehensive Validation**

- Test all plugin features and endpoints
- Verify admin interface functionality
- Check performance and compatibility

## Local Environment Setup

### Recommended: Local by Flywheel

Local by Flywheel is the industry standard for WordPress development environments.

**Install Local by Flywheel:**

```bash
# Install via Homebrew (macOS)
brew install --cask local

# Launch the application
open -a Local
```

### Create Your Local Site

**In Local by Flywheel:**

1. **Create New Site**

   - Click "Create a new site"
   - Name it (e.g., "kismet-plugin-test")

2. **Environment Settings**

   - **PHP Version**: Match your live site
   - **Web Server**: Nginx (recommended)
   - **MySQL**: Latest version

3. **WordPress Setup**
   - Create admin credentials
   - Wait for installation to complete

### Import Your Live Site (Optional but Recommended)

**If you want to test on an exact replica:**

**Option A: Manual Export via cPanel**

1. **Export Database:**

   - In cPanel, go to **phpMyAdmin**
   - Select your WordPress database
   - Click **Export** tab → **Quick** → **Go**
   - Download the `.sql` file

2. **Export Files:**

   - Login to cPanel → **File Manager**
   - Navigate to `public_html` (or your WordPress directory)
   - Select all WordPress files → **Compress** → Download `.zip`

3. **Create Local Import Package:**

   - **Extract the WordPress files** from your zip
   - **Create new folder** with your site backup files
   - **Put the `.sql` file AND extracted WordPress files together**
   - **Zip everything together** into one package

4. **Import to Local:**
   - In Local: Right-click site → **Import/Export** → **Import**
   - Select your combined `.zip` package
   - Wait for import to complete

**Option B: WordPress Plugin Method**

- Install **Duplicator** plugin (free) on live site
- Create package (generates single `.zip` with files + database)
- Download package
- Import to Local using plugin's installer

**Option C: Fresh WordPress**

- Use fresh WordPress installation
- Manually configure to match live site

## Plugin Testing Process

### 1. Install the Plugin

**Upload Plugin:**

- Go to local site WP Admin
- Navigate to Plugins → Add New → Upload Plugin
- Select your `kismet-ai-ready-plugin.zip` file
- Install and activate

**Verify Installation:**

- Check plugin appears in Plugins list
- Look for any activation errors
- Verify no PHP errors in debug log

### 2. Test Core Functionality

**Admin Interface:**

- Go to Settings → Kismet AI Plugin
- Verify settings page loads correctly
- Test saving different configurations
- Check for JavaScript errors in browser console

**robots.txt Enhancement:**

```bash
# Test robots.txt endpoint
curl http://localhost:8080/robots.txt

# Should include Kismet AI integration section
```

**AI Plugin Discovery:**

```bash
# Test AI plugin JSON
curl http://localhost:8080/.well-known/ai-plugin.json

# Should return valid JSON
```

**Ask Endpoint:**

```bash
# Test human visitor mode (HTML response)
curl -H "Accept: text/html" http://localhost:8080/ask

# Test API mode (JSON response)
curl -X POST http://localhost:8080/ask \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"query": "test question", "streaming": false}'
```

### 3. Performance Testing

**Page Load Impact:**

- Test homepage load time with/without plugin
- Check WordPress admin performance
- Monitor memory usage

**Error Monitoring:**

```bash
# Enable WordPress debugging in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

# Check for errors
tail -f wp-content/debug.log
```

**Browser Console:**

- Check for JavaScript errors
- Verify no broken resources
- Test on different browsers

### 4. Compatibility Testing

**Theme Compatibility:**

- Test with your current theme
- Verify frontend display works correctly
- Check mobile responsiveness

**Plugin Compatibility:**

- Test with your current plugins active
- Look for plugin conflicts
- Verify no functionality interference

## Testing Checklist

- [ ] Plugin installs without errors
- [ ] Settings page loads and saves correctly
- [ ] robots.txt includes Kismet integration
- [ ] AI plugin JSON returns valid response
- [ ] /ask endpoint serves HTML to browsers
- [ ] /ask endpoint returns JSON for API requests
- [ ] No PHP errors in WordPress debug log
- [ ] No JavaScript errors in browser console
- [ ] Compatible with current theme
- [ ] Compatible with current plugins
- [ ] Performance impact is acceptable

## Automated Local Testing

Use the existing test script for automated validation:

```bash
# Run automated tests
./test-plugin.sh
```

This script validates:

- PHP syntax (should pass from prebuild)
- WordPress Plugin Check (if available)
- File structure
- Basic functionality

## Troubleshooting

### Plugin Won't Activate

- Check for PHP syntax errors
- Verify file permissions
- Look at WordPress error logs
- Ensure WordPress version compatibility

### Endpoints Return 404

- Flush permalink structure: Settings → Permalinks → Save Changes
- Check .htaccess file permissions
- Verify mod_rewrite is enabled in Local

### API Requests Fail

- Check Kismet backend connectivity
- Verify request headers are correct
- Test with different user agents
- Check for security plugin interference

### Local Site Won't Start

- Check if ports 80/443 are available
- Try changing Local's preferred port
- Restart Local application
- Check available disk space

### Performance Issues

- Monitor WordPress queries
- Check for memory leaks
- Profile with browser dev tools
- Test with minimal theme/plugins

## Success Criteria

✅ **Ready for production when:**

- All checklist items pass
- No errors in debug logs
- Performance impact is minimal
- Compatible with your theme/plugins
- All endpoints respond correctly

❌ **Stop and fix if:**

- Plugin causes any PHP errors
- Endpoints return unexpected responses
- Performance degrades significantly
- Conflicts with existing functionality

## Alternative Local Environments

If Local by Flywheel doesn't work for you:

**XAMPP/MAMP:**

- Download and install XAMPP or MAMP
- Set up WordPress manually
- Copy plugin to wp-content/plugins/

**Docker:**

```bash
# Quick WordPress setup
docker run --name wp-test -p 8080:80 -d wordpress:latest
```

**Staging Server:**

- Use hosting provider's staging environment
- Upload plugin via FTP or WordPress admin
- Test on server that matches production

---

**Next Step:** After all local tests pass, proceed to [Production Testing](../production/README.md)
