# Prebuild Validation - Code Quality & Compliance

**Purpose:** Validate code quality and syntax before installation.

## Quick Validation

### 1. PHP Syntax Check

```bash
find kismet-ai-ready-plugin -name "*.php" -exec php -l {} \;
```

### 2. WordPress Plugin Check

```bash
# Install if needed
brew install wp-cli

# Run check
wp plugin check kismet-ai-ready-plugin
```

### 3. File Structure Check

```bash
ls -la kismet-ai-ready-plugin/
ls -la kismet-ai-ready-plugin/includes/
```

### 4. Security Scan

```bash
grep -r "eval\|exec\|system\|shell_exec\|passthru" kismet-ai-ready-plugin/
```

## Automated Script

**`validate-plugin.sh`:**

```bash
#!/bin/bash
echo "🔧 Prebuild Validation"

cd ..

# PHP syntax check
echo "1️⃣ PHP Syntax..."
for file in $(find kismet-ai-ready-plugin -name "*.php"); do
    php -l "$file" || exit 1
done
echo "✅ PHP syntax OK"

# WordPress check
echo "2️⃣ WordPress Plugin Check..."
wp plugin check kismet-ai-ready-plugin 2>/dev/null || echo "⚠️ WP-CLI not available"

# File structure
echo "3️⃣ File Structure..."
[ -f "kismet-ai-ready-plugin/kismet-ai-ready-plugin.php" ] && echo "✅ Main file OK" || exit 1
[ -d "kismet-ai-ready-plugin/includes/" ] && echo "✅ Includes dir OK" || exit 1

echo "🎉 Validation complete!"
```

**Make executable:**

```bash
chmod +x validate-plugin.sh
```

## Success Criteria

- ✅ All PHP files parse without errors
- ✅ No critical WordPress Plugin Check errors
- ✅ Required files exist
- ✅ No dangerous functions found

**Next:** [Local Testing](../local/README.md)
