<?php
/**
 * Plugin Name: Facebook Post Scheduler
 * Plugin URI: https://github.com/yourname/facebook-post-scheduler
 * Description: Professional WordPress plugin for scheduling and automating Facebook page posts using Meta's Graph API with OAuth 2.0 authentication, secure token management, and comprehensive scheduling features.
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: facebook-post-scheduler
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FPS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FPS_VERSION', '2.0.0');
define('FPS_MIN_PHP_VERSION', '7.4');
define('FPS_MIN_WP_VERSION', '6.0');

/**
 * Main Facebook Post Scheduler Class
 * 
 * Handles plugin initialization, hooks, and core functionality
 */
class FacebookPostScheduler {
    
    /**
     * Single instance of the plugin
     * @var FacebookPostScheduler
     */
    private static $instance = null;
    
    /**
     * Facebook API handler
     * @var FPS_Facebook_API
     */
    public $facebook_api;
    
    /**
     * Token manager
     * @var FPS_Token_Manager
     */
    public $token_manager;
    
    /**
     * Scheduler handler
     * @var FPS_Scheduler
     */
    public $scheduler;
    
    /**
     * Get single instance
     * 
     * @return FacebookPostScheduler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize the plugin
     */
    private function __construct() {
        // Check system requirements
        if (!$this->check_requirements()) {
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Setup hooks
        $this->setup_hooks();
    }
    
    /**
     * Check system requirements
     * 
     * @return bool
     */
    private function check_requirements() {
        global $wp_version;
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.3', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('Facebook Post Scheduler requires PHP 8.3 or higher. You are running PHP %s.', 'facebook-post-scheduler'),
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check WordPress version
        if (version_compare($wp_version, '6.8', '<')) {
            add_action('admin_notices', function() use ($wp_version) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('Facebook Post Scheduler requires WordPress 6.8 or higher. You are running WordPress %s.', 'facebook-post-scheduler'),
                    $wp_version
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check required PHP extensions
        $required_extensions = ['curl', 'json', 'openssl'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                add_action('admin_notices', function() use ($extension) {
                    echo '<div class="notice notice-error"><p>';
                    printf(
                        __('Facebook Post Scheduler requires the PHP %s extension to be installed.', 'facebook-post-scheduler'),
                        $extension
                    );
                    echo '</p></div>';
                });
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-token-manager.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-facebook-api.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-scheduler.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-admin.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-ajax-handler.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-logger.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-database.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-calendar-manager.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-multi-scheduler.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-timezone-manager.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-multi-admin.php';
        require_once FPS_PLUGIN_PATH . 'includes/class-fps-multi-ajax.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->token_manager = new FPS_Token_Manager();
        $this->facebook_api = new FPS_Facebook_API($this->token_manager);
        $this->scheduler = new FPS_Scheduler($this->facebook_api);
        $this->calendar_manager = new FPS_Calendar_Manager();
        $this->multi_scheduler = new FPS_Multi_Scheduler($this->facebook_api, $this->calendar_manager);
        
        // Initialize admin interface if in admin
        if (is_admin()) {
            new FPS_Admin($this->facebook_api, $this->scheduler);
            new FPS_Ajax_Handler($this->facebook_api, $this->scheduler, $this->token_manager);
            
            // Initialize multi scheduler admin
            $multi_admin = new FPS_Multi_Admin($this->facebook_api, $this->multi_scheduler);
            new FPS_Multi_Ajax($multi_admin);
        }
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('FacebookPostScheduler', 'uninstall'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Cron hooks
        add_action('fps_publish_scheduled_post', array($this->scheduler, 'publish_post'));
        add_action('fps_refresh_tokens', array($this->token_manager, 'refresh_all_tokens'));
        add_action('fps_cleanup_logs', array('FPS_Logger', 'cleanup_old_logs'));
        
        // Custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Create database tables
        FPS_Database::create_tables();
        
        // Schedule recurring tasks
        $this->schedule_recurring_tasks();
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'facebook-post-scheduler',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Add custom cron schedules
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['fps_hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display' => __('Every Hour', 'facebook-post-scheduler')
        );
        
        $schedules['fps_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __('Daily', 'facebook-post-scheduler')
        );
        
        $schedules['fps_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', 'facebook-post-scheduler')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule recurring tasks
     */
    private function schedule_recurring_tasks() {
        // Token refresh (daily)
        if (!wp_next_scheduled('fps_refresh_tokens')) {
            wp_schedule_event(time(), 'fps_daily', 'fps_refresh_tokens');
        }
        
        // Log cleanup (weekly)
        if (!wp_next_scheduled('fps_cleanup_logs')) {
            wp_schedule_event(time(), 'fps_weekly', 'fps_cleanup_logs');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements again
        if (!$this->check_requirements()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Facebook Post Scheduler could not be activated due to system requirements.', 'facebook-post-scheduler'));
        }
        
        // Create database tables
        FPS_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule recurring tasks
        $this->schedule_recurring_tasks();
        
        // Log activation
        FPS_Logger::log('Plugin activated', 'info');
        
        // Set activation flag for redirect
        set_transient('fps_activation_redirect', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('fps_publish_scheduled_post');
        wp_clear_scheduled_hook('fps_refresh_tokens');
        wp_clear_scheduled_hook('fps_cleanup_logs');
        
        // Log deactivation
        FPS_Logger::log('Plugin deactivated', 'info');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove all plugin data if user chooses to
        $remove_data = get_option('fps_remove_data_on_uninstall', false);
        
        if ($remove_data) {
            // Remove database tables
            FPS_Database::drop_tables();
            
            // Remove all options
            delete_option('fps_facebook_app_id');
            delete_option('fps_facebook_app_secret');
            delete_option('fps_facebook_pages');
            delete_option('fps_default_page');
            delete_option('fps_post_settings');
            delete_option('fps_remove_data_on_uninstall');
            
            // Remove user meta
            delete_metadata('user', 0, 'fps_facebook_token', '', true);
            delete_metadata('user', 0, 'fps_facebook_pages', '', true);
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'fps_post_settings' => array(
                'default_status' => 'published',
                'auto_link_preview' => true,
                'image_quality' => 'high',
                'timezone' => get_option('timezone_string', 'UTC')
            )
        );
        
        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}

// Initialize the plugin
function fps_init() {
    return FacebookPostScheduler::get_instance();
}

// Start the plugin
fps_init();