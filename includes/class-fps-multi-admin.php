<?php
/**
 * Multi Scheduler Admin Class
 * 
 * Handles multi-post scheduling admin interface - COMPLETELY SEPARATE from normal scheduling
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Multi_Admin {
    
    /**
     * Facebook API instance
     * @var FPS_Facebook_API
     */
    private $facebook_api;
    
    /**
     * Multi scheduler instance
     * @var FPS_Multi_Scheduler
     */
    private $multi_scheduler;
    
    /**
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     * @param FPS_Multi_Scheduler $multi_scheduler Multi scheduler instance
     */
    public function __construct($facebook_api, $multi_scheduler) {
        $this->facebook_api = $facebook_api;
        $this->multi_scheduler = $multi_scheduler;
        
        // ONLY add hooks for multi scheduler - NO overlap with normal admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_multi_scripts'));
    }
    
    /**
     * Enqueue multi scheduler specific scripts - ONLY on multi page
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_multi_scripts($hook) {
        // ONLY load on multi scheduler page - EXACT match
        if ($hook !== 'facebook-scheduler_page_fps-schedule-multi') {
            return;
        }
        
        // Ensure WordPress media scripts are loaded
        wp_enqueue_media();
        
        // Multi scheduler specific scripts
        wp_enqueue_script(
            'fps-multi-script',
            FPS_PLUGIN_URL . 'assets/js/multi-scheduler.js',
            array('jquery', 'wp-util'),
            FPS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'fps-dropzone-style',
            FPS_PLUGIN_URL . 'assets/css/dropzone.css',
            array(),
            FPS_VERSION
        );
        
        // Localize multi scheduler script with SEPARATE nonce
        wp_localize_script('fps-multi-script', 'fpsMulti', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fps_multi_nonce'),
            'currentPage' => $hook,
            'strings' => array(
                'selectPage' => __('Please select a Facebook page', 'facebook-post-scheduler'),
                'noFilesSelected' => __('No files selected', 'facebook-post-scheduler'),
                'invalidFileType' => __('Invalid file type', 'facebook-post-scheduler'),
                'fileTooLarge' => __('File too large', 'facebook-post-scheduler'),
                'processFiles' => __('Processing files...', 'facebook-post-scheduler'),
                'filesProcessed' => __('Files processed successfully', 'facebook-post-scheduler'),
                'schedulingPosts' => __('Scheduling posts...', 'facebook-post-scheduler'),
                'postsScheduled' => __('Posts scheduled successfully!', 'facebook-post-scheduler'),
                'error' => __('An error occurred', 'facebook-post-scheduler'),
                'confirmClear' => __('Are you sure you want to clear all files?', 'facebook-post-scheduler'),
                'groupImages' => __('Group as carousel', 'facebook-post-scheduler'),
                'ungroupImages' => __('Ungroup carousel', 'facebook-post-scheduler'),
                'addToCarousel' => __('Add to carousel', 'facebook-post-scheduler'),
                'removeFromCarousel' => __('Remove from carousel', 'facebook-post-scheduler')
            ),
            'settings' => array(
                'maxImages' => 10,
                'maxImageSize' => 10 * 1024 * 1024, // 10MB
                'maxTextSize' => 1 * 1024 * 1024,   // 1MB
                'allowedImageTypes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                'allowedTextTypes' => array('text/plain'),
                'timezone' => 'America/Sao_Paulo'
            )
        ));
    }
    
    /**
     * Handle file upload for multi scheduler
     * 
     * @param array $files Files from $_FILES
     * @param string $file_type File type (images/texts)
     * @return array Processed files
     */
    public function handle_file_upload($files, $file_type) {
        return $this->multi_scheduler->process_uploaded_files($files, $file_type);
    }
    
    /**
     * Handle file pairing for multi scheduler
     * 
     * @param array $images Image files
     * @param array $texts Text files
     * @param string $pairing_method Pairing method
     * @param array $manual_texts Manual texts
     * @param array $carousel_groups Carousel groupings
     * @return array Paired posts
     */
    public function handle_file_pairing($images, $texts, $pairing_method, $manual_texts = array(), $carousel_groups = array()) {
        return $this->multi_scheduler->pair_files($images, $texts, $pairing_method, $manual_texts, $carousel_groups);
    }
    
    /**
     * Handle multi post scheduling
     * 
     * @param string $page_id Facebook page ID
     * @param array $pairs Paired posts
     * @param string $selected_date Selected date
     * @param array $time_slots Time slots
     * @param bool $share_to_story Share to story option
     * @return array Results
     */
    public function handle_multi_scheduling($page_id, $pairs, $selected_date, $time_slots, $share_to_story = false) {
        return $this->multi_scheduler->schedule_multiple_posts($page_id, $pairs, $selected_date, $time_slots, $share_to_story);
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
        return $this->multi_scheduler->generate_preview($pairs, $page_id, $selected_date, $time_slots);
    }
}