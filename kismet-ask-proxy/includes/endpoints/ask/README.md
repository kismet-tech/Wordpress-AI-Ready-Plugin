# Ask Endpoint

This is the core API endpoint (`/ask`) that handles AI assistant requests and serves both API clients and human visitors with Kismet branding.

## Purpose

The Ask endpoint serves as the main interaction point for:

- AI assistants making programmatic requests
- Human visitors accessing the page directly
- OpenAPI specification discovery for AI integrations

## Files

- `class-ask-handler.php` - Main handler implementing the /ask endpoint

## URL

- `/ask` - Main API endpoint for AI interactions

## Functionality

### API Mode (for AI Assistants)

- Accepts JSON requests from AI assistants
- Processes hotel/booking inquiries
- Returns structured JSON responses
- Integrates with backend booking systems

### Human Mode (for Web Visitors)

- Displays Kismet-branded interface
- Provides user-friendly form for inquiries
- Shows contact information and branding
- Responsive design for all devices

### OpenAPI Discovery

- Serves OpenAPI specification when requested
- Enables AI assistants to understand available endpoints
- Documents request/response formats

## Request/Response Format

### API Requests

```json
{
  "message": "What rooms are available this weekend?",
  "context": {
    "checkin": "2024-06-15",
    "checkout": "2024-06-17",
    "guests": 2
  }
}
```

### API Responses

```json
{
  "response": "We have several rooms available...",
  "data": {
    "rooms": [...],
    "pricing": {...}
  }
}
```

## Integration Points

- Backend booking system APIs
- Hotel management systems
- Payment processing (for bookings)
- Email notifications
- Customer relationship management

## Configuration

The endpoint behavior can be configured through:

- WordPress admin settings
- Environment variables
- Hotel-specific customizations
- Branding and styling options

## Contributing

When working on this endpoint:

1. Maintain compatibility with both API and human interfaces
2. Test with actual AI assistants (ChatGPT, etc.)
3. Ensure OpenAPI spec stays current with implementation
4. Validate booking system integrations
5. Test responsive design across devices
