# AI Plugin Endpoint

This endpoint provides the `/.well-known/ai-plugin.json` file that AI assistants (like ChatGPT) use to discover and interact with your site's AI capabilities.

## Purpose

The AI Plugin endpoint follows the OpenAI plugin specification to:

- Announce your site's AI-ready endpoints to AI assistants
- Provide metadata about your hotel/business
- Configure authentication methods (currently set to "none")
- Specify the OpenAPI endpoint for AI interactions

## Files

- `class-ai-plugin-handler.php` - Main implementation using WordPress rewrite rules
- `class-ai-plugin-handler-safe.php` - Bulletproof implementation with comprehensive safety checks

## URL

- `/.well-known/ai-plugin.json` - Standard AI plugin discovery endpoint

## Configuration

The endpoint can be configured through WordPress admin settings:

- **Custom URL**: Proxy to an external ai-plugin.json file
- **Hotel Name**: Business name for AI interactions
- **Description**: What services your AI can help with
- **Logo URL**: Visual branding for AI interfaces
- **Contact Email**: Support contact for AI-related issues
- **Legal Info URL**: Terms/privacy policy link

## JSON Structure

```json
{
  "schema_version": "v1",
  "name_for_human": "Hotel Name AI Assistant",
  "name_for_model": "hotel_assistant",
  "description_for_human": "Get information about the hotel...",
  "description_for_model": "Provides hotel information...",
  "auth": {
    "type": "none"
  },
  "api": {
    "type": "openapi",
    "url": "https://yoursite.com/ask"
  },
  "logo_url": "https://yoursite.com/logo.png",
  "contact_email": "info@yoursite.com",
  "legal_info_url": "https://yoursite.com/privacy-policy"
}
```

## Implementation Approaches

### Standard Handler

- Uses WordPress rewrite rules and hooks
- Lighter weight implementation
- Good for most environments

### Safe Handler

- Tests route accessibility before creating anything
- Uses file safety manager for conflict resolution
- Comprehensive error handling and rollback
- Diagnostic logging for troubleshooting
- Recommended for complex hosting environments

## Contributing

When modifying this endpoint:

1. Test both physical file and WordPress rewrite approaches
2. Ensure JSON validates against OpenAI plugin schema
3. Update admin settings if adding new configuration options
4. Test with actual AI assistants when possible
