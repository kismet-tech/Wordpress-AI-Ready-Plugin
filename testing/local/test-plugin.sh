#!/bin/bash

# Kismet AI Ready Plugin - Testing Script
# Usage: ./test-plugin.sh
# Run from wordpress-plugin directory

echo "🧪 Testing Kismet AI Ready Plugin..."
echo "====================================="

# Check if we're in the right directory
if [ ! -d "kismet-ai-ready-plugin" ]; then
    echo "❌ Error: kismet-ai-ready-plugin directory not found!"
    echo "Please run this script from the wordpress-plugin directory."
    exit 1
fi

# Basic PHP Syntax Check First
echo ""
echo "🔍 PHP Syntax Check..."
echo "----------------------"
echo "Checking all PHP files for syntax errors..."

syntax_errors=0
for file in $(find kismet-ai-ready-plugin -name "*.php"); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ Syntax error in: $file"
        php -l "$file"
        syntax_errors=$((syntax_errors + 1))
    fi
done

if [ $syntax_errors -eq 0 ]; then
    file_count=$(find kismet-ai-ready-plugin -name "*.php" | wc -l | tr -d ' ')
    echo "✅ All $file_count PHP files have valid syntax"
else
    echo "❌ Found $syntax_errors PHP syntax errors!"
    exit 1
fi

# WordPress Plugin Check
echo ""
echo "🔧 WordPress Plugin Check..."
echo "-----------------------------"

if command -v wp &> /dev/null; then
    # Check if plugin check is available
    if wp help plugin check > /dev/null 2>&1; then
        echo "Running official WordPress Plugin Check..."
        wp plugin check kismet-ai-ready-plugin
    else
        echo "⚠️ WordPress Plugin Check not installed."
        echo "The 'wp plugin check' command requires the Plugin Check plugin."
        echo ""
        echo "To install Plugin Check:"
        echo "1. Set up a local WordPress site"
        echo "2. Install the Plugin Check plugin from WordPress.org"
        echo "3. Run: wp plugin check /path/to/kismet-ai-ready-plugin"
        echo ""
        echo "For now, we've verified PHP syntax is correct."
    fi
else
    echo "❌ WP-CLI not found!"
    echo "Install with: brew install wp-cli"
    echo "Note: WordPress Plugin Check also requires a WordPress installation."
fi

echo ""
echo "🎉 Available checks complete!"
echo ""
echo "✅ PHP syntax validation: PASSED"
echo "⚠️  WordPress Plugin Check: Requires WordPress installation"
echo ""
echo "Next steps:"
echo "1. Test on a local WordPress installation"
echo "2. Install Plugin Check plugin in WordPress"
echo "3. Run full validation: wp plugin check kismet-ai-ready-plugin" 