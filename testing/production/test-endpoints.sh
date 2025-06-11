#!/bin/bash

# Test script for Kismet WordPress Plugin endpoint tracking
# This script hits all tracked endpoints to generate tracking events

BASE_URL="https://theknollcroft.com"

echo "🔥 Testing Kismet WordPress Plugin Endpoint Tracking"
echo "=================================================="
echo "Base URL: $BASE_URL"
echo "Time: $(date)"
echo ""

# Function to test an endpoint
test_endpoint() {
    local endpoint="$1"
    local description="$2"
    
    echo "Testing: $endpoint ($description)"
    echo "curl -I \"$BASE_URL$endpoint\""
    
    response=$(curl -I -s "$BASE_URL$endpoint" 2>/dev/null)
    status_line=$(echo "$response" | head -n 1)
    
    echo "Response: $status_line"
    echo "---"
    echo ""
    
    # Small delay between requests
    sleep 1
}

# Test all tracked endpoints
echo "🤖 Testing AI Bot Endpoints:"
test_endpoint "/robots.txt" "Robots file for web crawlers"
test_endpoint "/llms.txt" "LLM training data instructions"

echo "🔌 Testing AI Plugin Endpoints:"
test_endpoint "/.well-known/ai-plugin.json" "OpenAI plugin manifest"
test_endpoint "/.well-known/mcp/servers.json" "Model Context Protocol servers"

echo "💬 Testing Chat Endpoint:"    
test_endpoint "/ask" "Main chat/ask endpoint"

echo "✅ All endpoint tests completed!"
echo ""
echo "📋 Check WordPress error logs for tracking debug output:"
echo "Look for lines starting with 'KISMET DEBUG:'"
echo ""
echo "🔍 Backend events should appear in your ngrok/API logs" 