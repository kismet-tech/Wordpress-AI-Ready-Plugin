# Shared Utilities

This folder contains utility classes that are used by multiple endpoints throughout the plugin.

## Purpose

Shared utilities provide common functionality that multiple endpoints need:

- File safety management for conflict-free operations
- Route testing for deployment verification
- Cross-cutting concerns that shouldn't be duplicated

## Files

- `class-file-safety-manager.php` - Safe file creation and conflict resolution
- `class-route-tester.php` - Route accessibility testing and verification

## File Safety Manager

### Purpose

Provides bulletproof file creation and management to avoid conflicts with existing files or hosting restrictions.

### Key Features

- **Content Analysis**: Compares existing file content before overwriting
- **Backup Creation**: Automatic backups of files before modification
- **Conflict Detection**: Identifies potential issues before they occur
- **Rollback Support**: Can restore original files if needed
- **Metadata Tracking**: Keeps records of all file operations

### Usage

```php
$manager = new Kismet_File_Safety_Manager();
$result = $manager->safe_file_create(
    '/path/to/file.txt',
    $content,
    Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
);
```

### Policies

- `POLICY_NEVER_OVERWRITE`: Never replace existing files
- `POLICY_CONTENT_ANALYSIS`: Compare content before deciding
- `POLICY_BACKUP_FIRST`: Always backup before replacing
- `POLICY_FORCE_REPLACE`: Replace regardless of existing content

## Route Tester

### Purpose

Tests whether specific routes and endpoints are accessible before attempting to create them, preventing deployment failures.

### Key Features

- **Accessibility Testing**: Verifies routes can be reached
- **Response Validation**: Checks expected response codes and content
- **Conflict Detection**: Identifies existing routes that might interfere
- **Performance Monitoring**: Measures response times
- **Diagnostic Reporting**: Detailed reports on route status

### Usage

```php
$tester = new Kismet_Route_Tester();
$result = $tester->test_route('/test-endpoint');
if ($result['accessible']) {
    // Proceed with endpoint creation
}
```

### Test Types

- **HTTP Accessibility**: Can the route be reached?
- **Content Validation**: Does it return expected content?
- **Performance Check**: Response time within acceptable limits?
- **Security Verification**: No security warnings or blocks?

## Integration

These utilities are designed to work together:

1. Route Tester verifies endpoint accessibility
2. File Safety Manager handles safe file operations
3. Both provide detailed logging for troubleshooting

## Error Handling

Both utilities provide comprehensive error reporting:

- Detailed error messages
- Context information
- Suggested remediation steps
- Diagnostic data for support

## Contributing

When working with shared utilities:

1. Maintain backward compatibility
2. Add comprehensive test coverage
3. Document new policies and options
4. Update integration examples
5. Test with all dependent endpoints
