# Production Testing - Are Things Working?

**Purpose:** Verify all endpoints work on your live site.

## Quick Endpoint Tests

### 1. robots.txt Check

```bash
curl -s "https://yoursite.com/robots.txt" | grep -A 5 "Kismet"
```

**Should show:**

```
# Kismet AI integration
User-agent: *
Allow: /ask
Allow: /.well-known/ai-plugin.json
```

### 2. AI Plugin JSON Check

```bash
curl -s "https://yoursite.com/.well-known/ai-plugin.json" | jq .
```

**Should return:** Valid JSON with your site info

### 3. Ask Endpoint - Human Mode

```bash
curl -H "Accept: text/html" "https://yoursite.com/ask"
```

**Should return:** HTML page or form

### 4. Ask Endpoint - API Mode

```bash
curl -X POST "https://yoursite.com/ask" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"query": "what are your hours?", "streaming": false}'
```

**Should return:** JSON response

## Automated Test Script

**Use existing `test-endpoints.sh`:**

```bash
./test-endpoints.sh
```

## .htaccess Setup (if needed)

**Use existing setup tool:**

```bash
# Upload test-htaccess-setup.php to site root
# Visit: https://yoursite.com/test-htaccess-setup.php
```

## Success Criteria

- ✅ robots.txt shows Kismet entries
- ✅ AI plugin JSON endpoint returns valid data
- ✅ Ask endpoint responds in both HTML and JSON modes
- ✅ No 404 or 500 errors

**All working?** ✅ Plugin is live and ready!
