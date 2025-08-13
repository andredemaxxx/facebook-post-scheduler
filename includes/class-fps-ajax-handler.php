<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests from admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Ajax_Handler {
    
    /**
     * Facebook API instance
     * @var FPS_Facebook_API
     */
    private $facebook_api;
    
    /**
     * Scheduler instance
     * @var FPS_Scheduler
     */
    private $scheduler;
    
    /**
     * Token manager instance
     * @var FPS_Token_Manager
     */
    private $token_manager;
    
    /**
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     * @param FPS_Scheduler $scheduler Scheduler instance
     * @param FPS_Token_Manager $token_manager Token manager instance
     */
    public function __construct($facebook_api, $scheduler, $token_manager) {
        $this->facebook_api = $facebook_api;
        $this->scheduler = $scheduler;
        $this->token_manager = $token_manager;
        
        // Register AJAX handlers
        add_action('wp_ajax_fps_schedule_post', array($this, 'handle_schedule_post'));
        add_action('wp_ajax_fps_update_post', array($this, 'handle_update_post'));
        add_action('wp_ajax_fps_delete_post', array($this, 'handle_delete_post'));
        add_action('wp_ajax_fps_edit_post', array($this, 'handle_edit_post'));
        add_action('wp_ajax_fps_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_fps_disconnect_facebook', array($this, 'handle_disconnect_facebook'));
        add_action('wp_ajax_fps_refresh_pages', array($this, 'handle_refresh_pages'));
        add_action('wp_ajax_fps_get_post_preview', array($this, 'handle_get_post_preview'));
        add_action('wp_ajax_fps_get_insights', array($this, 'handle_get_insights'));
        add_action('wp_ajax_fps_diagnose_pages', array($this, 'handle_diagnose_pages'));
        add_action('wp_ajax_fps_get_page_info', array($this, 'handle_get_page_info'));
        add_action('wp_ajax_fps_get_link_preview', array($this, 'handle_get_link_preview'));
        
        // Calendar post handlers
        add_action('wp_ajax_fps_create_recurring_time', array($this, 'handle_create_recurring_time'));
        add_action('wp_ajax_fps_update_recurring_time', array($this, 'handle_update_recurring_time'));
        add_action('wp_ajax_fps_delete_recurring_time', array($this, 'handle_delete_recurring_time'));
        add_action('wp_ajax_fps_toggle_recurring_time', array($this, 'handle_toggle_recurring_time'));
        add_action('wp_ajax_fps_get_calendar_data', array($this, 'handle_get_calendar_data'));
        add_action('wp_ajax_fps_get_available_time_slots', array($this, 'handle_get_available_time_slots'));
    }
    
    /**
     * Handle schedule post AJAX request
     */
    public function handle_schedule_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate required fields
        $required_fields = array('page_id', 'message', 'scheduled_time');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => sprintf(__('Field %s is required', 'facebook-post-scheduler'), $field)));
            }
        }
        
        // Prepare post data
        $post_data = array(
            'page_id' => sanitize_text_field($_POST['page_id']),
            'message' => wp_kses_post($_POST['message']),
            'scheduled_time' => sanitize_text_field($_POST['scheduled_time']),
            'link' => !empty($_POST['link']) ? esc_url_raw($_POST['link']) : '',
        );
        
        // Handle file uploads
        if (!empty($_FILES['image']['name'])) {
            $post_data['image_file'] = $_FILES['image'];
        }
        
        if (!empty($_FILES['video']['name'])) {
            $post_data['video_file'] = $_FILES['video'];
        }
        
        // Validate scheduled time
        $scheduled_timestamp = strtotime($post_data['scheduled_time']);
        if ($scheduled_timestamp <= time()) {
            wp_send_json_error(array('message' => __('Scheduled time must be in the future', 'facebook-post-scheduler')));
        }
        
        // Schedule the post
        $post_id = $this->scheduler->schedule_post($post_data);
        
        if ($post_id) {
            wp_send_json_success(array(
                'message' => __('Post scheduled successfully!', 'facebook-post-scheduler'),
                'post_id' => $post_id,
                'redirect' => admin_url('admin.php?page=fps-scheduled-posts')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to schedule post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle update post AJAX request
     */
    public function handle_update_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Post ID is required', 'facebook-post-scheduler')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Prepare update data
        $update_data = array();
        
        if (isset($_POST['message'])) {
            $update_data['message'] = wp_kses_post($_POST['message']);
        }
        
        if (isset($_POST['link'])) {
            $update_data['link'] = esc_url_raw($_POST['link']);
        }
        
        if (isset($_POST['scheduled_time'])) {
            $scheduled_timestamp = strtotime($_POST['scheduled_time']);
            if ($scheduled_timestamp > time()) {
                $update_data['scheduled_time'] = sanitize_text_field($_POST['scheduled_time']);
            } else {
                wp_send_json_error(array('message' => __('Scheduled time must be in the future', 'facebook-post-scheduler')));
            }
        }
        
        // Update the post
        $result = $this->scheduler->update_scheduled_post($post_id, $update_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Post updated successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle edit post AJAX request
     */
    public function handle_edit_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Post ID is required', 'facebook-post-scheduler')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Get post data
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $post_id
        ));
        
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'facebook-post-scheduler')));
        }
        
        // Only allow editing of scheduled posts
        if (!in_array($post->status, array('scheduled', 'scheduled_facebook', 'failed'))) {
            wp_send_json_error(array('message' => __('This post cannot be edited', 'facebook-post-scheduler')));
        }
        
        // Return post data for editing
        wp_send_json_success(array(
            'post' => array(
                'id' => $post->id,
                'message' => $post->message,
                'link' => $post->link,
                'scheduled_time' => $post->scheduled_time,
                'page_id' => $post->page_id,
                'status' => $post->status,
                'facebook_post_id' => $post->facebook_post_id
            )
        ));
    }
    
    /**
     * Handle delete post AJAX request
     */
    public function handle_delete_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Post ID is required', 'facebook-post-scheduler')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Delete the post
        $result = $this->scheduler->delete_scheduled_post($post_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Post deleted successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        $token_data = $this->token_manager->get_user_token($user_id);
        
        if (!$token_data) {
            wp_send_json_error(array('message' => __('No Facebook token found. Please connect your account first.', 'facebook-post-scheduler')));
        }
        
        // Test the connection
        $result = $this->facebook_api->test_connection($token_data['access_token']);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Connection test successful!', 'facebook-post-scheduler'),
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle disconnect Facebook AJAX request
     */
    public function handle_disconnect_facebook() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        
        // Remove user token
        $this->token_manager->remove_user_token($user_id);
        
        // Remove user pages
        delete_user_meta($user_id, 'fps_facebook_pages');
        
        // Clear selected pages
        delete_option('fps_selected_pages');
        
        wp_send_json_success(array('message' => __('Facebook account disconnected successfully!', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle refresh pages AJAX request
     */
    public function handle_refresh_pages() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        
        // Get fresh pages from Facebook
        $pages = $this->facebook_api->get_user_pages($user_id);
        
        if ($pages !== false) {
            update_user_meta($user_id, 'fps_facebook_pages', $pages);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Found %d pages', 'facebook-post-scheduler'), count($pages)),
                'pages' => $pages
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh pages', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get page info AJAX request
     */
    public function handle_get_page_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $page_id = sanitize_text_field($_POST['page_id']);
        
        // Get page info from stored pages
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if ($pages) {
            foreach ($pages as $page) {
                if ($page['id'] === $page_id) {
                    wp_send_json_success(array('page' => $page));
                    return;
                }
            }
        }
        
        wp_send_json_error(array('message' => __('Page not found', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle get link preview AJAX request
     */
    public function handle_get_link_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $link = esc_url_raw($_POST['link']);
        
        if (empty($link)) {
            wp_send_json_error(array('message' => __('Link is required', 'facebook-post-scheduler')));
        }
        
        // Get link preview data
        $preview_data = $this->get_link_preview_data($link);
        
        if ($preview_data) {
            wp_send_json_success(array('preview' => $preview_data));
        } else {
            wp_send_json_error(array('message' => __('Failed to get link preview', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get post preview AJAX request
     */
    public function handle_get_post_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
        $link = isset($_POST['link']) ? esc_url_raw($_POST['link']) : '';
        $page_id = isset($_POST['page_id']) ? sanitize_text_field($_POST['page_id']) : '';
        $image_ids = isset($_POST['image_ids']) ? json_decode(stripslashes($_POST['image_ids']), true) : array();
        
        // Generate preview HTML
        $preview_html = $this->generate_post_preview($message, $link, $page_id, $image_ids);
        
        wp_send_json_success(array('preview' => $preview_html));
    }
    
    /**
     * Handle get insights AJAX request
     */
    public function handle_get_insights() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $page_id = isset($_POST['page_id']) ? sanitize_text_field($_POST['page_id']) : '';
        
        if (empty($page_id)) {
            wp_send_json_error(array('message' => __('Page ID is required', 'facebook-post-scheduler')));
        }
        
        // Get insights from Facebook
        $insights = $this->facebook_api->get_page_insights($page_id);
        
        if ($insights !== false) {
            wp_send_json_success(array('insights' => $insights));
        } else {
            wp_send_json_error(array('message' => __('Failed to get insights', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle diagnose pages AJAX request
     */
    public function handle_diagnose_pages() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        
        // Run diagnostics
        $diagnostics = $this->facebook_api->diagnose_pages_issue($user_id);
        
        if ($diagnostics['success']) {
            wp_send_json_success($diagnostics);
        } else {
            wp_send_json_error($diagnostics);
        }
    }
    
    /**
     * Handle create recurring time AJAX request
     */
    public function handle_create_recurring_time() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate required fields
        $required_fields = array('time', 'days');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => sprintf(__('Field %s is required', 'facebook-post-scheduler'), $field)));
            }
        }
        
        $time_data = array(
            'time' => sanitize_text_field($_POST['time']),
            'days' => array_map('intval', $_POST['days'])
        );
        
        $calendar_manager = new FPS_Calendar_Manager();
        $time_id = $calendar_manager->create_recurring_time($time_data);
        
        if ($time_id) {
            wp_send_json_success(array(
                'message' => __('Recurring time created successfully!', 'facebook-post-scheduler'),
                'time_id' => $time_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create recurring time', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle update recurring time AJAX request
     */
    public function handle_update_recurring_time() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        if (empty($_POST['time_id'])) {
            wp_send_json_error(array('message' => __('Time ID is required', 'facebook-post-scheduler')));
        }
        
        $time_id = intval($_POST['time_id']);
        $time_data = array();
        
        if (isset($_POST['time'])) {
            $time_data['time'] = sanitize_text_field($_POST['time']);
        }
        
        if (isset($_POST['days'])) {
            $time_data['days'] = array_map('intval', $_POST['days']);
        }
        
        if (isset($_POST['active'])) {
            $time_data['active'] = (bool) $_POST['active'];
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $result = $calendar_manager->update_recurring_time($time_id, $time_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Recurring time updated successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update recurring time', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle delete recurring time AJAX request
     */
    public function handle_delete_recurring_time() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        if (empty($_POST['time_id'])) {
            wp_send_json_error(array('message' => __('Time ID is required', 'facebook-post-scheduler')));
        }
        
        $time_id = intval($_POST['time_id']);
        
        $calendar_manager = new FPS_Calendar_Manager();
        $result = $calendar_manager->delete_recurring_time($time_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Recurring time deleted successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete recurring time', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle toggle recurring time AJAX request
     */
    public function handle_toggle_recurring_time() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $time_id = intval($_POST['time_id']);
        $active = (bool) $_POST['active'];
        
        $calendar_manager = new FPS_Calendar_Manager();
        $result = $calendar_manager->update_recurring_time($time_id, array('active' => $active));
        
        if ($result) {
            $status = $active ? __('activated', 'facebook-post-scheduler') : __('deactivated', 'facebook-post-scheduler');
            wp_send_json_success(array('message' => sprintf(__('Recurring time %s successfully!', 'facebook-post-scheduler'), $status)));
        } else {
            wp_send_json_error(array('message' => __('Failed to toggle recurring time', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get calendar data AJAX request
     */
    public function handle_get_calendar_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : null;
        
        $calendar_manager = new FPS_Calendar_Manager();
        $calendar_data = $calendar_manager->get_calendar_data($month);
        
        wp_send_json_success(array('calendar_data' => $calendar_data));
    }
    
    /**
     * Handle get available time slots AJAX request
     */
    public function handle_get_available_time_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');
        
        $calendar_manager = new FPS_Calendar_Manager();
        $available_slots = $calendar_manager->get_available_time_slots($date);
        
        wp_send_json_success(array('available_slots' => $available_slots));
    }
    
    /**
     * Handle process multi files AJAX request
     */
    public function handle_process_multi_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        
        if (!isset($_FILES['files'])) {
            wp_send_json_error(array('message' => __('No files uploaded', 'facebook-post-scheduler')));
        }
        
        $token_manager = new FPS_Token_Manager();
        $facebook_api = new FPS_Facebook_API($token_manager);
        $calendar_manager = new FPS_Calendar_Manager();
        $multi_scheduler = new FPS_Multi_Scheduler($facebook_api, $calendar_manager);
        
        $processed_files = $multi_scheduler->process_uploaded_files($_FILES['files'], $file_type);
        
        wp_send_json_success(array('files' => $processed_files));
    }
    
    /**
     * Handle pair multi files AJAX request
     */
    public function handle_pair_multi_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $images = isset($_POST['images']) ? $_POST['images'] : array();
        $texts = isset($_POST['texts']) ? $_POST['texts'] : array();
        $pairing_method = sanitize_text_field($_POST['pairing_method']);
        $manual_texts = isset($_POST['manual_texts']) ? array_map('wp_kses_post', $_POST['manual_texts']) : array();
        
        $token_manager = new FPS_Token_Manager();
        $facebook_api = new FPS_Facebook_API($token_manager);
        $calendar_manager = new FPS_Calendar_Manager();
        $multi_scheduler = new FPS_Multi_Scheduler($facebook_api, $calendar_manager);
        
        $pairs = $multi_scheduler->pair_files($images, $texts, $pairing_method, $manual_texts);
        
        wp_send_json_success(array('pairs' => $pairs));
    }
    
    /**
     * Handle schedule multi posts AJAX request
     */
    public function handle_schedule_multi_posts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
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
        
        $token_manager = new FPS_Token_Manager();
        $facebook_api = new FPS_Facebook_API($token_manager);
        $calendar_manager = new FPS_Calendar_Manager();
        $multi_scheduler = new FPS_Multi_Scheduler($facebook_api, $calendar_manager);
        
        $result = $multi_scheduler->schedule_multiple_posts($page_id, $pairs, $selected_date, $time_slots);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle preview multi posts AJAX request
     */
    public function handle_preview_multi_posts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
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
        
        $token_manager = new FPS_Token_Manager();
        $facebook_api = new FPS_Facebook_API($token_manager);
        $calendar_manager = new FPS_Calendar_Manager();
        $multi_scheduler = new FPS_Multi_Scheduler($facebook_api, $calendar_manager);
        
        $previews = $multi_scheduler->generate_preview($pairs, $page_id, $selected_date, $time_slots);
        
        wp_send_json_success(array('previews' => $previews));
    }
    
    /**
     * Handle upload multi files AJAX request
     */
    public function handle_upload_multi_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        $uploaded_files = array();
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Handle multiple file uploads
        if (isset($_FILES['files'])) {
            $files = $_FILES['files'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );
                    
                    $upload_overrides = array(
                        'test_form' => false,
                        'unique_filename_callback' => array($this, 'unique_filename_callback')
                    );
                    
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
                        $uploaded_files[] = array(
                            'name' => $file['name'],
                            'url' => $upload_result['url'],
                            'file' => $upload_result['file'],
                            'type' => $file_type
                        );
                    }
                }
            }
        }
        
        wp_send_json_success(array('files' => $uploaded_files));
    }
    
    /**
     * Handle pair files AJAX request
     */
    public function handle_pair_files() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $images = isset($_POST['images']) ? $_POST['images'] : array();
        $texts = isset($_POST['texts']) ? $_POST['texts'] : array();
        $pairing_method = sanitize_text_field($_POST['pairing_method']);
        
        $pairs = array();
        
        if ($pairing_method === 'filename') {
            // Pair by filename similarity
            foreach ($images as $image) {
                $image_basename = pathinfo($image['name'], PATHINFO_FILENAME);
                
                foreach ($texts as $text) {
                    $text_basename = pathinfo($text['name'], PATHINFO_FILENAME);
                    
                    if ($image_basename === $text_basename) {
                        $pairs[] = array(
                            'image' => $image,
                            'text' => $text,
                            'content' => file_get_contents($text['file'])
                        );
                        break;
                    }
                }
            }
        } else {
            // Pair by order
            $max_pairs = min(count($images), count($texts));
            
            for ($i = 0; $i < $max_pairs; $i++) {
                $pairs[] = array(
                    'image' => $images[$i],
                    'text' => $texts[$i],
                    'content' => file_get_contents($texts[$i]['file'])
                );
            }
        }
        
        wp_send_json_success(array('pairs' => $pairs));
    }
    
    /**
     * Handle schedule multi posts AJAX request
     */
    public function handle_schedule_multi_posts_v2() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
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
        
        if (empty($page_id) || empty($pairs) || empty($selected_date) || empty($time_slots)) {
            wp_send_json_error(array('message' => __('Missing required data', 'facebook-post-scheduler')));
        }
        
        $scheduled_count = 0;
        $errors = array();
        
        foreach ($pairs as $index => $pair) {
            if (isset($time_slots[$index])) {
                $scheduled_time = $selected_date . ' ' . $time_slots[$index];
                
                $post_data = array(
                    'page_id' => $page_id,
                    'message' => sanitize_textarea_field($pair['content']),
                    'scheduled_time' => $scheduled_time,
                    'image_url' => esc_url_raw($pair['image']['url']),
                    'image_path' => $pair['image']['file']
                );
                
                $post_id = $this->scheduler->schedule_post($post_data);
                
                if ($post_id) {
                    $scheduled_count++;
                } else {
                    $errors[] = sprintf(__('Failed to schedule post %d', 'facebook-post-scheduler'), $index + 1);
                }
            }
        }
        
        if ($scheduled_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d posts scheduled successfully', 'facebook-post-scheduler'), $scheduled_count),
                'scheduled_count' => $scheduled_count,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(array('message' => __('No posts were scheduled', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle save time slots AJAX request
     */
    public function handle_save_time_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $time_slots = isset($_POST['time_slots']) ? array_map('sanitize_text_field', $_POST['time_slots']) : array();
        
        update_option('fps_multi_schedule_times', $time_slots);
        
        wp_send_json_success(array('message' => __('Time slots saved successfully', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle clear multi posts AJAX request
     */
    public function handle_clear_multi_posts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        // Delete only multi-scheduled posts (we'll add a flag for this)
        $deleted = $wpdb->delete(
            $table_name,
            array('status' => 'scheduled_multi'),
            array('%s')
        );
        
        if ($deleted !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d multi posts cleared', 'facebook-post-scheduler'), $deleted)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear multi posts', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get occupied slots AJAX request
     */
    public function handle_get_occupied_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $occupied_slots = $this->scheduler->get_occupied_time_slots($date);
        
        wp_send_json_success(array('occupied_slots' => $occupied_slots));
    }
    
    /**
     * Generate unique filename for uploads
     */
    public function unique_filename_callback($dir, $name, $ext) {
        return 'fps_multi_' . time() . '_' . wp_generate_password(8, false) . $ext;
    }
    
    /**
     * Get link preview data
     * 
     * @param string $link URL to preview
     * @return array|false Preview data or false
     */
    private function get_link_preview_data($link) {
        // Get page content
        $response = wp_remote_get($link, array(
            'timeout' => 10,
            'user-agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse HTML for meta tags
        $preview_data = array(
            'url' => $link,
            'title' => '',
            'description' => '',
            'image' => '',
            'site_name' => ''
        );
        
        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $preview_data['title'] = html_entity_decode(trim($matches[1]));
        }
        
        // Extract Open Graph tags
        if (preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $property = $match[1];
                $content = html_entity_decode($match[2]);
                
                switch ($property) {
                    case 'title':
                        $preview_data['title'] = $content;
                        break;
                    case 'description':
                        $preview_data['description'] = $content;
                        break;
                    case 'image':
                        $preview_data['image'] = $content;
                        break;
                    case 'site_name':
                        $preview_data['site_name'] = $content;
                        break;
                }
            }
        }
        
        // Extract meta description if no OG description
        if (empty($preview_data['description'])) {
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
                $preview_data['description'] = html_entity_decode($matches[1]);
            }
        }
        
        // Set site name from URL if not found
        if (empty($preview_data['site_name'])) {
            $preview_data['site_name'] = parse_url($link, PHP_URL_HOST);
        }
        
        return $preview_data;
    }
    
    /**
     * Generate post preview HTML
     * 
     * @param string $message Post message
     * @param string $link Post link
     * @param string $page_id Page ID
     * @param array $image_ids Image attachment IDs
     * @param int $image_id Single image ID
     * @param int $video_id Video ID
     * @return string Preview HTML
     */
    private function generate_post_preview($message, $link, $page_id = '', $image_ids = array(), $image_id = 0, $video_id = 0) {
        // Get page info
        $page_name = __('Your Facebook Page', 'facebook-post-scheduler');
        $page_avatar = '';
        
        if ($page_id) {
            $user_id = get_current_user_id();
            $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
            
            if ($pages) {
                foreach ($pages as $page) {
                    if ($page['id'] === $page_id) {
                        $page_name = $page['name'];
                        if (isset($page['picture']['data']['url'])) {
                            $page_avatar = $page['picture']['data']['url'];
                        }
                        break;
                    }
                }
            }
        }
        
        // Get link preview if link provided - ENHANCED
        $link_preview = null;
        if (!empty($link)) {
            $link_preview = $this->get_link_preview_data($link);
        }
        
        ob_start();
        ?>
        <div class="fps-post-preview">
            <div class="fps-post-header">
                <div class="fps-post-avatar w-10 h-10 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center">
                    <?php if ($page_avatar): ?>
                    <img src="<?php echo esc_url($page_avatar); ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                    <span class="text-white font-bold text-sm">FB</span>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <div class="fps-page-name font-semibold text-gray-900"><?php echo esc_html($page_name); ?></div>
                    <div class="fps-post-time text-sm text-gray-500"><?php _e('Scheduled post', 'facebook-post-scheduler'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="fps-post-content p-4 text-gray-900">
                <?php echo nl2br(esc_html($message)); ?>
            </div>
            <?php endif; ?>
            
            <?php 
            // Handle multiple images (carousel)
            if (!empty($image_ids) && is_array($image_ids)): ?>
            <div class="fps-post-images">
                <?php if (count($image_ids) === 1): ?>
                <!-- Single image -->
                <?php 
                $attachment = wp_get_attachment_image_src($image_ids[0], 'large');
                if ($attachment): ?>
                <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="w-full h-auto">
                <?php endif; ?>
                <?php else: ?>
                <!-- Carousel -->
                <div class="fps-post-carousel flex space-x-1">
                    <?php foreach (array_slice($image_ids, 0, 3) as $index => $image_id): ?>
                    <?php 
                    $attachment = wp_get_attachment_image_src($image_id, 'medium');
                    if ($attachment): ?>
                    <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="flex-1 h-48 object-cover">
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($image_ids) > 3): ?>
                    <div class="fps-carousel-more absolute bottom-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded text-sm">
                        +<?php echo count($image_ids) - 3; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php 
            // Handle single image
            elseif (!empty($image_id)): 
            $attachment = wp_get_attachment_image_src($image_id, 'large');
            if ($attachment): ?>
            <div class="fps-post-images">
                <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="w-full h-auto">
            </div>
            <?php endif; ?>
            <?php 
            // Handle video
            elseif (!empty($video_id)): 
            $attachment = wp_get_attachment_url($video_id);
            if ($attachment): ?>
            <div class="fps-post-video">
                <video controls class="w-full h-auto">
                    <source src="<?php echo esc_url($attachment); ?>" type="video/mp4">
                </video>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($link)): ?>
            <div class="fps-post-link mx-4 mb-4">
                <?php if ($link_preview): ?>
                <div class="fps-link-preview border border-gray-200 rounded-lg overflow-hidden">
                    <?php if (!empty($link_preview['image'])): ?>
                    <div class="fps-link-image">
                        <img src="<?php echo esc_url($link_preview['image']); ?>" alt="" class="w-full h-48 object-cover">
                    </div>
                    <?php endif; ?>
                    <div class="fps-link-content p-3">
                        <?php if (!empty($link_preview['title'])): ?>
                        <div class="fps-link-title font-semibold text-gray-900 mb-1"><?php echo esc_html($link_preview['title']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($link_preview['description'])): ?>
                        <div class="fps-link-description text-sm text-gray-600 mb-2"><?php echo esc_html(wp_trim_words($link_preview['description'], 20)); ?></div>
                        <?php endif; ?>
                        <div class="fps-link-url text-xs text-gray-500 uppercase"><?php echo esc_html(parse_url($link, PHP_URL_HOST)); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="fps-link-simple p-3 bg-gray-50 rounded-lg">
                    <div class="text-sm text-blue-600">🔗 <?php echo esc_html($link); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="fps-post-actions flex justify-around py-3 border-t border-gray-100 text-gray-500">
                <span class="fps-action cursor-pointer hover:text-blue-600">👍 <?php _e('Like', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action cursor-pointer hover:text-blue-600">💬 <?php _e('Comment', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action cursor-pointer hover:text-blue-600">📤 <?php _e('Share', 'facebook-post-scheduler'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get link preview data - ENHANCED
     * 
     * @param string $link URL to preview
     * @return array|false Preview data or false
     */
    private function get_link_preview_data($link) {
        // Check cache first
        $cache_key = 'fps_link_preview_' . md5($link);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Get page content
        $response = wp_remote_get($link, array(
            'timeout' => 10,
            'user-agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse HTML for meta tags
        $preview_data = array(
            'url' => $link,
            'title' => '',
            'description' => '',
            'image' => '',
            'site_name' => ''
        );
        
        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $preview_data['title'] = html_entity_decode(trim($matches[1]));
        }
        
        // Extract Open Graph tags
        if (preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $property = $match[1];
                $content = html_entity_decode($match[2]);
                
                switch ($property) {
                    case 'title':
                        $preview_data['title'] = $content;
                        break;
                    case 'description':
                        $preview_data['description'] = $content;
                        break;
                    case 'image':
                        $preview_data['image'] = $content;
                        break;
                    case 'site_name':
                        $preview_data['site_name'] = $content;
                        break;
                }
            }
        }
        
        // Extract meta description if no OG description
        if (empty($preview_data['description'])) {
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
                $preview_data['description'] = html_entity_decode($matches[1]);
            }
        }
        
        // Set site name from URL if not found
        if (empty($preview_data['site_name'])) {
            $preview_data['site_name'] = parse_url($link, PHP_URL_HOST);
        }
        
        // Cache for 24 hours
        set_transient($cache_key, $preview_data, DAY_IN_SECONDS);
        
        return $preview_data;
    }
    
    /**
     * Generate post preview HTML - ENHANCED
     * 
     * @param string $message Post message
     * @param string $link Post link
     * @param string $page_id Page ID
     * @param array $image_ids Image attachment IDs (carousel)
     * @param int $image_id Single image ID
     * @param int $video_id Video ID
     * @return string Preview HTML
     */
    private function generate_post_preview($message, $link, $page_id = '', $image_ids = array(), $image_id = 0, $video_id = 0) {
        // Get page info
        $page_name = __('Your Facebook Page', 'facebook-post-scheduler');
        $page_avatar = '';
        
        if ($page_id) {
            $user_id = get_current_user_id();
            $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
            
            if ($pages) {
                foreach ($pages as $page) {
                    if ($page['id'] === $page_id) {
                        $page_name = $page['name'];
                        if (isset($page['picture']['data']['url'])) {
                            $page_avatar = $page['picture']['data']['url'];
                        }
                        break;
                    }
                }
            }
        }
        
        // Get link preview if link provided - ENHANCED
        $link_preview = null;
        if (!empty($link)) {
            $link_preview = $this->get_link_preview_data($link);
        }
        
        ob_start();
        ?>
        <div class="fps-post-preview bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="fps-post-header flex items-center space-x-3 p-4 border-b border-gray-100">
                <div class="fps-post-avatar w-10 h-10 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center">
                    <?php if ($page_avatar): ?>
                    <img src="<?php echo esc_url($page_avatar); ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                    <span class="text-white font-bold text-sm">FB</span>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <div class="fps-page-name font-semibold text-gray-900"><?php echo esc_html($page_name); ?></div>
                    <div class="fps-post-time text-sm text-gray-500"><?php _e('Scheduled post', 'facebook-post-scheduler'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="fps-post-content p-4 text-gray-900">
                <?php echo nl2br(esc_html($message)); ?>
            </div>
            <?php endif; ?>
            
            <?php 
            // Handle multiple images (carousel)
            if (!empty($image_ids) && is_array($image_ids)): ?>
            <div class="fps-post-images">
                <?php if (count($image_ids) === 1): ?>
                <!-- Single image -->
                <?php 
                $attachment = wp_get_attachment_image_src($image_ids[0], 'large');
                if ($attachment): ?>
                <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="w-full h-auto">
                <?php endif; ?>
                <?php else: ?>
                <!-- Carousel -->
                <div class="fps-post-carousel flex space-x-1 relative">
                    <?php foreach (array_slice($image_ids, 0, 3) as $index => $image_id): ?>
                    <?php 
                    $attachment = wp_get_attachment_image_src($image_id, 'medium');
                    if ($attachment): ?>
                    <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="flex-1 h-48 object-cover">
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($image_ids) > 3): ?>
                    <div class="fps-carousel-more absolute bottom-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded text-sm">
                        +<?php echo count($image_ids) - 3; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php 
            // Handle single image
            elseif (!empty($image_id)): 
            $attachment = wp_get_attachment_image_src($image_id, 'large');
            if ($attachment): ?>
            <div class="fps-post-images">
                <img src="<?php echo esc_url($attachment[0]); ?>" alt="" class="w-full h-auto">
            </div>
            <?php endif; ?>
            <?php 
            // Handle video
            elseif (!empty($video_id)): 
            $attachment = wp_get_attachment_url($video_id);
            if ($attachment): ?>
            <div class="fps-post-video">
                <video controls class="w-full h-auto">
                    <source src="<?php echo esc_url($attachment); ?>" type="video/mp4">
                </video>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($link)): ?>
            <div class="fps-post-link mx-4 mb-4">
                <?php if ($link_preview): ?>
                <div class="fps-link-preview border border-gray-200 rounded-lg overflow-hidden">
                    <?php if (!empty($link_preview['image'])): ?>
                    <div class="fps-link-image">
                        <img src="<?php echo esc_url($link_preview['image']); ?>" alt="" class="w-full h-48 object-cover">
                    </div>
                    <?php endif; ?>
                    <div class="fps-link-content p-3">
                        <?php if (!empty($link_preview['title'])): ?>
                        <div class="fps-link-title font-semibold text-gray-900 mb-1"><?php echo esc_html($link_preview['title']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($link_preview['description'])): ?>
                        <div class="fps-link-description text-sm text-gray-600 mb-2"><?php echo esc_html(wp_trim_words($link_preview['description'], 20)); ?></div>
                        <?php endif; ?>
                        <div class="fps-link-url text-xs text-gray-500 uppercase"><?php echo esc_html(parse_url($link, PHP_URL_HOST)); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="fps-link-simple p-3 bg-gray-50 rounded-lg">
                    <div class="text-sm text-blue-600">🔗 <?php echo esc_html($link); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="fps-post-actions flex justify-around py-3 border-t border-gray-100 text-gray-500">
                <span class="fps-action cursor-pointer hover:text-blue-600">👍 <?php _e('Like', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action cursor-pointer hover:text-blue-600">💬 <?php _e('Comment', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action cursor-pointer hover:text-blue-600">📤 <?php _e('Share', 'facebook-post-scheduler'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
                        <?php if (!empty($link_preview['image'])): ?>
                        <img src="<?php echo esc_url($link_preview['image']); ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="fps-link-content">
                        <div class="fps-link-title"><?php echo esc_html($link_preview['title']); ?></div>
                        <?php if (!empty($link_preview['description'])): ?>
                        <div class="fps-link-description"><?php echo esc_html(wp_trim_words($link_preview['description'], 15)); ?></div>
                        <?php endif; ?>
                        <div class="fps-link-url"><?php echo esc_html($link); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="fps-post-actions">
                <span class="fps-action"><?php _e('Like', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action"><?php _e('Comment', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action"><?php _e('Share', 'facebook-post-scheduler'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}