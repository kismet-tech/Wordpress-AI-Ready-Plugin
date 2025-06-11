<?php
/**
 * Ask Handler - Request Handling ONLY
 *
 * This class handles ONLY requests to /ask endpoint
 * It NEVER runs on unrelated page loads. NO init hooks.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Handler {
    
    /**
     * Initialize handler - hooks ONLY to template_redirect
     */
    public function __construct() {
        // ONLY hook to template_redirect for efficiency
        add_action('template_redirect', array($this, 'handle_ask_request'));
    }
    
    /**
     * Handle /ask endpoint request
     * Exits immediately if not our request to avoid any overhead
     */
    public function handle_ask_request() {
        // Fast path - exit immediately if not /ask request
        if (!$this->is_ask_request()) {
            return;
        }
        
        error_log("KISMET HANDLER: /ask endpoint request detected");
        
        // Check for proper request method
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'OPTIONS') {
            $this->handle_cors_preflight();
            exit;
        }
        
        if ($method === 'POST') {
            $this->handle_ask_post();
        } else {
            $this->handle_ask_get();
        }
    }
    
    /**
     * Check if this is an /ask request
     */
    private function is_ask_request() {
        // Use WordPress query var that was set by installer
        global $wp_query;
        return !empty($wp_query->query_vars['kismet_ask_endpoint']);
    }
    
    /**
     * Handle CORS preflight
     */
    private function handle_cors_preflight() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        status_header(200);
    }
    
    /**
     * Handle POST request to /ask
     */
    private function handle_ask_post() {
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // Get and validate input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['message'])) {
                throw new Exception('Invalid request: message is required');
            }
            
            $user_message = sanitize_text_field($input['message']);
            
            if (empty($user_message)) {
                throw new Exception('Message cannot be empty');
            }
            
            // Process the chat request
            $response = $this->process_chat_request($user_message, $input);
            
            // Return JSON response
            echo json_encode($response);
            
        } catch (Exception $e) {
            status_header(400);
            echo json_encode(array(
                'error' => $e->getMessage(),
                'status' => 'error'
            ));
        }
        
        exit;
    }
    
    /**
     * Handle GET request to /ask (documentation/info)
     */
    private function handle_ask_get() {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        
        $info = array(
            'service' => 'Kismet Hotel Assistant',
            'description' => 'AI-powered hotel information and booking assistance',
            'version' => '1.0',
            'endpoints' => array(
                'POST /ask' => 'Submit a question or request',
                'GET /ask' => 'This information endpoint'
            ),
            'usage' => array(
                'method' => 'POST',
                'content_type' => 'application/json',
                'body' => array(
                    'message' => 'Your question here',
                    'session_id' => 'optional-session-identifier'
                )
            ),
            'status' => 'active'
        );
        
        echo json_encode($info, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Process chat request
     */
    private function process_chat_request($user_message, $input) {
        // Generate session ID if not provided
        $session_id = $input['session_id'] ?? uniqid('chat_', true);
        
        // Call Kismet backend API
        $assistant_response = $this->call_kismet_backend($user_message, $session_id, $input);
        
        // Log the conversation to database
        // $this->log_conversation($session_id, $user_message, $assistant_response);
        
        return array(
            'response' => $assistant_response,
            'session_id' => $session_id,
            'timestamp' => current_time('c'),
            'status' => 'success'
        );
    }
    
    /**
     * Call Kismet backend API
     */
    private function call_kismet_backend($message, $session_id, $input = array()) {
        $backend_url = 'https://api.makekismet.com/ask';
        
        // Prepare request payload
        $payload = array(
            'query' => $message,
            'session_id' => $session_id,
            'source' => 'wordpress_plugin',
            'site_url' => home_url(),
            'timestamp' => current_time('c')
        );
        
        // Add any additional input data
        if (!empty($input['context'])) {
            $payload['context'] = $input['context'];
        }
        
        // Make HTTP POST request
        $response = wp_remote_post($backend_url, array(
            'timeout' => 60, // Increased timeout for SSE stream
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Kismet-WordPress-Plugin/1.0',
                'Accept' => 'text/event-stream'
            ),
            'body' => json_encode($payload)
        ));
        
        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log("KISMET ERROR: Backend request failed: " . $response->get_error_message());
            throw new Exception('Unable to connect to AI service. Please try again later.');
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle non-200 responses
        if ($response_code !== 200 && $response_code !== 201) {
            error_log("KISMET ERROR: Backend returned status {$response_code}: {$response_body}");
            throw new Exception('AI service temporarily unavailable. Please try again later.');
        }
        
        // Parse SSE stream response
        return $this->parse_sse_response($response_body);
    }
    
    /**
     * Parse Server-Sent Events response from Kismet API
     */
    private function parse_sse_response($sse_data) {
        $lines = explode("\n", $sse_data);
        $results = array();
        $is_complete = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and non-data lines
            if (empty($line) || !str_starts_with($line, 'data: ')) {
                continue;
            }
            
            // Extract JSON from data line
            $json_data = substr($line, 6); // Remove "data: " prefix
            $data = json_decode($json_data, true);
            
            if (!$data || !isset($data['message_type'])) {
                continue;
            }
            
            switch ($data['message_type']) {
                case 'result_batch':
                    if (isset($data['results']) && is_array($data['results'])) {
                        $results = array_merge($results, $data['results']);
                    }
                    break;
                    
                case 'complete':
                    $is_complete = true;
                    break;
            }
        }
        
        // Generate a conversational response from the search results
        return $this->format_results_as_response($results, $is_complete);
    }
    
    /**
     * Format search results into a conversational response
     */
    private function format_results_as_response($results, $is_complete) {
        if (empty($results)) {
            return "I apologize, but I couldn't find specific information to answer your question. Please feel free to contact our hotel directly for assistance.";
        }
        
        // Take the most relevant results (limit to top 3-5)
        $top_results = array_slice($results, 0, 5);
        
        // Extract key information
        $response_parts = array();
        $sources = array();
        
        foreach ($top_results as $result) {
            if (isset($result['schema_object']['acceptedAnswer']['text'])) {
                $answer = $result['schema_object']['acceptedAnswer']['text'];
                $response_parts[] = $answer;
                
                if (isset($result['name'])) {
                    $sources[] = $result['name'];
                }
            }
        }
        
        // Combine responses into a coherent answer
        if (!empty($response_parts)) {
            $combined_response = implode("\n\n", array_unique($response_parts));
            
            // Add source attribution if available
            if (!empty($sources)) {
                $combined_response .= "\n\nThis information is based on our current knowledge and policies. For the most up-to-date details, please contact us directly.";
            }
            
            return $combined_response;
        }
        
        return "I found some information but couldn't format a complete response. Please contact our hotel directly for detailed assistance.";
    }
    
    /**
     * Log conversation to database
     */
    private function log_conversation($session_id, $user_message, $assistant_response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kismet_chat_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'user_message' => $user_message,
                'assistant_response' => $assistant_response,
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'status' => 'completed'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($wpdb->last_error) {
            error_log("KISMET ERROR: Failed to log conversation: " . $wpdb->last_error);
        }
    }
} 