#!/bin/bash

echo "🔧 Kismet AI Ready Plugin - Prebuild Validation"
echo "=============================================="

# Check if we're in the right directory
if [ ! -d "../kismet-ai-ready-plugin" ]; then
    echo "❌ Error: Run this script from testing/prebuild/ directory"
    exit 1
fi

cd ..

# 1. PHP Syntax Check
echo ""
echo "1️⃣ PHP Syntax Validation..."
echo "-----------------------------"
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

# 2. WordPress Plugin Check
echo ""
echo "2️⃣ WordPress Plugin Check..."
echo "-----------------------------"
if command -v wp &> /dev/null; then
    if wp help plugin check > /dev/null 2>&1; then
        echo "Running official WordPress Plugin Check..."
        wp plugin check kismet-ai-ready-plugin --format=table
    else
        echo "⚠️ WordPress Plugin Check not available"
        echo "Install with: wp plugin install plugin-check --activate"
    fi
else
    echo "⚠️ WP-CLI not found. Install with: brew install wp-cli"
fi

# 3. File Structure Check  
echo ""
echo "3️⃣ File Structure Validation..."
echo "-------------------------------"
required_files=(
    "kismet-ai-ready-plugin/kismet-ai-ready-plugin.php"
    "kismet-ai-ready-plugin/includes/"
    "kismet-ai-ready-plugin/README.md"
)

for file in "${required_files[@]}"; do
    if [ -e "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
        exit 1
    fi
done

# 4. Security Scan
echo ""
echo "4️⃣ Security Scanning..."
echo "-----------------------"
dangerous_funcs=$(grep -r "eval\|exec\|system\|shell_exec\|passthru" kismet-ai-ready-plugin/ | wc -l | tr -d ' ')
if [ $dangerous_funcs -eq 0 ]; then
    echo "✅ No dangerous functions found"
else
    echo "⚠️ Found potentially dangerous functions:"
    grep -r "eval\|exec\|system\|shell_exec\|passthru" kismet-ai-ready-plugin/
fi

echo ""
echo "🎉 Prebuild validation complete!"
echo ""
echo "✅ Ready for local testing phase"
echo "📖 Next: cd ../local && follow Local testing guide" 