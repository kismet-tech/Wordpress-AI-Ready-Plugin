# MCP Servers Endpoint

This endpoint provides the `/.well-known/mcp/servers.json` file for Model Context Protocol (MCP) server discovery and configuration.

## Purpose

The MCP Servers endpoint enables:

- Discovery of available MCP servers
- Configuration of AI assistant integrations
- Server capability announcements
- Authentication and connection details

## Files

- `class-mcp-servers-handler.php` - Main handler implementing MCP server discovery

## URL

- `/.well-known/mcp/servers.json` - Standard MCP discovery endpoint

## MCP Overview

Model Context Protocol (MCP) is a standard that allows AI assistants to:

- Connect to external data sources
- Execute server-side functions
- Access real-time information
- Perform complex operations beyond text generation

## JSON Structure

```json
{
  "servers": [
    {
      "name": "kismet-hotel-server",
      "description": "Hotel booking and information server",
      "url": "https://mcp.ksmt.app/sse",
      "transport": {
        "type": "sse",
        "endpoint": "https://mcp.ksmt.app/sse"
      },
      "capabilities": ["tools", "resources", "prompts"],
      "authentication": {
        "type": "none"
      }
    }
  ]
}
```

## Server Capabilities

### Tools

- Room availability checks
- Booking creation and management
- Pricing calculations
- Amenity information

### Resources

- Hotel property data
- Room type information
- Policies and terms
- Contact details

### Prompts

- Booking assistance templates
- Customer service responses
- Upselling suggestions
- FAQ responses

## Transport Types

- **SSE (Server-Sent Events)**: Real-time streaming connection
- **HTTP**: Standard request/response
- **WebSocket**: Bidirectional real-time communication

## Authentication

Current implementation uses:

- `type: "none"` - No authentication required
- Future versions may support API keys or OAuth

## Integration

This endpoint enables AI assistants to:

1. Discover available MCP servers
2. Establish connections using specified transport
3. Access hotel-specific tools and data
4. Provide enhanced booking assistance

## Configuration

Server information is configured through:

- WordPress admin settings
- Environment variables
- MCP server deployment configuration
- Capability definitions

## Contributing

When working on this endpoint:

1. Follow MCP specification standards
2. Test with MCP-compatible AI assistants
3. Validate JSON schema compliance
4. Ensure transport endpoints are accessible
5. Document new capabilities and tools
6. Test authentication flows if implemented
