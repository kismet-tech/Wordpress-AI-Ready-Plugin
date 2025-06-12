<?php
/**
 * Kismet File Safety Manager - Bulletproof file operations with configurable policies
 *
 * Handles file creation conflicts safely with multiple policy options
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File Safety Manager for bulletproof file operations
 */
class Kismet_File_Safety_Manager {
    
    /**
     * File conflict policies
     */
    const POLICY_NEVER_OVERWRITE = 'never_overwrite';
    const POLICY_PROMPT_USER = 'prompt_user';
    const POLICY_BACKUP_AND_OVERWRITE = 'backup_overwrite';
    const POLICY_CONTENT_ANALYSIS = 'content_analysis';
    const POLICY_ADMIN_CHOICE = 'admin_choice';
    
    /**
     * Default policy
     * @var string
     */
    private $default_policy = self::POLICY_NEVER_OVERWRITE;
    
    /**
     * Handle file creation with conflict resolution
     * 
     * @param string $file_path Target file path
     * @param string $content Content to write
     * @param string $policy Conflict resolution policy
     * @return array Operation result
     */
    public function safe_file_create($file_path, $content, $policy = null) {
        $policy = $policy ?? $this->get_file_policy($file_path);
        
        $result = array(
            'success' => false,
            'action_taken' => 'none',
            'existing_file' => file_exists($file_path),
            'backup_created' => false,
            'policy_used' => $policy,
            'messages' => array(),
            'warnings' => array(),
            'errors' => array()
        );
        
        // If file doesn't exist, create it directly
        if (!file_exists($file_path)) {
            return $this->create_new_file($file_path, $content, $result);
        }
        
        // File exists - apply conflict resolution policy
        switch ($policy) {
            case self::POLICY_NEVER_OVERWRITE:
                return $this->handle_never_overwrite($file_path, $content, $result);
                
            case self::POLICY_BACKUP_AND_OVERWRITE:
                return $this->handle_backup_and_overwrite($file_path, $content, $result);
                
            case self::POLICY_CONTENT_ANALYSIS:
                return $this->handle_content_analysis($file_path, $content, $result);
                
            case self::POLICY_ADMIN_CHOICE:
                return $this->handle_admin_choice($file_path, $content, $result);
                
            case self::POLICY_PROMPT_USER:
                return $this->handle_prompt_user($file_path, $content, $result);
                
            default:
                $result['errors'][] = "Unknown file policy: $policy";
                return $result;
        }
    }
    
    /**
     * Create new file (no conflicts)
     */
    private function create_new_file($file_path, $content, $result) {
        $bytes_written = @file_put_contents($file_path, $content);
        
        if ($bytes_written === false) {
            $result['errors'][] = "Failed to create file: $file_path";
            return $result;
        }
        
        $result['success'] = true;
        $result['action_taken'] = 'created_new';
        $result['messages'][] = "Successfully created new file: $file_path";
        
        // Store file metadata for tracking
        $this->store_file_metadata($file_path, $content);
        
        return $result;
    }
    
    /**
     * Handle NEVER_OVERWRITE policy
     */
    private function handle_never_overwrite($file_path, $content, $result) {
        // Check if it's our file by content fingerprint
        $existing_content = @file_get_contents($file_path);
        if ($existing_content === false) {
            $result['errors'][] = "Cannot read existing file: $file_path";
            return $result;
        }
        
        // If content is identical, it's already our file
        if ($existing_content === $content) {
            $result['success'] = true;
            $result['action_taken'] = 'file_already_correct';
            $result['messages'][] = "File already exists with correct content: $file_path";
            return $result;
        }
        
        // Content differs - check our metadata to see if we created it
        if ($this->is_our_file($file_path, $existing_content)) {
            // It's our file but content changed - update it
            $bytes_written = @file_put_contents($file_path, $content);
            if ($bytes_written === false) {
                $result['errors'][] = "Failed to update our existing file: $file_path";
                return $result;
            }
            
            $result['success'] = true;
            $result['action_taken'] = 'updated_our_file';
            $result['messages'][] = "Updated our existing file with new content: $file_path";
            
            // Update metadata
            $this->store_file_metadata($file_path, $content);
            return $result;
        }
        
        // Not our file - refuse to overwrite
        $result['errors'][] = "File already exists with different content - not overwriting: $file_path";
        $result['warnings'][] = "Consider using backup_overwrite or content_analysis policy";
        return $result;
    }
    
    /**
     * Handle BACKUP_AND_OVERWRITE policy
     */
    private function handle_backup_and_overwrite($file_path, $content, $result) {
        // Create backup first
        $backup_path = $this->create_backup($file_path);
        if (!$backup_path) {
            $result['errors'][] = "Failed to create backup for: $file_path";
            return $result;
        }
        
        $result['backup_created'] = true;
        $result['messages'][] = "Created backup: $backup_path";
        
        // Now overwrite the file
        $bytes_written = @file_put_contents($file_path, $content);
        if ($bytes_written === false) {
            $result['errors'][] = "Failed to overwrite file after backup: $file_path";
            return $result;
        }
        
        $result['success'] = true;
        $result['action_taken'] = 'backup_and_overwrite';
        $result['messages'][] = "Backed up and overwritten: $file_path";
        
        // Store metadata
        $this->store_file_metadata($file_path, $content);
        
        return $result;
    }
    
    /**
     * Handle CONTENT_ANALYSIS policy
     */
    private function handle_content_analysis($file_path, $content, $result) {
        $existing_content = @file_get_contents($file_path);
        if ($existing_content === false) {
            $result['errors'][] = "Cannot read existing file: $file_path";
            return $result;
        }
        
        // Analyze the existing content
        $analysis = $this->analyze_file_content($file_path, $existing_content);
        
        if ($analysis['is_safe_to_overwrite']) {
            $bytes_written = @file_put_contents($file_path, $content);
            if ($bytes_written === false) {
                $result['errors'][] = "Failed to overwrite analyzed file: $file_path";
                return $result;
            }
            
            $result['success'] = true;
            $result['action_taken'] = 'overwrite_after_analysis';
            $result['messages'][] = "Content analysis indicates safe to overwrite: $file_path";
            $result['messages'][] = "Analysis: " . $analysis['reason'];
            
            // Store metadata
            $this->store_file_metadata($file_path, $content);
        } else {
            $result['errors'][] = "Content analysis indicates unsafe to overwrite: $file_path";
            $result['warnings'][] = "Analysis: " . $analysis['reason'];
            $result['warnings'][] = "Consider backup_overwrite policy or manual review";
        }
        
        return $result;
    }
    
    /**
     * Analyze file content to determine if it's safe to overwrite
     */
    private function analyze_file_content($file_path, $content) {
        $analysis = array(
            'is_safe_to_overwrite' => false,
            'reason' => 'Unknown content type',
            'content_type' => 'unknown',
            'size_bytes' => strlen($content),
            'appears_generated' => false,
            'has_user_data' => true
        );
        
        $filename = basename($file_path);
        
        // Check if it looks like an auto-generated file
        if (strpos($content, 'Generated by') !== false || 
            strpos($content, 'Auto-generated') !== false ||
            strpos($content, '# This file is auto-generated') !== false) {
            $analysis['appears_generated'] = true;
        }
        
        // Analyze specific file types
        if ($filename === 'llms.txt') {
            if (preg_match('/^#.*LLMS\.txt/mi', $content) || 
                strpos($content, 'MCP-SERVER:') !== false) {
                $analysis['content_type'] = 'llms_txt';
                $analysis['has_user_data'] = false;
                $analysis['is_safe_to_overwrite'] = true;
                $analysis['reason'] = 'Appears to be LLMS.txt format - safe to overwrite';
            }
        } elseif ($filename === 'robots.txt') {
            // Check if robots.txt appears to be a standard WordPress robots.txt
            $is_standard_robots = (
                strpos($content, 'User-agent:') !== false &&
                (strpos($content, 'Disallow: /wp-admin/') !== false || 
                 strpos($content, 'wp-sitemap.xml') !== false ||
                 preg_match('/^User-agent:\s*\*\s*$/mi', $content))
            );
            
            // Check if it already contains our AI section
            $has_our_content = strpos($content, '# AI/LLM Discovery Section') !== false;
            
            // Check if it's empty or very minimal
            $is_minimal = strlen(trim($content)) < 200;
            
            if ($is_standard_robots || $has_our_content || $is_minimal) {
                $analysis['content_type'] = 'robots_txt';
                $analysis['has_user_data'] = false;
                $analysis['is_safe_to_overwrite'] = true;
                if ($has_our_content) {
                    $analysis['reason'] = 'robots.txt already contains our AI section - safe to update';
                } elseif ($is_standard_robots) {
                    $analysis['reason'] = 'Standard WordPress robots.txt format - safe to enhance';
                } else {
                    $analysis['reason'] = 'Minimal robots.txt content - safe to overwrite';
                }
            } else {
                $analysis['content_type'] = 'robots_txt';
                $analysis['has_user_data'] = true;
                $analysis['is_safe_to_overwrite'] = false;
                $analysis['reason'] = 'robots.txt contains custom content - requires manual review';
            }
        } elseif (strpos($filename, '.json') !== false) {
            $json_data = @json_decode($content, true);
            if ($json_data !== null) {
                $analysis['content_type'] = 'json';
                if (isset($json_data['schema_version']) || 
                    isset($json_data['generated_by']) ||
                    isset($json_data['api'])) {
                    $analysis['has_user_data'] = false;
                    $analysis['is_safe_to_overwrite'] = true;
                    $analysis['reason'] = 'Appears to be API/config JSON - safe to overwrite';
                }
            }
        }
        
        // Small files with minimal content might be safe
        if ($analysis['size_bytes'] < 1024 && $analysis['appears_generated']) {
            $analysis['is_safe_to_overwrite'] = true;
            $analysis['reason'] = 'Small generated file - appears safe to overwrite';
        }
        
        return $analysis;
    }
    
    /**
     * Handle ADMIN_CHOICE policy
     */
    private function handle_admin_choice($file_path, $content, $result) {
        // Store conflict for admin resolution
        $this->store_file_conflict($file_path, $content);
        $result['action_taken'] = 'deferred_to_admin';
        $result['messages'][] = "File conflict stored for admin resolution: $file_path";
        $result['warnings'][] = "Check Kismet admin panel to resolve file conflicts";
        return $result;
    }
    
    /**
     * Handle PROMPT_USER policy (stores for later resolution)
     */
    private function handle_prompt_user($file_path, $content, $result) {
        // In WordPress plugin context, we can't prompt directly
        // Store the conflict for resolution in admin panel
        $this->store_file_conflict($file_path, $content);
        
        $result['action_taken'] = 'stored_for_resolution';
        $result['messages'][] = "File conflict stored for user resolution: $file_path";
        $result['warnings'][] = "Visit Kismet admin panel to resolve pending file conflicts";
        
        return $result;
    }
    
    /**
     * Create backup of existing file
     */
    private function create_backup($file_path) {
        $backup_dir = dirname($file_path) . '/kismet-backups';
        if (!file_exists($backup_dir)) {
            if (!@mkdir($backup_dir, 0755, true)) {
                return false;
            }
        }
        
        $filename = basename($file_path);
        $timestamp = date('Y-m-d_H-i-s');
        $backup_path = $backup_dir . '/' . $filename . '.backup.' . $timestamp;
        
        if (@copy($file_path, $backup_path)) {
            return $backup_path;
        }
        
        return false;
    }
    
    /**
     * Check if file was created by our plugin
     */
    private function is_our_file($file_path, $content) {
        $created_files = get_option('kismet_created_files', array());
        
        foreach ($created_files as $file_meta) {
            if (isset($file_meta['file_path']) && $file_meta['file_path'] === $file_path) {
                // Check if content hash matches any version we created
                if (isset($file_meta['content_hash']) && 
                    $file_meta['content_hash'] === md5($content)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Store file metadata for tracking
     */
    private function store_file_metadata($file_path, $content) {
        $metadata = array(
            'file_path' => $file_path,
            'content_hash' => md5($content),
            'created_at' => current_time('mysql'),
            'created_by' => 'kismet-ai-ready-plugin',
            'file_size' => strlen($content)
        );
        
        $existing_files = get_option('kismet_created_files', array());
        
        // Remove old entries for this file path
        $existing_files = array_filter($existing_files, function($file_meta) use ($file_path) {
            return !isset($file_meta['file_path']) || $file_meta['file_path'] !== $file_path;
        });
        
        $existing_files[] = $metadata;
        
        update_option('kismet_created_files', $existing_files);
    }
    
    /**
     * Store file conflict for later resolution
     */
    private function store_file_conflict($file_path, $proposed_content) {
        $conflict = array(
            'file_path' => $file_path,
            'proposed_content' => $proposed_content,
            'existing_content' => file_exists($file_path) ? @file_get_contents($file_path) : '',
            'detected_at' => current_time('mysql'),
            'status' => 'pending',
            'admin_action' => null
        );
        
        $conflicts = get_option('kismet_file_conflicts', array());
        $conflicts[] = $conflict;
        
        update_option('kismet_file_conflicts', $conflicts);
    }
    
    /**
     * Get appropriate file policy for a file path
     */
    private function get_file_policy($file_path) {
        // Check if there's a specific policy for this file type
        $filename = basename($file_path);
        
        $file_policies = get_option('kismet_file_policies', array(
            'llms.txt' => self::POLICY_CONTENT_ANALYSIS,
            '*.json' => self::POLICY_CONTENT_ANALYSIS,
            'default' => self::POLICY_NEVER_OVERWRITE
        ));
        
        if (isset($file_policies[$filename])) {
            return $file_policies[$filename];
        }
        
        // Check wildcard patterns
        foreach ($file_policies as $pattern => $policy) {
            if (fnmatch($pattern, $filename)) {
                return $policy;
            }
        }
        
        return $file_policies['default'] ?? $this->default_policy;
    }
} 