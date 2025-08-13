<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the plugin
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
        add_action('wp_ajax_fps_get_post_preview', array($this, 'handle_get_post_preview'));
        add_action('wp_ajax_fps_get_page_info', array($this, 'handle_get_page_info'));
        add_action('wp_ajax_fps_get_link_preview', array($this, 'handle_get_link_preview'));
        add_action('wp_ajax_fps_edit_post', array($this, 'handle_edit_post'));
        add_action('wp_ajax_fps_delete_post', array($this, 'handle_delete_post'));
        add_action('wp_ajax_fps_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_fps_disconnect_facebook', array($this, 'handle_disconnect_facebook'));
        add_action('wp_ajax_fps_refresh_pages', array($this, 'handle_refresh_pages'));
        add_action('wp_ajax_fps_diagnose_pages', array($this, 'handle_diagnose_pages'));
        
        // Calendar AJAX handlers
        add_action('wp_ajax_fps_create_recurring_time', array($this, 'handle_create_recurring_time'));
        add_action('wp_ajax_fps_update_recurring_time', array($this, 'handle_update_recurring_time'));
        add_action('wp_ajax_fps_delete_recurring_time', array($this, 'handle_delete_recurring_time'));
        add_action('wp_ajax_fps_toggle_recurring_time', array($this, 'handle_toggle_recurring_time'));
        add_action('wp_ajax_fps_get_calendar_data', array($this, 'handle_get_calendar_data'));
        add_action('wp_ajax_fps_get_recurring_times', array($this, 'handle_get_recurring_times'));
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
        $page_id = sanitize_text_field($_POST['page_id']);
        $message = wp_kses_post($_POST['message']);
        $scheduled_date = sanitize_text_field($_POST['scheduled_date']);
        $scheduled_time = sanitize_text_field($_POST['scheduled_time']);
        
        if (empty($page_id) || empty($message) || empty($scheduled_date) || empty($scheduled_time)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'facebook-post-scheduler')));
        }
        
        // Combine date and time
        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;
        
        // Validate future time using São Paulo timezone
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $scheduled_dt = new DateTime($scheduled_datetime, $timezone);
        $now = new DateTime('now', $timezone);
        
        if ($scheduled_dt <= $now) {
            wp_send_json_error(array('message' => __('Scheduled time must be in the future', 'facebook-post-scheduler')));
        }
        
        // Prepare post data
        $post_data = array(
            'page_id' => $page_id,
            'message' => $message,
            'scheduled_time' => $scheduled_datetime,
            'link' => !empty($_POST['link']) ? esc_url_raw($_POST['link']) : '',
            'share_to_story' => !empty($_POST['share_to_story'])
        );
        
        // Handle media uploads
        $this->handle_media_uploads($post_data);
        
        // Schedule the post
        $post_id = $this->scheduler->schedule_post($post_data);
        
        if ($post_id) {
            FPS_Logger::info("[FPS] Post scheduled successfully with ID: {$post_id}");
            wp_send_json_success(array(
                'message' => __('Post scheduled successfully!', 'facebook-post-scheduler'),
                'post_id' => $post_id,
                'redirect' => admin_url('admin.php?page=fps-scheduled-posts')
            ));
        } else {
            FPS_Logger::error("[FPS] Failed to schedule post");
            wp_send_json_error(array('message' => __('Failed to schedule post. Please try again.', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle media uploads for post
     * 
     * @param array &$post_data Post data array (passed by reference)
     */
    private function handle_media_uploads(&$post_data) {
        // Handle multiple images (carousel)
        if (!empty($_POST['image_ids'])) {
            $image_ids = json_decode(stripslashes($_POST['image_ids']), true);
            if (is_array($image_ids)) {
                $images = array();
                foreach ($image_ids as $image_id) {
                    $attachment_url = wp_get_attachment_url($image_id);
                    $attachment_path = get_attached_file($image_id);
                    
                    if ($attachment_url) {
                        $images[] = array(
                            'id' => $image_id,
                            'url' => $attachment_url,
                            'file' => $attachment_path
                        );
                    }
                }
                
                if (!empty($images)) {
                    $post_data['images'] = $images;
                    $post_data['post_type'] = count($images) > 1 ? 'carousel' : 'single';
                }
            }
        }
        
        // Handle single image
        if (!empty($_POST['image_id'])) {
            $post_data['image_id'] = intval($_POST['image_id']);
        }
        
        // Handle video
        if (!empty($_POST['video_id'])) {
            $post_data['video_id'] = intval($_POST['video_id']);
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
        
        $message = wp_kses_post($_POST['message']);
        $link = esc_url_raw($_POST['link']);
        $page_id = sanitize_text_field($_POST['page_id']);
        
        // Get page info
        $page_info = $this->get_page_info($page_id);
        
        // Get link preview if link provided
        $link_preview = null;
        if (!empty($link)) {
            $link_preview = $this->get_link_preview_data($link);
        }
        
        // Handle media
        $media_data = $this->get_media_preview_data();
        
        // Generate preview HTML
        $preview_html = $this->generate_preview_html($message, $link, $link_preview, $page_info, $media_data);
        
        wp_send_json_success(array('preview' => $preview_html));
    }
    
    /**
     * Get page info for preview
     * 
     * @param string $page_id Page ID
     * @return array|null Page info
     */
    private function get_page_info($page_id) {
        if (empty($page_id)) {
            return null;
        }
        
        $user_id = get_current_user_id();
        $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
        
        if (!$pages) {
            return null;
        }
        
        foreach ($pages as $page) {
            if ($page['id'] === $page_id) {
                return $page;
            }
        }
        
        return null;
    }
    
    /**
     * Get link preview data with caching
     * 
     * @param string $url URL to preview
     * @return array|null Link preview data
     */
    private function get_link_preview_data($url) {
        if (empty($url)) {
            return null;
        }
        
        // Check cache first
        $cache_key = 'fps_link_preview_' . md5($url);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Fetch page content
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Parse Open Graph tags
        $preview_data = array(
            'url' => $url,
            'title' => $this->extract_og_tag($html, 'og:title') ?: $this->extract_title_tag($html),
            'description' => $this->extract_og_tag($html, 'og:description') ?: $this->extract_meta_description($html),
            'image' => $this->extract_og_tag($html, 'og:image'),
            'site_name' => $this->extract_og_tag($html, 'og:site_name') ?: parse_url($url, PHP_URL_HOST)
        );
        
        // Cache for 24 hours
        set_transient($cache_key, $preview_data, DAY_IN_SECONDS);
        
        return $preview_data;
    }
    
    /**
     * Extract Open Graph tag from HTML
     * 
     * @param string $html HTML content
     * @param string $property OG property
     * @return string|null Tag content
     */
    private function extract_og_tag($html, $property) {
        if (preg_match('/<meta[^>]+property=["\']' . preg_quote($property) . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']' . preg_quote($property) . '["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        return null;
    }
    
    /**
     * Extract title tag from HTML
     * 
     * @param string $html HTML content
     * @return string|null Title
     */
    private function extract_title_tag($html) {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }
        
        return null;
    }
    
    /**
     * Extract meta description from HTML
     * 
     * @param string $html HTML content
     * @return string|null Description
     */
    private function extract_meta_description($html) {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        return null;
    }
    
    /**
     * Get media preview data
     * 
     * @return array Media data
     */
    private function get_media_preview_data() {
        $media_data = array(
            'type' => 'none',
            'images' => array(),
            'video' => null
        );
        
        // Handle multiple images (carousel)
        if (!empty($_POST['image_ids'])) {
            $image_ids = json_decode(stripslashes($_POST['image_ids']), true);
            if (is_array($image_ids)) {
                foreach ($image_ids as $image_id) {
                    $attachment_url = wp_get_attachment_url($image_id);
                    if ($attachment_url) {
                        $media_data['images'][] = array(
                            'id' => $image_id,
                            'url' => $attachment_url,
                            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                        );
                    }
                }
                
                if (!empty($media_data['images'])) {
                    $media_data['type'] = count($media_data['images']) > 1 ? 'carousel' : 'image';
                }
            }
        }
        
        // Handle single image
        if (!empty($_POST['image_id']) && empty($media_data['images'])) {
            $image_id = intval($_POST['image_id']);
            $attachment_url = wp_get_attachment_url($image_id);
            
            if ($attachment_url) {
                $media_data['images'][] = array(
                    'id' => $image_id,
                    'url' => $attachment_url,
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                );
                $media_data['type'] = 'image';
            }
        }
        
        // Handle video
        if (!empty($_POST['video_id'])) {
            $video_id = intval($_POST['video_id']);
            $attachment_url = wp_get_attachment_url($video_id);
            
            if ($attachment_url) {
                $media_data['video'] = array(
                    'id' => $video_id,
                    'url' => $attachment_url
                );
                $media_data['type'] = 'video';
            }
        }
        
        return $media_data;
    }
    
    /**
     * Generate preview HTML
     * 
     * @param string $message Post message
     * @param string $link Post link
     * @param array|null $link_preview Link preview data
     * @param array|null $page_info Page info
     * @param array $media_data Media data
     * @return string Preview HTML
     */
    private function generate_preview_html($message, $link, $link_preview, $page_info, $media_data) {
        ob_start();
        ?>
        <div class="fps-post-preview">
            <div class="fps-post-header">
                <div class="fps-post-avatar">
                    <?php if ($page_info && isset($page_info['picture']['data']['url'])): ?>
                    <img src="<?php echo esc_url($page_info['picture']['data']['url']); ?>" alt="" class="fps-avatar-placeholder">
                    <?php else: ?>
                    <div class="fps-avatar-placeholder"></div>
                    <?php endif; ?>
                </div>
                <div class="fps-post-info">
                    <div class="fps-page-name">
                        <?php echo $page_info ? esc_html($page_info['name']) : __('Facebook Page', 'facebook-post-scheduler'); ?>
                    </div>
                    <div class="fps-post-time">
                        <?php 
                        $timezone_info = FPS_Timezone_Manager::get_timezone_info();
                        echo esc_html($timezone_info['current_time']);
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="fps-post-content">
                <?php echo nl2br(esc_html($message)); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($media_data['type'] === 'image' && !empty($media_data['images'])): ?>
            <div class="fps-post-media">
                <img src="<?php echo esc_url($media_data['images'][0]['url']); ?>" alt="" class="fps-post-image">
            </div>
            <?php elseif ($media_data['type'] === 'carousel' && !empty($media_data['images'])): ?>
            <div class="fps-post-carousel">
                <?php foreach (array_slice($media_data['images'], 0, 3) as $image): ?>
                <img src="<?php echo esc_url($image['url']); ?>" alt="" class="fps-carousel-image">
                <?php endforeach; ?>
                
                <?php if (count($media_data['images']) > 3): ?>
                <div class="fps-carousel-more">
                    +<?php echo count($media_data['images']) - 3; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($media_data['type'] === 'video' && $media_data['video']): ?>
            <div class="fps-post-media">
                <video controls class="fps-post-video">
                    <source src="<?php echo esc_url($media_data['video']['url']); ?>" type="video/mp4">
                </video>
            </div>
            <?php endif; ?>
            
            <?php if ($link_preview): ?>
            <div class="fps-post-link">
                <div class="fps-link-preview">
                    <?php if (!empty($link_preview['image'])): ?>
                    <div class="fps-link-image">
                        <img src="<?php echo esc_url($link_preview['image']); ?>" alt="">
                    </div>
                    <?php else: ?>
                    <div class="fps-link-image">
                        <span class="dashicons dashicons-admin-links"></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="fps-link-content">
                        <?php if (!empty($link_preview['title'])): ?>
                        <div class="fps-link-title"><?php echo esc_html($link_preview['title']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($link_preview['description'])): ?>
                        <div class="fps-link-description"><?php echo esc_html(wp_trim_words($link_preview['description'], 20)); ?></div>
                        <?php endif; ?>
                        
                        <div class="fps-link-url"><?php echo esc_html($link_preview['site_name'] ?: parse_url($link, PHP_URL_HOST)); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="fps-post-actions">
                <span class="fps-action">
                    <span class="dashicons dashicons-thumbs-up"></span>
                    <?php _e('Like', 'facebook-post-scheduler'); ?>
                </span>
                <span class="fps-action">
                    <span class="dashicons dashicons-admin-comments"></span>
                    <?php _e('Comment', 'facebook-post-scheduler'); ?>
                </span>
                <span class="fps-action">
                    <span class="dashicons dashicons-share"></span>
                    <?php _e('Share', 'facebook-post-scheduler'); ?>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle get page info AJAX request
     */
    public function handle_get_page_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        $page_id = sanitize_text_field($_POST['page_id']);
        $page_info = $this->get_page_info($page_id);
        
        if ($page_info) {
            wp_send_json_success(array('page' => $page_info));
        } else {
            wp_send_json_error(array('message' => __('Page not found', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get link preview AJAX request
     */
    public function handle_get_link_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        $url = esc_url_raw($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error(array('message' => __('Invalid URL', 'facebook-post-scheduler')));
        }
        
        $link_preview = $this->get_link_preview_data($url);
        
        if ($link_preview) {
            wp_send_json_success(array('preview' => $link_preview));
        } else {
            wp_send_json_error(array('message' => __('Failed to load link preview', 'facebook-post-scheduler')));
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
        
        $post_id = intval($_POST['post_id']);
        
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
        
        wp_send_json_success(array('post' => $post));
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
        
        $post_id = intval($_POST['post_id']);
        
        if ($this->scheduler->delete_scheduled_post($post_id)) {
            wp_send_json_success(array('message' => __('Post deleted successfully', 'facebook-post-scheduler')));
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
        $user_token = $this->token_manager->get_user_token($user_id);
        
        if (!$user_token) {
            wp_send_json_error(array('message' => __('No Facebook account connected', 'facebook-post-scheduler')));
        }
        
        $test_result = $this->facebook_api->test_connection($user_token['access_token']);
        
        if ($test_result['success']) {
            wp_send_json_success(array('message' => __('Connection test successful!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => $test_result['message']));
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
        
        // Remove selected pages
        delete_option('fps_selected_pages');
        
        wp_send_json_success(array('message' => __('Facebook account disconnected successfully', 'facebook-post-scheduler')));
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
        $pages = $this->facebook_api->get_user_pages($user_id);
        
        if ($pages !== false) {
            update_user_meta($user_id, 'fps_facebook_pages', $pages);
            wp_send_json_success(array(
                'message' => sprintf(__('%d pages found and updated', 'facebook-post-scheduler'), count($pages))
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh pages', 'facebook-post-scheduler')));
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
        $diagnostic_data = $this->facebook_api->diagnose_pages_issue($user_id);
        
        wp_send_json_success($diagnostic_data);
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
        
        $time = sanitize_text_field($_POST['time']);
        $days = array_map('intval', $_POST['days']);
        
        if (empty($time) || empty($days)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'facebook-post-scheduler')));
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $time_id = $calendar_manager->create_recurring_time(array(
            'time' => $time,
            'days' => $days
        ));
        
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
        
        $time_id = intval($_POST['time_id']);
        $time = sanitize_text_field($_POST['time']);
        $days = array_map('intval', $_POST['days']);
        
        if (empty($time_id) || empty($time) || empty($days)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'facebook-post-scheduler')));
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $success = $calendar_manager->update_recurring_time($time_id, array(
            'time' => $time,
            'days' => $days
        ));
        
        if ($success) {
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
        
        $time_id = intval($_POST['time_id']);
        
        if (empty($time_id)) {
            wp_send_json_error(array('message' => __('Invalid time ID', 'facebook-post-scheduler')));
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $success = $calendar_manager->delete_recurring_time($time_id);
        
        if ($success) {
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
        
        if (empty($time_id)) {
            wp_send_json_error(array('message' => __('Invalid time ID', 'facebook-post-scheduler')));
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $success = $calendar_manager->update_recurring_time($time_id, array('active' => $active));
        
        if ($success) {
            $message = $active 
                ? __('Recurring time activated successfully!', 'facebook-post-scheduler')
                : __('Recurring time deactivated successfully!', 'facebook-post-scheduler');
            
            wp_send_json_success(array('message' => $message));
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
        
        $month = sanitize_text_field($_POST['month']);
        
        $calendar_manager = new FPS_Calendar_Manager();
        $calendar_data = $calendar_manager->get_calendar_data($month);
        
        wp_send_json_success(array('calendar_data' => $calendar_data));
    }
    
    /**
     * Handle get recurring times AJAX request
     */
    public function handle_get_recurring_times() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        $calendar_manager = new FPS_Calendar_Manager();
        $recurring_times = $calendar_manager->get_recurring_times();
        
        wp_send_json_success(array('recurring_times' => $recurring_times));
    }
}