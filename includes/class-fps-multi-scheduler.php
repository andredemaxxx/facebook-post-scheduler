<?php
/**
 * Multi Scheduler Class
 * 
 * Handles bulk post scheduling with file pairing and carousel support
 * COMPLETELY SEPARATE from normal post scheduling
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Multi_Scheduler {
    
    /**
     * Facebook API instance
     * @var FPS_Facebook_API
     */
    private $facebook_api;
    
    /**
     * Calendar manager instance
     * @var FPS_Calendar_Manager
     */
    private $calendar_manager;
    
    /**
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     * @param FPS_Calendar_Manager $calendar_manager Calendar manager instance
     */
    public function __construct($facebook_api, $calendar_manager) {
        $this->facebook_api = $facebook_api;
        $this->calendar_manager = $calendar_manager;
    }
    
    /**
     * Process uploaded files
     * 
     * @param array $files Files from $_FILES
     * @param string $file_type File type (images/texts)
     * @return array Processed files
     */
    public function process_uploaded_files($files, $file_type) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $processed_files = array();
        $file_count = count($files['name']);
        
        FPS_Logger::info("[FPS] Multi Scheduler: Processing {$file_count} {$file_type} files");
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );
                
                // Validate file
                if (!$this->validate_file($file, $file_type)) {
                    FPS_Logger::warning("[FPS] Multi Scheduler: File validation failed: {$file['name']}");
                    continue;
                }
                
                $upload_overrides = array(
                    'test_form' => false,
                    'unique_filename_callback' => array($this, 'unique_filename_callback')
                );
                
                // Set allowed file types
                if ($file_type === 'images') {
                    $upload_overrides['mimes'] = array(
                        'jpg|jpeg|jpe' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'png' => 'image/png',
                        'webp' => 'image/webp'
                    );
                } elseif ($file_type === 'texts') {
                    $upload_overrides['mimes'] = array(
                        'txt' => 'text/plain'
                    );
                }
                
                $upload_result = wp_handle_upload($file, $upload_overrides);
                
                if (!isset($upload_result['error'])) {
                    $processed_file = array(
                        'name' => $file['name'],
                        'original_name' => pathinfo($file['name'], PATHINFO_FILENAME),
                        'numeric_part' => $this->extract_numeric_part($file['name']),
                        'url' => $upload_result['url'],
                        'file' => $upload_result['file'],
                        'type' => $file_type,
                        'size' => $file['size'],
                        'mime_type' => $file['type'],
                        'id' => uniqid($file_type . '_')
                    );
                    
                    // For text files, read content
                    if ($file_type === 'texts') {
                        $content = file_get_contents($upload_result['file']);
                        $processed_file['content'] = wp_kses_post($content);
                    }
                    
                    $processed_files[] = $processed_file;
                    FPS_Logger::debug("[FPS] Multi Scheduler: File processed successfully: {$file['name']}");
                } else {
                    FPS_Logger::error("[FPS] Multi Scheduler: File upload failed: {$upload_result['error']}");
                }
            }
        }
        
        FPS_Logger::info("[FPS] Multi Scheduler: Successfully processed " . count($processed_files) . " {$file_type} files");
        return $processed_files;
    }
    
    /**
     * Extract numeric part from filename for pairing
     * 
     * @param string $filename Filename
     * @return string|null Numeric part
     */
    private function extract_numeric_part($filename) {
        // Extract numeric part (e.g., "image1a.jpg" -> "1", "text2.txt" -> "2")
        if (preg_match('/(\d+)/', $filename, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file File data
     * @param string $file_type File type
     * @return bool Valid
     */
    private function validate_file($file, $file_type) {
        // Check file size limits
        $max_sizes = array(
            'images' => 10 * 1024 * 1024, // 10MB
            'texts' => 1 * 1024 * 1024    // 1MB
        );
        
        if (isset($max_sizes[$file_type]) && $file['size'] > $max_sizes[$file_type]) {
            return false;
        }
        
        // Check file types
        $allowed_types = array(
            'images' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
            'texts' => array('text/plain')
        );
        
        if (isset($allowed_types[$file_type]) && !in_array($file['type'], $allowed_types[$file_type])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Pair files based on method with carousel support
     * 
     * @param array $images Image files
     * @param array $texts Text files
     * @param string $pairing_method Pairing method
     * @param array $manual_texts Manual texts (for images-only mode)
     * @param array $carousel_groups Carousel groupings
     * @return array Paired posts
     */
    public function pair_files($images, $texts, $pairing_method, $manual_texts = array(), $carousel_groups = array()) {
        $pairs = array();
        
        FPS_Logger::info("[FPS] Multi Scheduler: Pairing files using method: {$pairing_method}");
        
        switch ($pairing_method) {
            case 'filename':
                $pairs = $this->pair_by_numeric_part($images, $texts);
                break;
                
            case 'order':
                $pairs = $this->pair_by_order($images, $texts, $carousel_groups);
                break;
                
            case 'manual':
                $pairs = $this->pair_with_manual_texts($images, $manual_texts, $carousel_groups);
                break;
                
            default:
                FPS_Logger::error("[FPS] Multi Scheduler: Invalid pairing method: {$pairing_method}");
                return array();
        }
        
        FPS_Logger::info("[FPS] Multi Scheduler: Created " . count($pairs) . " paired posts");
        return $pairs;
    }
    
    /**
     * Pair files by numeric part with automatic carousel grouping
     * 
     * @param array $images Image files
     * @param array $texts Text files
     * @return array Paired posts
     */
    private function pair_by_numeric_part($images, $texts) {
        $pairs = array();
        
        // Group images by numeric part
        $image_groups = array();
        foreach ($images as $image) {
            $numeric_part = $image['numeric_part'] ?: 'no_number';
            if (!isset($image_groups[$numeric_part])) {
                $image_groups[$numeric_part] = array();
            }
            $image_groups[$numeric_part][] = $image;
        }
        
        // Group texts by numeric part
        $text_groups = array();
        foreach ($texts as $text) {
            $numeric_part = $text['numeric_part'] ?: 'no_number';
            $text_groups[$numeric_part] = $text;
        }
        
        // Create pairs
        foreach ($image_groups as $numeric_part => $grouped_images) {
            if (isset($text_groups[$numeric_part])) {
                $post_type = count($grouped_images) > 1 ? 'carousel' : 'single';
                
                $pairs[] = array(
                    'type' => $post_type,
                    'images' => $grouped_images,
                    'text' => $text_groups[$numeric_part],
                    'content' => $text_groups[$numeric_part]['content'],
                    'pair_method' => 'filename',
                    'numeric_part' => $numeric_part
                );
            }
        }
        
        return $pairs;
    }
    
    /**
     * Pair files by upload order
     * 
     * @param array $images Image files
     * @param array $texts Text files
     * @param array $carousel_groups Carousel groupings
     * @return array Paired posts
     */
    private function pair_by_order($images, $texts, $carousel_groups = array()) {
        $pairs = array();
        $max_pairs = min(count($images), count($texts));
        
        for ($i = 0; $i < $max_pairs; $i++) {
            // Check if this image is part of a carousel group
            $post_images = array($images[$i]);
            $post_type = 'single';
            
            // Apply carousel groupings if provided
            if (!empty($carousel_groups)) {
                foreach ($carousel_groups as $group) {
                    if (in_array($images[$i]['id'], $group)) {
                        $post_images = array();
                        foreach ($group as $image_id) {
                            foreach ($images as $img) {
                                if ($img['id'] === $image_id) {
                                    $post_images[] = $img;
                                    break;
                                }
                            }
                        }
                        $post_type = 'carousel';
                        break;
                    }
                }
            }
            
            $pairs[] = array(
                'type' => $post_type,
                'images' => $post_images,
                'text' => $texts[$i],
                'content' => $texts[$i]['content'],
                'pair_method' => 'order'
            );
        }
        
        return $pairs;
    }
    
    /**
     * Pair images with manual texts
     * 
     * @param array $images Image files
     * @param array $manual_texts Manual texts
     * @param array $carousel_groups Carousel groupings
     * @return array Paired posts
     */
    private function pair_with_manual_texts($images, $manual_texts, $carousel_groups = array()) {
        $pairs = array();
        
        foreach ($images as $index => $image) {
            $text_content = isset($manual_texts[$index]) ? wp_kses_post($manual_texts[$index]) : '';
            
            if (!empty($text_content)) {
                // Check if this image is part of a carousel group
                $post_images = array($image);
                $post_type = 'single';
                
                if (!empty($carousel_groups)) {
                    foreach ($carousel_groups as $group) {
                        if (in_array($image['id'], $group)) {
                            $post_images = array();
                            foreach ($group as $image_id) {
                                foreach ($images as $img) {
                                    if ($img['id'] === $image_id) {
                                        $post_images[] = $img;
                                        break;
                                    }
                                }
                            }
                            $post_type = 'carousel';
                            break;
                        }
                    }
                }
                
                $pairs[] = array(
                    'type' => $post_type,
                    'images' => $post_images,
                    'text' => null,
                    'content' => $text_content,
                    'pair_method' => 'manual'
                );
            }
        }
        
        return $pairs;
    }
    
    /**
     * Schedule multiple posts with timezone validation
     * 
     * @param string $page_id Facebook page ID
     * @param array $pairs Paired posts
     * @param string $selected_date Selected date
     * @param array $time_slots Available time slots
     * @param bool $share_to_story Share to story option
     * @return array Results
     */
    public function schedule_multiple_posts($page_id, $pairs, $selected_date, $time_slots, $share_to_story = false) {
        if (empty($pairs) || empty($time_slots)) {
            return array(
                'success' => false,
                'message' => __('No posts or time slots provided', 'facebook-post-scheduler'),
                'scheduled_count' => 0,
                'errors' => array()
            );
        }
        
        // Validate date is in future using São Paulo timezone
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $target_date = new DateTime($selected_date, $timezone);
        $now = new DateTime('now', $timezone);
        
        if ($target_date < $now && $target_date->format('Y-m-d') !== $now->format('Y-m-d')) {
            return array(
                'success' => false,
                'message' => __('Selected date must be today or in the future', 'facebook-post-scheduler'),
                'scheduled_count' => 0,
                'errors' => array()
            );
        }
        
        // Check if we have enough time slots
        if (count($pairs) > count($time_slots)) {
            return array(
                'success' => false,
                'message' => __('Not enough available time slots for all posts', 'facebook-post-scheduler'),
                'scheduled_count' => 0,
                'errors' => array()
            );
        }
        
        FPS_Logger::info("[FPS] Multi Scheduler: Scheduling " . count($pairs) . " posts for {$selected_date}");
        
        $scheduled_count = 0;
        $errors = array();
        
        // Initialize scheduler
        $token_manager = new FPS_Token_Manager();
        $facebook_api = new FPS_Facebook_API($token_manager);
        $scheduler = new FPS_Scheduler($facebook_api);
        
        foreach ($pairs as $index => $pair) {
            if (!isset($time_slots[$index])) {
                $errors[] = sprintf(__('No time slot available for post %d', 'facebook-post-scheduler'), $index + 1);
                continue;
            }
            
            $scheduled_time = $selected_date . ' ' . $time_slots[$index];
            
            // Validate scheduled time is in future (except for today)
            $scheduled_datetime = new DateTime($scheduled_time, $timezone);
            
            // Facebook requires at least 10 minutes in the future
            $min_future_time = clone $now;
            $min_future_time->modify('+10 minutes');
            
            if ($scheduled_datetime < $min_future_time) {
                $errors[] = sprintf(__('Scheduled time %s must be at least 10 minutes in the future', 'facebook-post-scheduler'), $scheduled_time);
                continue;
            }
            
            $post_data = array(
                'page_id' => $page_id,
                'message' => $pair['content'],
                'scheduled_time' => $scheduled_time,
                'post_type' => $pair['type'], // single or carousel
                'share_to_story' => $share_to_story
            );
            
            // Add images
            if ($pair['type'] === 'carousel') {
                $post_data['images'] = $pair['images'];
            } else {
                $post_data['image_url'] = $pair['images'][0]['url'];
                $post_data['image_path'] = $pair['images'][0]['file'];
            }
            
            $post_id = $scheduler->schedule_post($post_data);
            
            if ($post_id) {
                $scheduled_count++;
                FPS_Logger::info("[FPS] Multi Scheduler: Post scheduled with ID: {$post_id}");
            } else {
                $errors[] = sprintf(__('Failed to schedule post %d', 'facebook-post-scheduler'), $index + 1);
                FPS_Logger::error("[FPS] Multi Scheduler: Failed to schedule post for index {$index}");
            }
        }
        
        $success = $scheduled_count > 0;
        $message = $success 
            ? sprintf(__('%d posts scheduled successfully', 'facebook-post-scheduler'), $scheduled_count)
            : __('No posts were scheduled', 'facebook-post-scheduler');
        
        return array(
            'success' => $success,
            'message' => $message,
            'scheduled_count' => $scheduled_count,
            'errors' => $errors
        );
    }
    
    /**
     * Generate preview for paired posts
     * 
     * @param array $pairs Paired posts
     * @param string $page_id Facebook page ID
     * @param string $selected_date Selected date
     * @param array $time_slots Time slots
     * @return array Preview data
     */
    public function generate_preview($pairs, $page_id, $selected_date, $time_slots) {
        $previews = array();
        
        // Get page info
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        $page_name = 'Facebook Page';
        
        if ($pages) {
            foreach ($pages as $page) {
                if ($page['id'] === $page_id) {
                    $page_name = $page['name'];
                    break;
                }
            }
        }
        
        foreach ($pairs as $index => $pair) {
            $scheduled_time = isset($time_slots[$index]) 
                ? $selected_date . ' ' . $time_slots[$index]
                : null;
            
            $preview = array(
                'index' => $index,
                'type' => $pair['type'],
                'images' => $pair['images'],
                'content' => $pair['content'],
                'page_name' => $page_name,
                'scheduled_time' => $scheduled_time,
                'formatted_time' => $scheduled_time ? $this->format_datetime($scheduled_time) : null,
                'pair_method' => $pair['pair_method'],
                'can_schedule' => !empty($scheduled_time)
            );
            
            $previews[] = $preview;
        }
        
        return $previews;
    }
    
    /**
     * Format datetime for display
     * 
     * @param string $datetime Datetime string
     * @return string Formatted datetime
     */
    private function format_datetime($datetime) {
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $dt = new DateTime($datetime, $timezone);
        
        return $dt->format('d/m/Y \à\s H:i');
    }
    
    /**
     * Generate unique filename for uploads
     * 
     * @param string $dir Directory
     * @param string $name Filename
     * @param string $ext Extension
     * @return string Unique filename
     */
    public function unique_filename_callback($dir, $name, $ext) {
        return 'fps_multi_' . time() . '_' . wp_generate_password(8, false) . $ext;
    }
    
    /**
     * Clean up uploaded files
     * 
     * @param array $files Files to clean up
     */
    public function cleanup_files($files) {
        foreach ($files as $file) {
            if (isset($file['file']) && file_exists($file['file'])) {
                wp_delete_file($file['file']);
                FPS_Logger::debug("[FPS] Multi Scheduler: Cleaned up file: {$file['file']}");
            }
        }
    }
}