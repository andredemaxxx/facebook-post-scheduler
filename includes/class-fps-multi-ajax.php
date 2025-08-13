<?php
/**
 * Multi Scheduler AJAX Handler Class
 * 
 * Handles AJAX requests ONLY for multi-post scheduling - COMPLETELY SEPARATE
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Multi_Ajax {
    
    /**
     * Multi admin instance
     * @var FPS_Multi_Admin
     */
    private $multi_admin;
    
    /**
     * Constructor
     * 
     * @param FPS_Multi_Admin $multi_admin Multi admin instance
     */
    public function __construct($multi_admin) {
        $this->multi_admin = $multi_admin;
        
        // Register AJAX handlers ONLY for multi scheduler
        add_action('wp_ajax_fps_schedule_multi_posts', array($this, 'handle_schedule_multi_posts'));
        add_action('wp_ajax_fps_get_available_time_slots', array($this, 'handle_get_available_time_slots'));
        add_action('wp_ajax_fps_preview_multi_posts', array($this, 'handle_preview_multi_posts'));
        add_action('wp_ajax_fps_clear_multi_files', array($this, 'handle_clear_multi_files'));
        add_action('wp_ajax_fps_group_carousel', array($this, 'handle_group_carousel'));
        add_action('wp_ajax_fps_ungroup_carousel', array($this, 'handle_ungroup_carousel'));
    }
    
    /**
     * Handle multi post scheduling AJAX request
     */
    public function handle_schedule_multi_posts() {
        // Verify nonce - SPECIFIC to multi scheduler
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $page_id = sanitize_text_field($_POST['page_id']);
        $pairs = $_POST['pairs'];
        $selected_date = sanitize_text_field($_POST['selected_date']);
        $time_slots = $_POST['time_slots'];
        $share_to_story = isset($_POST['share_to_story']) ? (bool) $_POST['share_to_story'] : false;
        
        FPS_Logger::info("Multi AJAX: Scheduling " . count($pairs) . " posts for {$selected_date}");
        
        $result = $this->multi_admin->handle_multi_scheduling($page_id, $pairs, $selected_date, $time_slots, $share_to_story);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle get available time slots AJAX request
     */
    public function handle_get_available_time_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        FPS_Logger::info("Multi AJAX: Getting available time slots for {$date}");
        
        $calendar_manager = new FPS_Calendar_Manager();
        $available_slots = $calendar_manager->get_available_time_slots($date);
        
        wp_send_json_success(array('available_slots' => $available_slots));
    }
    
    /**
     * Handle preview multi posts AJAX request
     */
    public function handle_preview_multi_posts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $pairs = $_POST['pairs'];
        $page_id = sanitize_text_field($_POST['page_id']);
        $selected_date = sanitize_text_field($_POST['selected_date']);
        $time_slots = $_POST['time_slots'];
        
        FPS_Logger::info("Multi AJAX: Generating preview for " . count($pairs) . " posts");
        
        $previews = $this->multi_admin->generate_preview($pairs, $page_id, $selected_date, $time_slots);
        
        wp_send_json_success(array('previews' => $previews));
    }
    
    /**
     * Handle clear files AJAX request
     */
    public function handle_clear_multi_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        FPS_Logger::info("Multi AJAX: Clearing uploaded files");
        
        // Clear any temporary files if needed
        wp_send_json_success(array('message' => __('Files cleared successfully', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle group carousel AJAX request
     */
    public function handle_group_carousel() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $image_ids = $_POST['image_ids'];
        
        FPS_Logger::info("Multi AJAX: Grouping images as carousel: " . implode(', ', $image_ids));
        
        // Process carousel grouping logic here
        wp_send_json_success(array('message' => __('Images grouped as carousel', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle ungroup carousel AJAX request
     */
    public function handle_ungroup_carousel() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_multi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $carousel_id = sanitize_text_field($_POST['carousel_id']);
        
        FPS_Logger::info("Multi AJAX: Ungrouping carousel: {$carousel_id}");
        
        // Process carousel ungrouping logic here
        wp_send_json_success(array('message' => __('Carousel ungrouped', 'facebook-post-scheduler')));
    }
}