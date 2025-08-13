<?php
/**
 * Scheduler Class
 * 
 * Handles post scheduling and publishing
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Scheduler {
    
    /**
     * Facebook API instance
     * @var FPS_Facebook_API
     */
    private $facebook_api;
    
    /**
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     */
    public function __construct($facebook_api) {
        $this->facebook_api = $facebook_api;
    }
    
    /**
     * Schedule a post
     * 
     * @param array $post_data Post data
     * @return int|false Post ID or false
     */
    public function schedule_post($post_data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($post_data['page_id']) || empty($post_data['message'])) {
            FPS_Logger::log('Missing required fields for scheduling', 'error');
            return false;
        }
        
        // Validate scheduled time
        // Use São Paulo timezone for validation
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $scheduled_datetime = new DateTime($post_data['scheduled_time'], $timezone);
        $now = new DateTime('now', $timezone);
        
        $scheduled_time = strtotime($post_data['scheduled_time']);
        if ($scheduled_datetime <= $now) {
            FPS_Logger::log('Scheduled time must be in the future', 'error');
            return false;
        }
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Prepare data for database
        $db_data = array(
            'page_id' => sanitize_text_field($post_data['page_id']),
            'message' => wp_kses_post($post_data['message']),
            'link' => !empty($post_data['link']) ? esc_url_raw($post_data['link']) : '',
            'image_url' => !empty($post_data['image_url']) ? esc_url_raw($post_data['image_url']) : '',
            'image_path' => !empty($post_data['image_path']) ? $post_data['image_path'] : '',
            'video_url' => !empty($post_data['video_url']) ? esc_url_raw($post_data['video_url']) : '',
            'post_type' => !empty($post_data['post_type']) ? sanitize_text_field($post_data['post_type']) : 'single',
            'share_to_story' => !empty($post_data['share_to_story']) ? 1 : 0,
            'scheduled_time' => $scheduled_datetime->format('Y-m-d H:i:s'),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        // Handle file uploads
        if (!empty($post_data['image_file'])) {
            $upload_result = $this->handle_file_upload($post_data['image_file'], 'image');
            if ($upload_result) {
                $db_data['image_path'] = $upload_result['file'];
                $db_data['image_url'] = $upload_result['url'];
            }
        }
        
        // Handle multiple images (carousel)
        if (!empty($post_data['images']) && is_array($post_data['images'])) {
            $image_urls = array();
            $image_paths = array();
            
            foreach ($post_data['images'] as $image) {
                if (isset($image['url'])) {
                    $image_urls[] = $image['url'];
                }
                if (isset($image['file'])) {
                    $image_paths[] = $image['file'];
                }
            }
            
            $db_data['image_url'] = implode(',', $image_urls);
            $db_data['image_path'] = implode(',', $image_paths);
            $db_data['post_type'] = 'carousel';
        }
        
        // Handle single image by ID
        if (!empty($post_data['image_id'])) {
            $attachment_url = wp_get_attachment_url($post_data['image_id']);
            $attachment_path = get_attached_file($post_data['image_id']);
            
            if ($attachment_url) {
                $db_data['image_url'] = $attachment_url;
                $db_data['image_path'] = $attachment_path;
            }
        }
        
        // Handle video by ID
        if (!empty($post_data['video_id'])) {
            $attachment_url = wp_get_attachment_url($post_data['video_id']);
            $attachment_path = get_attached_file($post_data['video_id']);
            
            if ($attachment_url) {
                $db_data['video_url'] = $attachment_url;
                $db_data['video_path'] = $attachment_path;
            }
        }
        
        if (!empty($post_data['video_file'])) {
            $upload_result = $this->handle_file_upload($post_data['video_file'], 'video');
            if ($upload_result) {
                $db_data['video_path'] = $upload_result['file'];
                $db_data['video_url'] = $upload_result['url'];
            }
        }
        
        // Insert into database
        $result = $wpdb->insert($table_name, $db_data, array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'
        ));
        
        if ($result === false) {
            FPS_Logger::log('Failed to insert scheduled post into database', 'error');
            return false;
        }
        
        $post_id = $wpdb->insert_id;
        
        // Schedule WordPress cron job as backup
        wp_schedule_single_event($scheduled_datetime->getTimestamp(), 'fps_publish_scheduled_post', array($post_id));
        
        // Try to schedule directly with Facebook if immediate scheduling is preferred
        $post_settings = get_option('fps_post_settings', array());
        $use_facebook_scheduling = isset($post_settings['use_facebook_scheduling']) ? $post_settings['use_facebook_scheduling'] : true;
        
        if ($use_facebook_scheduling) {
            // Convert to UTC for Facebook API
            $utc_datetime = clone $scheduled_datetime;
            $utc_datetime->setTimezone(new DateTimeZone('UTC'));
            $facebook_timestamp = $utc_datetime->getTimestamp();
            
            // Validate Facebook timestamp (must be at least 10 minutes in future)
            $now_utc = new DateTime('now', new DateTimeZone('UTC'));
            $min_future_utc = clone $now_utc;
            $min_future_utc->modify('+10 minutes');
            
            if ($facebook_timestamp < $min_future_utc->getTimestamp()) {
                FPS_Logger::error("[FPS] Facebook timestamp too close to now. Scheduled: {$facebook_timestamp}, Min required: {$min_future_utc->getTimestamp()}");
                // Fall back to WordPress cron
            } else {
                $facebook_result = $this->schedule_with_facebook($post_id, $post_data, $facebook_timestamp);
            }
            
            if ($facebook_result) {
                // Update database with Facebook post ID
                $wpdb->update(
                    $table_name,
                    array(
                        'facebook_post_id' => $facebook_result['id'],
                        'status' => 'scheduled_facebook'
                    ),
                    array('id' => $post_id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                // Clear WordPress cron since Facebook will handle it
                wp_clear_scheduled_hook('fps_publish_scheduled_post', array($post_id));
                
                FPS_Logger::info("[FPS] Post {$post_id} scheduled with Facebook. UTC timestamp: {$facebook_timestamp}");
            }
        }
        
        FPS_Logger::log("Post scheduled with ID {$post_id}", 'info');
        return $post_id;
    }
    
    /**
     * Schedule post directly with Facebook
     * 
     * @param int $post_id Internal post ID
     * @param array $post_data Post data
     * @param int $scheduled_timestamp Scheduled timestamp (UTC)
     * @return array|false Facebook response or false
     */
    private function schedule_with_facebook($post_id, $post_data, $scheduled_timestamp) {
        $facebook_post_data = array(
            'message' => $post_data['message'],
            'scheduled_publish_time' => $scheduled_timestamp
        );
        
        // Add link if provided
        if (!empty($post_data['link'])) {
            $facebook_post_data['link'] = $post_data['link'];
        }
        
        // Add image if provided
        if (!empty($post_data['image_path'])) {
            $facebook_post_data['image_path'] = $post_data['image_path'];
        } elseif (!empty($post_data['image_url'])) {
            $facebook_post_data['image_url'] = $post_data['image_url'];
        }
        
        // Add video if provided
        if (!empty($post_data['video_path'])) {
            $facebook_post_data['video_path'] = $post_data['video_path'];
        }
        
        $result = $this->facebook_api->create_post($post_data['page_id'], $facebook_post_data);
        
        if ($result) {
            FPS_Logger::log("Post {$post_id} scheduled with Facebook, FB ID: {$result['id']}", 'info');
        } else {
            FPS_Logger::log("Failed to schedule post {$post_id} with Facebook", 'error');
        }
        
        return $result;
    }
    
    /**
     * Publish a scheduled post (called by cron)
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    public function publish_post($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Get post data
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND status IN ('scheduled', 'failed')",
            $post_id
        ));
        
        if (!$post) {
            FPS_Logger::log("Post {$post_id} not found or not schedulable", 'error');
            return false;
        }
        
        // Check if it's time to publish
        // Use São Paulo timezone for comparison
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $scheduled_datetime = new DateTime($post->scheduled_time, $timezone);
        $now = new DateTime('now', $timezone);
        
        if ($scheduled_datetime > $now) {
            FPS_Logger::log("Post {$post_id} not ready for publishing yet", 'info');
            return false;
        }
        
        // Update status to publishing
        $wpdb->update(
            $table_name,
            array('status' => 'publishing'),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
        
        // Prepare post data for Facebook
        $facebook_post_data = array(
            'message' => $post->message
        );
        
        if (!empty($post->link)) {
            $facebook_post_data['link'] = $post->link;
        }
        
        if (!empty($post->image_path)) {
            $facebook_post_data['image_path'] = $post->image_path;
        } elseif (!empty($post->image_url)) {
            $facebook_post_data['image_url'] = $post->image_url;
        }
        
        if (!empty($post->video_path)) {
            $facebook_post_data['video_path'] = $post->video_path;
        }
        
        // Publish to Facebook
        $result = $this->facebook_api->create_post($post->page_id, $facebook_post_data);
        
        if ($result) {
            // Success - update database
            $update_data = array(
                'status' => 'published',
                'facebook_post_id' => $result['id'],
                'published_at' => current_time('mysql'),
                'error_message' => ''
            );
            
            // Add permalink if available
            if (isset($result['permalink_url'])) {
                $update_data['permalink'] = $result['permalink_url'];
            } elseif (isset($result['id'])) {
                $update_data['permalink'] = "https://www.facebook.com/{$post->page_id}/posts/{$result['id']}";
            }
            
            $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $post_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            FPS_Logger::log("Post {$post_id} published successfully, FB ID: {$result['id']}", 'info');
            
            // Clean up uploaded files if they exist
            $this->cleanup_post_files($post);
            
            return true;
        } else {
            // Failed - update status and error
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => 'Failed to publish to Facebook',
                    'retry_count' => intval($post->retry_count) + 1
                ),
                array('id' => $post_id),
                array('%s', '%s', '%d'),
                array('%d')
            );
            
            FPS_Logger::log("Post {$post_id} failed to publish", 'error');
            
            // Schedule retry if under retry limit
            $max_retries = 3;
            if (intval($post->retry_count) < $max_retries) {
                $retry_time = time() + (HOUR_IN_SECONDS * pow(2, intval($post->retry_count))); // Exponential backoff
                wp_schedule_single_event($retry_time, 'fps_publish_scheduled_post', array($post_id));
                FPS_Logger::log("Post {$post_id} scheduled for retry", 'info');
            }
            
            return false;
        }
    }
    
    /**
     * Update a scheduled post
     * 
     * @param int $post_id Post ID
     * @param array $post_data Updated post data
     * @return bool Success
     */
    public function update_scheduled_post($post_id, $post_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Get current post data
        $current_post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $post_id
        ));
        
        if (!$current_post) {
            return false;
        }
        
        // Only allow updates for scheduled posts
        if (!in_array($current_post->status, array('scheduled', 'scheduled_facebook', 'failed'))) {
            return false;
        }
        
        // Prepare update data
        $update_data = array();
        $update_formats = array();
        
        if (isset($post_data['message'])) {
            $update_data['message'] = wp_kses_post($post_data['message']);
            $update_formats[] = '%s';
        }
        
        if (isset($post_data['link'])) {
            $update_data['link'] = esc_url_raw($post_data['link']);
            $update_formats[] = '%s';
        }
        
        if (isset($post_data['scheduled_time'])) {
            // Use São Paulo timezone for validation
            $timezone = new DateTimeZone('America/Sao_Paulo');
            $scheduled_datetime = new DateTime($post_data['scheduled_time'], $timezone);
            $now = new DateTime('now', $timezone);
            
            if ($scheduled_datetime > $now) {
                $update_data['scheduled_time'] = date('Y-m-d H:i:s', $scheduled_time);
                $update_formats[] = '%s';
                
                // Reschedule cron job
                wp_clear_scheduled_hook('fps_publish_scheduled_post', array($post_id));
                wp_schedule_single_event($scheduled_datetime->getTimestamp(), 'fps_publish_scheduled_post', array($post_id));
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_formats[] = '%s';
        
        // Update database
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $post_id),
            $update_formats,
            array('%d')
        );
        
        if ($result !== false) {
            // If post was scheduled with Facebook, update it there too
            if ($current_post->status === 'scheduled_facebook' && !empty($current_post->facebook_post_id)) {
                $facebook_update_data = array();
                
                if (isset($post_data['message'])) {
                    $facebook_update_data['message'] = $post_data['message'];
                }
                
                if (isset($post_data['scheduled_time'])) {
                    $facebook_update_data['scheduled_publish_time'] = $scheduled_datetime->getTimestamp();
                }
                
                if (!empty($facebook_update_data)) {
                    $this->facebook_api->update_post($current_post->facebook_post_id, $facebook_update_data);
                }
            }
            
            FPS_Logger::log("Post {$post_id} updated successfully", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete a scheduled post
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    public function delete_scheduled_post($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Get post data
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $post_id
        ));
        
        if (!$post) {
            return false;
        }
        
        // Clear scheduled cron job
        wp_clear_scheduled_hook('fps_publish_scheduled_post', array($post_id));
        
        // Delete from Facebook if it was scheduled there
        if ($post->status === 'scheduled_facebook' && !empty($post->facebook_post_id)) {
            $this->facebook_api->delete_post($post->facebook_post_id);
        }
        
        // Clean up uploaded files
        $this->cleanup_post_files($post);
        
        // Delete from database
        $result = $wpdb->delete(
            $table_name,
            array('id' => $post_id),
            array('%d')
        );
        
        if ($result) {
            FPS_Logger::log("Post {$post_id} deleted successfully", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Get scheduled posts
     * 
     * @param array $args Query arguments
     * @return array Posts
     */
    public function get_scheduled_posts($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Default arguments
        $defaults = array(
            'status' => 'all',
            'page_id' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'scheduled_time',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where_conditions = array();
        $query_params = array();
        
        if ($args['status'] !== 'all') {
            $where_conditions[] = 'status = %s';
            $query_params[] = $args['status'];
        }
        
        if (!empty($args['page_id'])) {
            $where_conditions[] = 'page_id = %s';
            $query_params[] = $args['page_id'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Build ORDER BY clause
        $allowed_orderby = array('id', 'scheduled_time', 'created_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'scheduled_time';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        // Build LIMIT clause
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        $limit_clause = "LIMIT {$offset}, {$limit}";
        
        $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$orderby} {$order} {$limit_clause}";
        
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get post statistics
     * 
     * @return array Statistics
     */
    public function get_post_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $stats = array();
        
        // Total posts
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Posts by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status",
            OBJECT_K
        );
        
        $stats['scheduled'] = isset($status_counts['scheduled']) ? intval($status_counts['scheduled']->count) : 0;
        $stats['scheduled_facebook'] = isset($status_counts['scheduled_facebook']) ? intval($status_counts['scheduled_facebook']->count) : 0;
        $stats['published'] = isset($status_counts['published']) ? intval($status_counts['published']->count) : 0;
        $stats['failed'] = isset($status_counts['failed']) ? intval($status_counts['failed']->count) : 0;
        
        // Posts scheduled for today
        $today = date('Y-m-d');
        $stats['today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(scheduled_time) = %s AND status IN ('scheduled', 'scheduled_facebook')",
            $today
        ));
        
        // Posts scheduled for this week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $stats['this_week'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(scheduled_time) BETWEEN %s AND %s AND status IN ('scheduled', 'scheduled_facebook')",
            $week_start,
            $week_end
        ));
        
        return $stats;
    }
    
    /**
     * Get occupied time slots for a specific date
     * 
     * @param string $date Date in Y-m-d format (optional, defaults to today in São Paulo timezone)
     * @param string $date Date in Y-m-d format (optional, defaults to today)
     * @return array Array of occupied time slots
     */
    public function get_occupied_time_slots($date = null) {
        global $wpdb;
        
        if (!$date) {
            // Use São Paulo timezone for default date
            $timezone = new DateTimeZone('America/Sao_Paulo');
            $now = new DateTime('now', $timezone);
            $date = $now->format('Y-m-d');
        }
        
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $occupied_slots = $wpdb->get_col($wpdb->prepare(
            "SELECT TIME(scheduled_time) as time_slot 
             FROM {$table_name} 
             WHERE DATE(scheduled_time) = %s
             AND status IN ('scheduled', 'scheduled_facebook')
             ORDER BY scheduled_time",
            $date
        ));
        
        return $occupied_slots;
    }
    
    /**
     * Handle file upload
     * 
     * @param array $file File data from $_FILES
     * @param string $type File type (image/video)
     * @return array|false Upload result or false
     */
    private function handle_file_upload($file, $type = 'image') {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Set upload overrides
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => array($this, 'unique_filename_callback')
        );
        
        // Set allowed file types
        if ($type === 'image') {
            $upload_overrides['mimes'] = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif' => 'image/gif',
                'png' => 'image/png',
                'webp' => 'image/webp'
            );
        } elseif ($type === 'video') {
            $upload_overrides['mimes'] = array(
                'mp4' => 'video/mp4',
                'mov' => 'video/quicktime',
                'avi' => 'video/avi',
                'wmv' => 'video/x-ms-wmv'
            );
        }
        
        $upload_result = wp_handle_upload($file, $upload_overrides);
        
        if (isset($upload_result['error'])) {
            FPS_Logger::log('File upload error: ' . $upload_result['error'], 'error');
            return false;
        }
        
        return $upload_result;
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
        return 'fps_' . time() . '_' . wp_generate_password(8, false) . $ext;
    }
    
    /**
     * Clean up uploaded files for a post
     * 
     * @param object $post Post object
     */
    private function cleanup_post_files($post) {
        if (!empty($post->image_path) && file_exists($post->image_path)) {
            wp_delete_file($post->image_path);
        }
        
        if (!empty($post->video_path) && file_exists($post->video_path)) {
            wp_delete_file($post->video_path);
        }
    }
}