# Environment Detection System

This folder contains a simplified environment detection system focused on essential functionality.

## Purpose

The environment detection system provides:

- Server detection and strategy selection (via `class-server-detector.php`)
- Real endpoint testing with HTTP requests (via `class-endpoint-tester.php`)
- Simple activation-time logging

## Files

- `class-server-detector.php` - **Core server detection, strategy selection, and hosting environment analysis**
- `class-endpoint-tester.php` - **Real HTTP endpoint testing and validation**
- `README.md` - This documentation

## Main Components

### Server Detector

**File**: `class-server-detector.php`

The main server detection system that powers strategy selection:

- Detects server type (Apache, Nginx, IIS, LiteSpeed)
- Analyzes hosting environment (shared, managed, VPS, cloud)
- Determines file system permissions and capabilities
- Provides ordered strategy recommendations for each endpoint
- Used by the main plugin for all server-related decisions

### Endpoint Tester

**File**: `class-endpoint-tester.php`

Real HTTP testing for endpoint validation:

- Makes actual HTTP requests to test endpoint accessibility
- Validates response content (JSON format, CORS headers, etc.)
- Reports real-world endpoint status
- Used by admin dashboard and plugin notices
- Provides accurate "is it working?" information

## Detection Workflow

1. **Server Analysis**

   - Server type detection via `Server_Detector`
   - Strategy selection based on server capabilities
   - Hosting environment assessment

2. **Endpoint Testing**

   - Real HTTP requests via `Endpoint_Tester`
   - Response validation and content checking
   - Status reporting for admin interface

3. **Strategy Execution**
   - Ordered strategy attempts based on server detection
   - Fallback to alternative strategies on failure
   - Real-time status updates

## Usage

### Server Detection

```php
$server_detector = new Kismet_Server_Detector();
$server_info = $server_detector->get_server_info();
$strategies = $server_detector->get_endpoint_strategies($endpoint_path);
```

### Endpoint Testing

```php
$endpoint_tester = new Kismet_Endpoint_Tester();
$test_result = $endpoint_tester->test_real_endpoint($url);
$all_statuses = $endpoint_tester->get_endpoint_status_summary();
```

## Integration Points

The system integrates with:

- Main plugin class for strategy selection
- Admin dashboard for status display
- Plugin notices for user feedback
- Strategy implementation system

## Simplified Architecture

This system has been simplified to focus on:

- **Essential functionality only**
- **Real-world testing over theoretical checks**
- **Clean separation of concerns**
- **Performance-focused implementation**

The complex multi-class orchestration has been removed in favor of direct usage of the two core classes where needed.
