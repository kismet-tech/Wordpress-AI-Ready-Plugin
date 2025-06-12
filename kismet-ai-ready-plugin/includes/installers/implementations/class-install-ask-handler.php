<?php
/**
 * Ask Handler Building Block
 * 
 * Integrates the Ask Handler into the composable strategy system.
 * Sets up WordPress rewrite rules and connects the Ask Handler for dual functionality:
 * - GET requests: API information endpoint
 * - POST requests: API proxy to external services
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Ask_Handler {
    
    /**
     * Execute Ask Handler setup
     * 
     * @param string $endpoint_path Endpoint path (should be '/ask')
     * @param array $endpoint_data Data needed for the endpoint
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute($endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            $result = array(
                'success' => false,
                'building_block' => 'ask_handler',
                'details' => array()
            );
            
            // Step 1: Set up WordPress rewrite rules for /ask
            $rewrite_result = self::setup_ask_rewrite_rules();
            if (!$rewrite_result['success']) {
                return $rewrite_result;
            }
            $result['details']['rewrite'] = $rewrite_result;
            
            // Step 2: Initialize the Ask Handler
            $handler_result = self::initialize_ask_handler();
            $result['details']['handler'] = $handler_result;
            
            // Step 3: Set up database tables if needed
            $database_result = self::setup_database_tables();
            $result['details']['database'] = $database_result;
            
            // Step 4: Flush rewrite rules to activate
            flush_rewrite_rules();
            
            $result['success'] = true;
            $result['message'] = "Ask Handler building block executed successfully";
            $result['endpoint_url'] = get_site_url() . '/ask';
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Ask Handler building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Set up WordPress rewrite rules for /ask endpoint
     * 
     * @return array Result with rewrite rule details
     */
    private static function setup_ask_rewrite_rules() {
        // Add rewrite rule for /ask endpoint
        add_rewrite_rule('^ask/?$', 'index.php?kismet_ask_endpoint=1', 'top');
        
        // Add query var filter - this needs to be PERSISTENT, not just during activation
        add_filter('query_vars', array(self::class, 'add_ask_query_vars'));
        
        // Add template redirect hook - this needs to be PERSISTENT, not just during activation  
        add_action('template_redirect', array(self::class, 'handle_ask_template_redirect'));
        
        return array(
            'success' => true,
            'rewrite_rule' => '^ask/?$',
            'query_var' => 'kismet_ask_endpoint',
            'target' => 'index.php?kismet_ask_endpoint=1'
        );
    }
    
    /**
     * Add query vars for Ask endpoint (runs on every request)
     * 
     * @param array $vars Current query vars
     * @return array Modified query vars
     */
    public static function add_ask_query_vars($vars) {
        if (!in_array('kismet_ask_endpoint', $vars)) {
            $vars[] = 'kismet_ask_endpoint';
        }
        return $vars;
    }
    
    /**
     * Handle Ask endpoint template redirect (runs on every request)
     * 
     * This is the persistent hook that processes /ask requests on every page load
     */
    public static function handle_ask_template_redirect() {
        // Check if this is an /ask request
        if (!get_query_var('kismet_ask_endpoint')) {
            return; // Not our request, bail early
        }
        
        error_log("KISMET HANDLER: /ask endpoint request detected");
        
        // Check for proper request method
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'OPTIONS') {
            self::handle_cors_preflight();
            exit;
        }
        
        if ($method === 'POST') {
            self::handle_ask_post();
        } else {
            self::handle_ask_get();
        }
    }
    
    /**
     * Handle CORS preflight
     */
    private static function handle_cors_preflight() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        status_header(200);
    }
    
    /**
     * Handle POST request to /ask
     */
    private static function handle_ask_post() {
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
            $response = self::process_chat_request($user_message, $input);
            
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
     * Handle GET request to /ask (HTML chat interface)
     */
    private static function handle_ask_get() {
        header('Content-Type: text/html; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        
        // Load the Ask Content Logic to generate the HTML page
        $content_logic_file = plugin_dir_path(__FILE__) . '../endpoint-content-logic/class-ask-content-logic.php';
        if (file_exists($content_logic_file)) {
            require_once $content_logic_file;
        }
        
        // Generate and output the HTML content
        if (class_exists('Kismet_Ask_Content_Logic')) {
            echo Kismet_Ask_Content_Logic::generate_ask_content();
        } else {
            // Fallback error response
            status_header(500);
            echo '<h1>Ask Endpoint Unavailable</h1><p>Content logic not found.</p>';
        }
        exit;
    }
    
    /**
     * Process chat request
     */
    private static function process_chat_request($user_message, $input) {
        // Generate session ID if not provided
        $session_id = $input['session_id'] ?? uniqid('chat_', true);
        
        // Call Kismet backend API
        $assistant_response = self::call_kismet_backend($user_message, $session_id, $input);
        
        // Log the conversation to database (optional)
        // self::log_conversation($session_id, $user_message, $assistant_response);
        
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
    private static function call_kismet_backend($message, $session_id, $input = array()) {
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
        return self::parse_sse_response($response_body);
    }
    
    /**
     * Parse Server-Sent Events response from Kismet API
     */
    private static function parse_sse_response($sse_data) {
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
        return self::format_results_as_response($results, $is_complete);
    }
    
    /**
     * Format search results into a conversational response
     */
    private static function format_results_as_response($results, $is_complete) {
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
     * Validate Ask Handler availability
     * 
     * @return array Result with handler validation details
     */
    private static function initialize_ask_handler() {
        // Check that required content logic file exists
        $content_logic_file = plugin_dir_path(__FILE__) . '../endpoint-content-logic/class-ask-content-logic.php';
        
        $content_logic_exists = file_exists($content_logic_file);
        
        if (!$content_logic_exists) {
            return array(
                'success' => false,
                'error' => 'Required Ask Content Logic file not found',
                'content_logic_file_exists' => $content_logic_exists
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Ask Handler building block validated and persistent hooks registered',
            'content_logic_file' => $content_logic_file,
            'handler_integrated' => true,
            'hooks_registered' => array(
                'query_vars' => 'add_ask_query_vars',
                'template_redirect' => 'handle_ask_template_redirect'
            )
        );
    }
    
    /**
     * Set up database tables for chat logging
     * 
     * @return array Result with database setup details
     */
    private static function setup_database_tables() {
        // Use the existing database setup from Ask Content Logic
        if (class_exists('Kismet_Ask_Content_Logic')) {
            Kismet_Ask_Content_Logic::create_database_tables();
            
            return array(
                'success' => true,
                'tables_created' => true,
                'table_name' => 'kismet_chat_logs'
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Kismet_Ask_Content_Logic class not found for database setup'
            );
        }
    }
    
    /**
     * Test the Ask endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @param object $plugin_instance Main plugin instance
     * @return array Test result
     */
    public static function test_endpoint($endpoint_path, $plugin_instance) {
        $test_url = get_site_url() . '/ask';
        
        // Test GET request (should return API info)
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Kismet Plugin Test',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'test_url' => $test_url
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check if response looks like HTML page with chat interface
        $is_html = (strpos($response_body, '<!DOCTYPE html>') !== false);
        $has_chat_interface = (strpos($response_body, 'AI Ready Checklist') !== false);
        $has_site_name = (strpos($response_body, get_bloginfo('name')) !== false);
        
        return array(
            'success' => ($response_code === 200 && $is_html && $has_chat_interface),
            'response_code' => $response_code,
            'is_html' => $is_html,
            'has_chat_interface' => $has_chat_interface,
            'has_site_name' => $has_site_name,
            'response_preview' => substr($response_body, 0, 200),
            'test_url' => $test_url
        );
    }
    
    /**
     * Cleanup Ask Handler setup
     * 
     * @param string $endpoint_path Endpoint path to cleanup
     * @return array Result with success status
     */
    public static function cleanup($endpoint_path) {
        // Remove query var filter (WordPress will handle rewrite rule cleanup on flush)
        remove_all_filters('query_vars');
        
        // Flush rewrite rules to remove our rule
        flush_rewrite_rules();
        
        return array(
            'success' => true,
            'message' => 'Ask Handler cleanup completed',
            'endpoint_path' => $endpoint_path
        );
    }
} 