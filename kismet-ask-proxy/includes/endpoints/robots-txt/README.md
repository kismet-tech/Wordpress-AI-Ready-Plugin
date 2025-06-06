# Robots Endpoint

This endpoint enhances the existing `/robots.txt` file to announce AI-related endpoints and policies to web crawlers and AI systems.

## Purpose

The Robots endpoint:

- Extends WordPress's default robots.txt functionality
- Announces AI endpoints to discovery systems
- Provides AI-specific crawling guidelines
- Maintains compatibility with existing robots.txt standards

## Files

- `class-robots-handler.php` - Main handler for robots.txt enhancements

## URL

- `/robots.txt` - Standard robots file (enhanced, not replaced)

## Enhanced Content

The handler adds sections to robots.txt:

```
# Standard robots.txt content (preserved)
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

# AI/LLM Discovery Section (added by Kismet)
# AI Endpoints and Policies
Sitemap: https://example.com/sitemap.xml

# AI-Related Endpoints
# Well-known endpoints for AI discovery
# /.well-known/ai-plugin.json
# /.well-known/mcp/servers.json
# /llms.txt
# /ask

# AI Crawling Guidelines
# This site welcomes responsible AI access
# Please respect rate limits and server capacity
# Contact: admin@example.com for bulk access

# Last updated: 2024-06-06
```

## Key Features

### Non-Destructive

- Preserves existing robots.txt content
- Adds AI-specific sections without conflicts
- Maintains WordPress compatibility

### Discovery Enhancement

- Announces AI endpoints to crawlers
- Provides contact information for AI systems
- Links to policy and configuration files

### SEO Integration

- Maintains standard SEO robots directives
- Adds sitemap announcements
- Preserves search engine crawling rules

## Implementation

The handler uses WordPress's `robots_txt` filter to:

1. Capture existing robots.txt content
2. Append AI-specific sections
3. Ensure proper formatting and compatibility
4. Cache for performance

## Configuration

Enhancement content is generated from:

- WordPress site settings
- Plugin configuration
- Available endpoint detection
- Admin contact information

## Standards Compliance

This implementation follows:

- Standard robots.txt protocol (RFC 9309)
- Emerging AI discovery conventions
- SEO best practices
- Web accessibility guidelines

## Contributing

When modifying this endpoint:

1. Test compatibility with existing robots.txt
2. Validate robots.txt syntax
3. Ensure SEO directives are preserved
4. Test with various crawlers and AI systems
5. Maintain backward compatibility
