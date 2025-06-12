<?php
/**
 * Create Static File Building Block
 * 
 * Handles writing content to file paths with directory creation and content generation.
 * Used by static_file_with_htaccess, static_file_with_nginx_suggestion, and manual_static_file strategies.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Create_Static_File {
    
    /**
     * Execute static file creation
     * 
     * @param string $endpoint_path Endpoint path (e.g., '/.well-known/ai-plugin.json')
     * @param array $endpoint_data Data needed for the endpoint
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute($endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            $result = array(
                'success' => false,
                'building_block' => 'create_static_file',
                'files_created' => array(),
                'details' => array()
            );
            
            // Get file path from endpoint path
            $file_path = ABSPATH . ltrim($endpoint_path, '/');
            
            // Step 1: Create directory if needed
            $dir_result = self::create_directory_if_needed($file_path);
            if (!$dir_result['success']) {
                return $dir_result;
            }
            $result['details']['directory'] = $dir_result;
            
            // Step 2: Generate content
            $content_result = self::generate_content($endpoint_data);
            if (!$content_result['success']) {
                return $content_result;
            }
            $result['details']['content'] = $content_result;
            
            // Step 3: Write file
            $write_result = self::write_file($file_path, $content_result['content']);
            if (!$write_result['success']) {
                return $write_result;
            }
            
            $result['files_created'][] = $file_path;
            $result['details']['file_write'] = $write_result;
            $result['success'] = true;
            $result['message'] = "Static file created successfully";
            $result['file_path'] = $file_path;
            $result['bytes_written'] = $write_result['bytes_written'];
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Create static file building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create directory if needed
     * 
     * @param string $file_path Full path to the file
     * @return array Result with success status
     */
    private static function create_directory_if_needed($file_path) {
        $dir_path = dirname($file_path);
        
        if (file_exists($dir_path)) {
            return array(
                'success' => true,
                'message' => 'Directory already exists',
                'directory_path' => $dir_path
            );
        }
        
        if (!wp_mkdir_p($dir_path)) {
            return array(
                'success' => false,
                'error' => "Cannot create directory: {$dir_path}",
                'directory_path' => $dir_path
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Directory created successfully',
            'directory_path' => $dir_path,
            'created' => true
        );
    }
    
    /**
     * Generate content from endpoint data
     * 
     * @param array $endpoint_data Data containing content or content generator
     * @return array Result with generated content
     */
    private static function generate_content($endpoint_data) {
        // Direct content provided
        if (isset($endpoint_data['content']) && is_string($endpoint_data['content'])) {
            return array(
                'success' => true,
                'content' => $endpoint_data['content'],
                'source' => 'direct_content'
            );
        }
        
        // Content generator callback provided
        if (isset($endpoint_data['content_generator']) && is_callable($endpoint_data['content_generator'])) {
            try {
                $content = call_user_func($endpoint_data['content_generator']);
                if (!is_string($content)) {
                    return array(
                        'success' => false,
                        'error' => 'Content generator must return a string'
                    );
                }
                
                return array(
                    'success' => true,
                    'content' => $content,
                    'source' => 'content_generator'
                );
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'error' => 'Content generator failed: ' . $e->getMessage()
                );
            }
        }
        
        // No content provided
        return array(
            'success' => false,
            'error' => 'No content or content_generator provided in endpoint_data'
        );
    }
    
    /**
     * Write content to file
     * 
     * @param string $file_path Full path to the file
     * @param string $content Content to write
     * @return array Result with success status
     */
    private static function write_file($file_path, $content) {
        // Check if file already exists
        $file_existed = file_exists($file_path);
        
        // Write file
        $bytes_written = file_put_contents($file_path, $content);
        if ($bytes_written === false) {
            return array(
                'success' => false,
                'error' => "Cannot write file: {$file_path}",
                'file_path' => $file_path
            );
        }
        
        return array(
            'success' => true,
            'message' => $file_existed ? 'File updated successfully' : 'File created successfully',
            'file_path' => $file_path,
            'bytes_written' => $bytes_written,
            'file_existed' => $file_existed
        );
    }
    
    /**
     * Cleanup static file (for rollback scenarios)
     * 
     * @param string $file_path Full path to the file to remove
     * @return array Result with success status
     */
    public static function cleanup($file_path) {
        if (!file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => 'File does not exist, no cleanup needed',
                'file_path' => $file_path
            );
        }
        
        if (unlink($file_path)) {
            return array(
                'success' => true,
                'message' => 'File removed successfully',
                'file_path' => $file_path
            );
        } else {
            return array(
                'success' => false,
                'error' => "Cannot remove file: {$file_path}",
                'file_path' => $file_path
            );
        }
    }
    
    /**
     * Validate that we can write to the target location
     * 
     * @param string $endpoint_path Endpoint path to validate
     * @return array Validation result
     */
    public static function validate_write_permissions($endpoint_path) {
        $file_path = ABSPATH . ltrim($endpoint_path, '/');
        $dir_path = dirname($file_path);
        
        $validation = array(
            'file_path' => $file_path,
            'directory_path' => $dir_path,
            'can_write_directory' => false,
            'can_write_file' => false,
            'directory_exists' => false,
            'file_exists' => false
        );
        
        // Check directory
        $validation['directory_exists'] = file_exists($dir_path);
        if ($validation['directory_exists']) {
            $validation['can_write_directory'] = is_writable($dir_path);
        } else {
            // Test if we can create the directory
            $validation['can_write_directory'] = is_writable(dirname($dir_path));
        }
        
        // Check file
        $validation['file_exists'] = file_exists($file_path);
        if ($validation['file_exists']) {
            $validation['can_write_file'] = is_writable($file_path);
        } else {
            $validation['can_write_file'] = $validation['can_write_directory'];
        }
        
        $validation['success'] = $validation['can_write_file'];
        
        return $validation;
    }
} 