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
            
            // Step 3: Track which strategy we're using
            $strategy_result = self::track_strategy_status();
            $result['details']['strategy'] = $strategy_result;
            
            // Step 4: Mark that this endpoint needs rewrite rules flushed
            $result['details']['rewrite_rules'] = 'Pending flush';
            
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
        error_log('KISMET DEBUG: Setting up /ask rewrite rules');
        
        // Get current rewrite rules
        global $wp_rewrite;
        error_log('KISMET DEBUG: Current rewrite rules: ' . print_r($wp_rewrite->rules, true));
        
        // Add rewrite rule for /ask endpoint with consistent query var
        add_rewrite_rule('^ask/?$', 'index.php?kismet_ask=1', 'top');
        
        // Add query var filter
        add_filter('query_vars', array(__CLASS__, 'add_ask_query_vars'));
        
        // Flush rewrite rules to ensure they take effect
        flush_rewrite_rules();
        
        // Get updated rewrite rules after flush
        error_log('KISMET DEBUG: Updated rewrite rules: ' . print_r($wp_rewrite->rules, true));
        
        return array(
            'success' => true,
            'rewrite_rule' => '^ask/?$',
            'query_var' => 'kismet_ask',
            'target' => 'index.php?kismet_ask=1'
        );
    }
    
    /**
     * Add query vars for Ask endpoint (runs on every request)
     * 
     * @param array $vars Current query vars
     * @return array Modified query vars
     */
    public static function add_ask_query_vars($query_vars) {
        error_log('KISMET DEBUG: Adding ask query vars');
        
        $query_vars[] = 'kismet_ask';
        
        return $query_vars;
    }
    
    /**
     * Handle Ask endpoint template redirect (runs on every request)
     * 
     * This is the persistent hook that processes /ask requests on every page load
     */
    public static function handle_ask_template_redirect() {
        global $wp_query;
        error_log('KISMET DEBUG: Template redirect check');
        error_log('KISMET DEBUG: Query vars: ' . print_r($wp_query->query_vars, true));
        
        if (isset($wp_query->query_vars['kismet_ask'])) {
            error_log('KISMET DEBUG: Found kismet_ask in query vars');
            
            // Load the Ask Content Logic to generate the HTML page
            $content_logic_file = plugin_dir_path(__FILE__) . '../../endpoint-content-logic/class-ask-content-logic.php';
            error_log('KISMET DEBUG: Looking for content logic at: ' . $content_logic_file);
            
            if (file_exists($content_logic_file)) {
                error_log('KISMET DEBUG: Content logic file found');
                require_once $content_logic_file;
                
                // Generate and output the HTML
                $content = Kismet_Ask_Content_Logic::generate_ask_content();
                error_log('KISMET DEBUG: Generated content length: ' . strlen($content));
                echo $content;
                exit;
            } else {
                error_log('KISMET DEBUG: Content logic file NOT found at: ' . $content_logic_file);
                status_header(500);
                echo '<h1>Ask Endpoint Error</h1><p>Content logic file not found. Please check the plugin installation.</p>';
                exit;
            }
        } else {
            error_log('KISMET DEBUG: kismet_ask not found in query vars');
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
        $content_logic_file = plugin_dir_path(__FILE__) . '../../endpoint-content-logic/class-ask-content-logic.php';
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
     * Initialize Ask Handler with proper hooks and query vars
     * 
     * @return array Result with handler validation details
     */
    private static function initialize_ask_handler() {
        try {
            error_log('KISMET DEBUG: Initializing Ask Handler');
            
            // Add query var filter with higher priority to ensure it runs
            add_filter('query_vars', function($vars) {
                error_log('KISMET DEBUG: Adding kismet_ask to query vars');
                if (!in_array('kismet_ask', $vars)) {
                    $vars[] = 'kismet_ask';
                }
                return $vars;
            }, 20);
            error_log('KISMET DEBUG: Added query_vars filter with priority 20');
            
            // Add rewrite rule with high priority
            add_action('init', function() {
                error_log('KISMET DEBUG: Adding rewrite rule for /ask');
                add_rewrite_rule('^ask/?$', 'index.php?kismet_ask=1', 'top');
            }, 20);
            error_log('KISMET DEBUG: Added rewrite rule with priority 20');
            
            // Add template redirect action with high priority
            add_action('template_redirect', function() {
                global $wp_query;
                error_log('KISMET DEBUG: Template redirect check for /ask');
                error_log('KISMET DEBUG: Query vars: ' . print_r($wp_query->query_vars, true));
                
                if (isset($wp_query->query_vars['kismet_ask'])) {
                    error_log('KISMET DEBUG: Found kismet_ask in query vars');
                    
                    // Load the Ask Content Logic to generate the HTML page
                    $content_logic_file = plugin_dir_path(__FILE__) . '../../endpoint-content-logic/class-ask-content-logic.php';
                    error_log('KISMET DEBUG: Looking for content logic at: ' . $content_logic_file);
                    
                    if (file_exists($content_logic_file)) {
                        error_log('KISMET DEBUG: Content logic file found');
                        require_once $content_logic_file;
                        
                        // Generate and output the HTML
                        $content = Kismet_Ask_Content_Logic::generate_ask_content();
                        error_log('KISMET DEBUG: Generated content length: ' . strlen($content));
                        echo $content;
                        exit;
                    } else {
                        error_log('KISMET DEBUG: Content logic file NOT found at: ' . $content_logic_file);
                        status_header(500);
                        echo '<h1>Ask Endpoint Error</h1><p>Content logic file not found. Please check the plugin installation.</p>';
                        exit;
                    }
                } else {
                    error_log('KISMET DEBUG: kismet_ask not found in query vars');
                }
            }, 1);
            error_log('KISMET DEBUG: Added template_redirect action with priority 1');
            
            // Add REST API endpoint for POST requests
            add_action('rest_api_init', function() {
                register_rest_route('kismet/v1', '/ask', array(
                    'methods' => 'POST',
                    'callback' => array(__CLASS__, 'handle_ask_post'),
                    'permission_callback' => '__return_true'
                ));
            });
            error_log('KISMET DEBUG: Added REST API endpoint');
            
            return array(
                'success' => true,
                'message' => 'Ask Handler initialized with query vars and template redirect hooks',
                'hooks_added' => array(
                    'query_vars' => 'add_ask_query_vars',
                    'template_redirect' => 'handle_ask_template_redirect',
                    'rest_api' => '/kismet/v1/ask'
                )
            );
            
        } catch (Exception $e) {
            error_log('KISMET ERROR: Failed to initialize Ask Handler: ' . $e->getMessage());
            return array(
                'success' => false,
                'error' => 'Failed to initialize Ask Handler: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Track strategy status
     * 
     * @return array Result with strategy tracking details
     */
    private static function track_strategy_status() {
        // Store the current strategy and next fallback in wp_options
        $strategy_status = array(
            'current_strategy' => 'wordpress_rewrite',  // Current strategy being used
            'next_strategy' => null,                    // Next strategy to try if this fails
            'last_validated' => current_time('mysql'),  // When we last checked if it works
            'is_working' => true                        // Whether current strategy is working
        );
        
        update_option('kismet_ask_endpoint_strategy', $strategy_status);
            
        return array(
            'success' => true,
            'strategy_tracked' => true,
            'current_strategy' => $strategy_status['current_strategy']
        );
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
        
        // Mark that rewrite rules need to be flushed
        $result['details']['rewrite_rules'] = 'Pending flush';
        
        return array(
            'success' => true,
            'message' => 'Ask Handler cleanup completed',
            'endpoint_path' => $endpoint_path
        );
    }

    /**
     * Install the /ask endpoint
     */
    public static function install() {
        error_log('KISMET DEBUG: Starting /ask endpoint installation');
        
        try {
            $result = array(
                'success' => true,
                'details' => array()
            );
            
            // Step 1: Add rewrite rule for /ask endpoint
            error_log('KISMET DEBUG: Adding rewrite rule: ^ask/?$ -> index.php?kismet_ask=1');
            add_rewrite_rule('^ask/?$', 'index.php?kismet_ask=1', 'top');
            
            // Step 2: Load required files
            error_log('KISMET DEBUG: Loading required files');
            
            // Load the Ask Content Logic to generate the HTML page
            $content_logic_file = plugin_dir_path(__FILE__) . '../../endpoint-content-logic/class-ask-content-logic.php';
            error_log('KISMET DEBUG: Content logic path: ' . $content_logic_file);
            
            if (file_exists($content_logic_file)) {
                error_log('KISMET DEBUG: Content logic file found and loaded');
                require_once $content_logic_file;
            } else {
                error_log('KISMET DEBUG: Content logic file NOT found at: ' . $content_logic_file);
                throw new Exception('Required content logic file not found');
            }

            // ... rest of the installation code ...

            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Ask Handler installation failed: ' . $e->getMessage()
            );
        }
    }
} 