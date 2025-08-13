<?php
/**
 * Admin Interface Class
 * 
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Admin {
    
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
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     * @param FPS_Scheduler $scheduler Scheduler instance
     */
    public function __construct($facebook_api, $scheduler) {
        $this->facebook_api = $facebook_api;
        $this->scheduler = $scheduler;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('admin_init', array($this, 'handle_activation_redirect'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Facebook Scheduler', 'facebook-post-scheduler'),
            __('Facebook Scheduler', 'facebook-post-scheduler'),
            'manage_options',
            'facebook-post-scheduler',
            array($this, 'dashboard_page'),
            'dashicons-facebook',
            30
        );
        
        // Dashboard (same as main page)
        add_submenu_page(
            'facebook-post-scheduler',
            __('Dashboard', 'facebook-post-scheduler'),
            __('Dashboard', 'facebook-post-scheduler'),
            'manage_options',
            'facebook-post-scheduler',
            array($this, 'dashboard_page')
        );
        
        // Schedule Post
        add_submenu_page(
            'facebook-post-scheduler',
            __('Schedule Post', 'facebook-post-scheduler'),
            __('Schedule Post', 'facebook-post-scheduler'),
            'manage_options',
            'fps-schedule-post',
            array($this, 'schedule_post_page')
        );
        
        // Schedule Post Multi
        add_submenu_page(
            'facebook-post-scheduler',
            __('Schedule Post Multi', 'facebook-post-scheduler'),
            __('Schedule Post Multi', 'facebook-post-scheduler'),
            'manage_options',
            'fps-schedule-multi',
            array($this, 'schedule_multi_page')
        );
        
        // Scheduled Posts
        add_submenu_page(
            'facebook-post-scheduler',
            __('Scheduled Posts', 'facebook-post-scheduler'),
            __('Scheduled Posts', 'facebook-post-scheduler'),
            'manage_options',
            'fps-scheduled-posts',
            array($this, 'scheduled_posts_page')
        );
        
        // Calendar Post
        add_submenu_page(
            'facebook-post-scheduler',
            __('Calendar Post', 'facebook-post-scheduler'),
            __('Calendar Post', 'facebook-post-scheduler'),
            'manage_options',
            'fps-calendar-post',
            array($this, 'calendar_post_page')
        );
        
        // Analytics
        add_submenu_page(
            'facebook-post-scheduler',
            __('Analytics', 'facebook-post-scheduler'),
            __('Analytics', 'facebook-post-scheduler'),
            'manage_options',
            'fps-analytics',
            array($this, 'analytics_page')
        );
        
        // Settings
        add_submenu_page(
            'facebook-post-scheduler',
            __('Settings', 'facebook-post-scheduler'),
            __('Settings', 'facebook-post-scheduler'),
            'manage_options',
            'fps-settings',
            array($this, 'settings_page')
        );
        
        // Logs (only show if debug is enabled)
        if (WP_DEBUG) {
            add_submenu_page(
                'facebook-post-scheduler',
                __('Logs', 'facebook-post-scheduler'),
                __('Logs', 'facebook-post-scheduler'),
                'manage_options',
                'fps-logs',
                array($this, 'logs_page')
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Get current screen for precise page detection
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'facebook-post-scheduler') === false && strpos($screen->id, 'fps-') === false)) {
            return;
        }
        
        // Only enqueue media scripts on pages that need them
        if (strpos($hook, 'fps-schedule-post') !== false || strpos($hook, 'fps-schedule-multi') !== false) {
            wp_enqueue_media();
        }
        
        // Enqueue admin styles (common for all plugin pages)
        wp_enqueue_style(
            'fps-admin-style',
            FPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FPS_VERSION
        );
        
        // Enqueue specific scripts based on exact page
        if (strpos($hook, 'fps-schedule-post') !== false && strpos($hook, 'fps-schedule-multi') === -1) {
            // ONLY Normal schedule post scripts (NOT multi)
            wp_enqueue_script(
                'fps-admin-script',
                FPS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-util'),
                FPS_VERSION,
                true
            );
            
            wp_localize_script('fps-admin-script', 'fpsAdmin', $this->get_admin_localization($hook));
            
        } elseif (strpos($hook, 'facebook-post-scheduler') !== false || 
                  strpos($hook, 'fps-scheduled-posts') !== false ||
                  strpos($hook, 'fps-analytics') !== false ||
                  strpos($hook, 'fps-settings') !== false ||
                  strpos($hook, 'fps-logs') !== false) {
            // General admin scripts for dashboard, settings, etc.
            wp_enqueue_script(
                'fps-admin-script',
                FPS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-util'),
                FPS_VERSION,
                true
            );
            
            wp_localize_script('fps-admin-script', 'fpsAdmin', $this->get_admin_localization($hook));
        }
        
        // Calendar specific scripts - SEPARATE from others
        if (strpos($hook, 'fps-calendar-post') !== false) {
            wp_enqueue_script(
                'fps-calendar-script',
                FPS_PLUGIN_URL . 'assets/js/calendar.js',
                array('jquery', 'wp-util'),
                FPS_VERSION,
                true
            );
            
            wp_localize_script('fps-calendar-script', 'fpsCalendar', $this->get_calendar_localization());
        }
    }
    
    /**
     * Get admin localization data
     * 
     * @param string $hook Current page hook
     * @return array Localization data
     */
    private function get_admin_localization($hook) {
        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fps_admin_nonce'),
            'currentPage' => $hook,
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this post?', 'facebook-post-scheduler'),
                'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'facebook-post-scheduler'),
                'saving' => __('Saving...', 'facebook-post-scheduler'),
                'saved' => __('Saved!', 'facebook-post-scheduler'),
                'error' => __('Error occurred', 'facebook-post-scheduler'),
                'success' => __('Success!', 'facebook-post-scheduler'),
                'testing' => __('Testing...', 'facebook-post-scheduler'),
                'selectPage' => __('Please select a Facebook page', 'facebook-post-scheduler'),
                'enterMessage' => __('Please enter a message', 'facebook-post-scheduler'),
                'selectDateTime' => __('Please select a date and time', 'facebook-post-scheduler'),
                'pastDateTime' => __('Scheduled time must be in the future', 'facebook-post-scheduler'),
                'uploadFiles' => __('Upload Files', 'facebook-post-scheduler'),
                'processFiles' => __('Processing files...', 'facebook-post-scheduler'),
                'filesProcessed' => __('Files processed successfully', 'facebook-post-scheduler'),
                'noFilesSelected' => __('No files selected', 'facebook-post-scheduler'),
                'invalidFileType' => __('Invalid file type', 'facebook-post-scheduler'),
                'fileTooLarge' => __('File too large', 'facebook-post-scheduler'),
                'loadingPreview' => __('Loading preview...', 'facebook-post-scheduler'),
                'previewError' => __('Failed to load preview', 'facebook-post-scheduler')
            ),
            'settings' => array(
                'dateFormat' => get_option('date_format'),
                'timeFormat' => get_option('time_format'),
                'timezone' => 'America/Sao_Paulo',
                'maxImages' => 10,
                'maxImageSize' => 10 * 1024 * 1024, // 10MB
                'maxVideoSize' => 100 * 1024 * 1024 // 100MB
            )
        );
    }
    
    /**
     * Get calendar localization data
     * 
     * @return array Localization data
     */
    private function get_calendar_localization() {
        $calendar_manager = new FPS_Calendar_Manager();
        $calendar_data = $calendar_manager->get_calendar_data();
        
        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fps_admin_nonce'),
            'currentMonth' => $calendar_data['month'],
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this recurring time?', 'facebook-post-scheduler'),
                'editTime' => __('Edit Recurring Time', 'facebook-post-scheduler'),
                'addTime' => __('Create Recurring Times', 'facebook-post-scheduler'),
                'timeCreated' => __('Recurring time created successfully!', 'facebook-post-scheduler'),
                'timeUpdated' => __('Recurring time updated successfully!', 'facebook-post-scheduler'),
                'timeDeleted' => __('Recurring time deleted successfully!', 'facebook-post-scheduler'),
                'error' => __('An error occurred', 'facebook-post-scheduler'),
                'selectDays' => __('Please select at least one day', 'facebook-post-scheduler'),
                'invalidTime' => __('Please enter a valid time', 'facebook-post-scheduler')
            )
        );
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'fps-settings') {
            return;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }
        
        // Verify state parameter
        if (!wp_verify_nonce($_GET['state'], 'fps_facebook_oauth')) {
            wp_die(__('Invalid OAuth state parameter', 'facebook-post-scheduler'));
        }
        
        $code = sanitize_text_field($_GET['code']);
        $redirect_uri = admin_url('admin.php?page=fps-settings');
        
        // Exchange code for token
        $token_data = $this->facebook_api->exchange_code_for_token($code, $redirect_uri);
        
        if ($token_data) {
            // Store user token
            $user_id = get_current_user_id();
            $token_manager = new FPS_Token_Manager();
            $token_manager->store_user_token($user_id, $token_data);
            
            // Get user pages
            $pages = $this->facebook_api->get_user_pages($user_id);
            
            if ($pages) {
                update_user_meta($user_id, 'fps_facebook_pages', $pages);
                
                add_settings_error(
                    'fps_messages',
                    'fps_oauth_success',
                    __('Successfully connected to Facebook! Please select your pages below.', 'facebook-post-scheduler'),
                    'success'
                );
            } else {
                add_settings_error(
                    'fps_messages',
                    'fps_oauth_no_pages',
                    __('Connected to Facebook but no pages found. Make sure you have admin access to at least one Facebook page.', 'facebook-post-scheduler'),
                    'warning'
                );
            }
        } else {
            add_settings_error(
                'fps_messages',
                'fps_oauth_error',
                __('Failed to connect to Facebook. Please try again.', 'facebook-post-scheduler'),
                'error'
            );
        }
        
        // Redirect to remove parameters from URL
        wp_redirect(admin_url('admin.php?page=fps-settings'));
        exit;
    }
    
    /**
     * Handle activation redirect
     */
    public function handle_activation_redirect() {
        if (get_transient('fps_activation_redirect')) {
            delete_transient('fps_activation_redirect');
            
            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=facebook-post-scheduler'));
                exit;
            }
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if Facebook app is configured
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if ((!$app_id || !$app_secret) && $this->is_plugin_page()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            printf(
                __('Facebook Post Scheduler needs to be configured. Please <a href="%s">configure your Facebook app settings</a>.', 'facebook-post-scheduler'),
                admin_url('admin.php?page=fps-settings')
            );
            echo '</p>';
            echo '</div>';
        }
        
        // Check if user has connected Facebook
        $user_id = get_current_user_id();
        $token_manager = new FPS_Token_Manager();
        $user_token = $token_manager->get_user_token($user_id);
        
        if (!$user_token && $app_id && $app_secret && $this->is_plugin_page()) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>';
            printf(
                __('Connect your Facebook account to start scheduling posts. <a href="%s">Go to Settings</a>.', 'facebook-post-scheduler'),
                admin_url('admin.php?page=fps-settings')
            );
            echo '</p>';
            echo '</div>';
        }
        
        // Show settings errors
        settings_errors('fps_messages');
    }
    
    /**
     * Check if current page is a plugin page
     * 
     * @return bool True if on plugin page
     */
    private function is_plugin_page() {
        $screen = get_current_screen();
        return $screen && (strpos($screen->id, 'facebook-post-scheduler') !== false || strpos($screen->id, 'fps-') !== false);
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $stats = $this->scheduler->get_post_statistics();
        $recent_posts = $this->scheduler->get_scheduled_posts(array(
            'limit' => 5,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
        
        include FPS_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Schedule post page
     */
    public function schedule_post_page() {
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        include FPS_PLUGIN_PATH . 'templates/admin/schedule-post.php';
    }
    
    /**
     * Calendar post page
     */
    public function calendar_post_page() {
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $calendar_data = $calendar_manager->get_calendar_data();
        $recurring_schedules = $calendar_manager->get_recurring_schedules();
        
        include FPS_PLUGIN_PATH . 'templates/admin/calendar-post.php';
    }
    
    /**
     * Schedule multi page
     */
    public function schedule_multi_page() {
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        include FPS_PLUGIN_PATH . 'templates/admin/schedule-multi.php';
    }
    
    /**
     * Scheduled posts page
     */
    public function scheduled_posts_page() {
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $this->handle_bulk_actions();
        }
        
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $page_filter = isset($_GET['page_id']) ? sanitize_text_field($_GET['page_id']) : '';
        
        // Get posts
        $args = array(
            'status' => $status_filter,
            'page_id' => $page_filter,
            'limit' => 20,
            'orderby' => 'scheduled_time',
            'order' => 'ASC'
        );
        
        $posts = $this->scheduler->get_scheduled_posts($args);
        $stats = $this->scheduler->get_post_statistics();
        
        // Get user pages for filter
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        include FPS_PLUGIN_PATH . 'templates/admin/scheduled-posts.php';
    }
    
    /**
     * Analytics page
     */
    public function analytics_page() {
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        $selected_page = isset($_GET['page_id']) ? sanitize_text_field($_GET['page_id']) : '';
        $insights_data = array();
        
        if ($selected_page) {
            $insights_data = $this->facebook_api->get_page_insights($selected_page);
        }
        
        include FPS_PLUGIN_PATH . 'templates/admin/analytics.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Handle form submissions
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }
        
        $app_id = get_option('fps_facebook_app_id', '');
        $app_secret = get_option('fps_facebook_app_secret', '');
        $post_settings = get_option('fps_post_settings', array());
        
        $user_id = get_current_user_id();
        $token_manager = new FPS_Token_Manager();
        $user_token = $token_manager->get_user_token($user_id);
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            $pages = array();
        }
        
        $selected_pages = get_option('fps_selected_pages', array());
        
        include FPS_PLUGIN_PATH . 'templates/admin/settings.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        // Handle log actions
        if (isset($_POST['action'])) {
            $this->handle_log_actions();
        }
        
        $level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : 'all';
        
        $logs = FPS_Logger::get_logs(array(
            'level' => $level_filter,
            'limit' => 50
        ));
        
        $stats = FPS_Logger::get_statistics();
        
        include FPS_PLUGIN_PATH . 'templates/admin/logs.php';
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['fps_settings_nonce'], 'fps_save_settings')) {
            wp_die(__('Security check failed', 'facebook-post-scheduler'));
        }
        
        // Save Facebook app settings
        if (isset($_POST['facebook_app_id'])) {
            update_option('fps_facebook_app_id', sanitize_text_field($_POST['facebook_app_id']));
        }
        
        if (isset($_POST['facebook_app_secret'])) {
            update_option('fps_facebook_app_secret', sanitize_text_field($_POST['facebook_app_secret']));
        }
        
        // Save selected pages
        if (isset($_POST['selected_pages'])) {
            $selected_pages = array_map('sanitize_text_field', $_POST['selected_pages']);
            update_option('fps_selected_pages', $selected_pages);
        } else {
            update_option('fps_selected_pages', array());
        }
        
        // Save post settings
        $post_settings = array();
        
        if (isset($_POST['default_status'])) {
            $post_settings['default_status'] = sanitize_text_field($_POST['default_status']);
        }
        
        if (isset($_POST['auto_link_preview'])) {
            $post_settings['auto_link_preview'] = true;
        } else {
            $post_settings['auto_link_preview'] = false;
        }
        
        if (isset($_POST['use_facebook_scheduling'])) {
            $post_settings['use_facebook_scheduling'] = true;
        } else {
            $post_settings['use_facebook_scheduling'] = false;
        }
        
        if (isset($_POST['enable_logging'])) {
            $post_settings['enable_logging'] = true;
        } else {
            $post_settings['enable_logging'] = false;
        }
        
        if (isset($_POST['image_quality'])) {
            $post_settings['image_quality'] = sanitize_text_field($_POST['image_quality']);
        }
        
        update_option('fps_post_settings', $post_settings);
        
        add_settings_error(
            'fps_messages',
            'fps_settings_saved',
            __('Settings saved successfully!', 'facebook-post-scheduler'),
            'success'
        );
    }
    
    /**
     * Handle bulk actions on scheduled posts
     */
    private function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-posts')) {
            wp_die(__('Security check failed', 'facebook-post-scheduler'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        $post_ids = isset($_POST['post']) ? array_map('intval', $_POST['post']) : array();
        
        if (empty($post_ids)) {
            return;
        }
        
        $count = 0;
        
        switch ($action) {
            case 'delete':
                foreach ($post_ids as $post_id) {
                    if ($this->scheduler->delete_scheduled_post($post_id)) {
                        $count++;
                    }
                }
                
                add_settings_error(
                    'fps_messages',
                    'fps_bulk_delete',
                    sprintf(_n('%d post deleted.', '%d posts deleted.', $count, 'facebook-post-scheduler'), $count),
                    'success'
                );
                break;
        }
    }
    
    /**
     * Handle log actions
     */
    private function handle_log_actions() {
        if (!wp_verify_nonce($_POST['fps_logs_nonce'], 'fps_log_actions')) {
            wp_die(__('Security check failed', 'facebook-post-scheduler'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'clear_logs':
                if (FPS_Logger::clear_logs()) {
                    add_settings_error(
                        'fps_messages',
                        'fps_logs_cleared',
                        __('All logs cleared successfully!', 'facebook-post-scheduler'),
                        'success'
                    );
                }
                break;
                
            case 'cleanup_logs':
                FPS_Logger::cleanup_old_logs(30);
                add_settings_error(
                    'fps_messages',
                    'fps_logs_cleaned',
                    __('Old logs cleaned up successfully!', 'facebook-post-scheduler'),
                    'success'
                );
                break;
        }
    }
}