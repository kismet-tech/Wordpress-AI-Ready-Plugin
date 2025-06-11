# Plugin Decision Flow Analysis - 429 Error Debugging

This folder contains **corrected flowcharts** with clear yes/no decision points for debugging the 429 errors.

## 📊 Flowchart Files

### 1. [Plugin Lifecycle](05-plugin-lifecycle.md)

WordPress plugin activation/deactivation lifecycle and when rewrite rules are registered.

### 2. [Request Routing Reality](06-request-routing-reality.md)

Shows the actual path: Web server → .htaccess → WordPress → Plugin handlers.

### 3. ⭐ [Clear Decision Flow](07-clear-decision-flow.md)

**MAIN DEBUGGING TOOL** - Clear yes/no questions with specific commands to run.

### 4. 🎯 [Actual Problem Diagnosis](08-actual-problem-diagnosis.md)

**THE ANSWER** - Code analysis reveals the real cause and solution.

## 🚨 **Key Discovery From Code Analysis:**

### ✅ **AI Plugin Handler is CLEAN**

- **NO** rewrite rules registered for `/.well-known/ai-plugin.json`
- Only generates static files via `init` hook
- Should work perfectly IF .htaccess doesn't interfere

### ⚠️ **Other Handlers DO Register Rewrite Rules**

- **MCP Servers**: `\.well-known/mcp/servers\.json` → WordPress
- **LLMS.txt**: `llms\.txt` → WordPress

### 🔍 **The Real Question:**

**Do standard WordPress .htaccess rules intercept our static files?**

Standard WordPress .htaccess:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule . /index.php [L]
```

**If the file detection fails**, requests go to WordPress → `init` hooks fire → 15+ DB calls → 429 errors.

## 🎯 **Next Steps:**

1. Run the debugging commands in [Clear Decision Flow](07-clear-decision-flow.md)
2. Check if .htaccess is intercepting static files
3. Add `.well-known` exception if needed

The flowcharts now have **proper yes/no questions** and **specific debugging commands** for each decision point.
