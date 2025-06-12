<?php
/**
 * Modify Existing File Building Block
 * 
 * Handles file content modification, backup creation, and content insertion/replacement.
 * Used by file_modification strategy for robots.txt endpoint and similar file modifications.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Modify_Existing_File {
    
    /**
     * Execute file modification
     * 
     * @param string $endpoint_path Endpoint path (e.g., '/robots.txt')
     * @param array $endpoint_data Data needed for the endpoint
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute($endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            $result = array(
                'success' => false,
                'building_block' => 'modify_existing_file',
                'files_modified' => array(),
                'backups_created' => array(),
                'details' => array()
            );
            
            // Get file path from endpoint path
            $file_path = ABSPATH . ltrim($endpoint_path, '/');
            
            // Step 1: Validate file modification parameters
            $validation_result = self::validate_modification_params($file_path, $endpoint_data);
            if (!$validation_result['success']) {
                return $validation_result;
            }
            $result['details']['validation'] = $validation_result;
            
            // Step 2: Create backup if requested
            if (isset($endpoint_data['backup_path']) || 
                (isset($endpoint_data['safety_checks']) && in_array('backup_before_modify', $endpoint_data['safety_checks']))) {
                
                $backup_result = self::create_backup($file_path, $endpoint_data);
                if (!$backup_result['success']) {
                    return $backup_result;
                }
                $result['backups_created'][] = $backup_result['backup_path'];
                $result['details']['backup'] = $backup_result;
            }
            
            // Step 3: Read existing content
            $content_result = self::read_existing_content($file_path, $endpoint_data);
            if (!$content_result['success']) {
                return $content_result;
            }
            $result['details']['existing_content'] = $content_result;
            
            // Step 4: Generate new content
            $new_content_result = self::generate_modified_content($content_result['content'], $endpoint_data);
            if (!$new_content_result['success']) {
                return $new_content_result;
            }
            $result['details']['content_generation'] = $new_content_result;
            
            // Step 5: Write modified content
            $write_result = self::write_modified_content($file_path, $new_content_result['content']);
            if (!$write_result['success']) {
                return $write_result;
            }
            
            $result['files_modified'][] = $file_path;
            $result['details']['file_write'] = $write_result;
            $result['success'] = true;
            $result['message'] = "File modified successfully";
            $result['file_path'] = $file_path;
            $result['bytes_written'] = $write_result['bytes_written'];
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Modify existing file building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Validate file modification parameters
     * 
     * @param string $file_path Full path to the file
     * @param array $endpoint_data Endpoint configuration data
     * @return array Validation result
     */
    private static function validate_modification_params($file_path, $endpoint_data) {
        $validation = array(
            'file_path' => $file_path,
            'file_exists' => false,
            'can_read' => false,
            'can_write' => false,
            'has_content_source' => false
        );
        
        // Check if file exists
        $validation['file_exists'] = file_exists($file_path);
        
        if ($validation['file_exists']) {
            $validation['can_read'] = is_readable($file_path);
            $validation['can_write'] = is_writable($file_path);
        } else {
            // For new files, check if directory is writable
            $validation['can_write'] = is_writable(dirname($file_path));
        }
        
        // Check if we have content to add
        $validation['has_content_source'] = (
            isset($endpoint_data['content']) ||
            isset($endpoint_data['content_generator']) ||
            isset($endpoint_data['content_to_add'])
        );
        
        $validation['success'] = $validation['can_write'] && $validation['has_content_source'];
        
        if (!$validation['success']) {
            $errors = array();
            if (!$validation['can_write']) {
                $errors[] = 'Cannot write to file or directory';
            }
            if (!$validation['has_content_source']) {
                $errors[] = 'No content source provided';
            }
            $validation['error'] = implode(', ', $errors);
        }
        
        return $validation;
    }
    
    /**
     * Create backup of existing file
     * 
     * @param string $file_path Full path to the file
     * @param array $endpoint_data Endpoint configuration data
     * @return array Backup result
     */
    private static function create_backup($file_path, $endpoint_data) {
        if (!file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => 'No existing file to backup',
                'backup_path' => null
            );
        }
        
        // Determine backup path
        if (isset($endpoint_data['backup_path'])) {
            $backup_path = $endpoint_data['backup_path'];
        } else {
            $backup_path = $file_path . '.kismet-backup-' . date('Y-m-d-H-i-s');
        }
        
        // Copy file to backup location
        if (!copy($file_path, $backup_path)) {
            return array(
                'success' => false,
                'error' => "Cannot create backup file: {$backup_path}",
                'file_path' => $file_path,
                'backup_path' => $backup_path
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Backup created successfully',
            'file_path' => $file_path,
            'backup_path' => $backup_path,
            'backup_size' => filesize($backup_path)
        );
    }
    
    /**
     * Read existing file content
     * 
     * @param string $file_path Full path to the file
     * @param array $endpoint_data Endpoint configuration data
     * @return array Content result
     */
    private static function read_existing_content($file_path, $endpoint_data) {
        if (!file_exists($file_path)) {
            return array(
                'success' => true,
                'content' => '',
                'file_size' => 0,
                'message' => 'File does not exist, starting with empty content'
            );
        }
        
        $content = file_get_contents($file_path);
        if ($content === false) {
            return array(
                'success' => false,
                'error' => "Cannot read existing file: {$file_path}"
            );
        }
        
        return array(
            'success' => true,
            'content' => $content,
            'file_size' => strlen($content),
            'line_count' => substr_count($content, "\n") + 1
        );
    }
    
    /**
     * Generate modified content
     * 
     * @param string $existing_content Current file content
     * @param array $endpoint_data Endpoint configuration data
     * @return array Modified content result
     */
    private static function generate_modified_content($existing_content, $endpoint_data) {
        // Get content to add
        $content_to_add = '';
        
        if (isset($endpoint_data['content'])) {
            $content_to_add = $endpoint_data['content'];
        } elseif (isset($endpoint_data['content_generator']) && is_callable($endpoint_data['content_generator'])) {
            try {
                $content_to_add = call_user_func($endpoint_data['content_generator']);
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'error' => 'Content generator failed: ' . $e->getMessage()
                );
            }
        } elseif (isset($endpoint_data['content_to_add'])) {
            $content_to_add = $endpoint_data['content_to_add'];
        }
        
        if (empty($content_to_add)) {
            return array(
                'success' => false,
                'error' => 'No content to add'
            );
        }
        
        // For robots.txt, always append with proper formatting
        $new_content = $existing_content;
        
        // Add separator if needed
        if (!empty($existing_content) && !preg_match('/\n$/', $existing_content)) {
            $new_content .= "\n";
        }
        
        // Add extra newlines for separation
        $new_content .= "\n";
        $new_content .= $content_to_add;
        
        return array(
            'success' => true,
            'content' => $new_content,
            'strategy' => 'append',
            'content_added_length' => strlen($content_to_add),
            'final_length' => strlen($new_content)
        );
    }
    
    /**
     * Write modified content to file
     * 
     * @param string $file_path Full path to the file
     * @param string $content Content to write
     * @return array Write result
     */
    private static function write_modified_content($file_path, $content) {
        $bytes_written = file_put_contents($file_path, $content);
        if ($bytes_written === false) {
            return array(
                'success' => false,
                'error' => "Cannot write modified content to file: {$file_path}"
            );
        }
        
        return array(
            'success' => true,
            'message' => 'File modified successfully',
            'file_path' => $file_path,
            'bytes_written' => $bytes_written
        );
    }
    
    /**
     * Cleanup file modifications (for rollback scenarios)
     * 
     * @param string $file_path Full path to the file
     * @param string $backup_path Path to backup file (optional)
     * @return array Result with success status
     */
    public static function cleanup($file_path, $backup_path = null) {
        if ($backup_path && file_exists($backup_path)) {
            // Restore from backup
            if (copy($backup_path, $file_path)) {
                unlink($backup_path); // Remove backup after restore
                return array(
                    'success' => true,
                    'message' => 'File restored from backup',
                    'file_path' => $file_path,
                    'backup_path' => $backup_path
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Cannot restore file from backup',
                    'file_path' => $file_path,
                    'backup_path' => $backup_path
                );
            }
        } else {
            return array(
                'success' => true,
                'message' => 'No backup available for cleanup',
                'file_path' => $file_path
            );
        }
    }
} 