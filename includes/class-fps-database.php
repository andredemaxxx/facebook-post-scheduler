<?php
/**
 * Database Management Class
 * 
 * Handles database table creation and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Database {
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Scheduled posts table
        $scheduled_posts_table = $wpdb->prefix . 'fps_scheduled_posts';
        $scheduled_posts_sql = "CREATE TABLE {$scheduled_posts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id varchar(50) NOT NULL,
            message longtext NOT NULL,
            link varchar(500) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            image_path varchar(500) DEFAULT '',
            video_url varchar(500) DEFAULT '',
            video_path varchar(500) DEFAULT '',
            post_type varchar(20) DEFAULT 'single',
            share_to_story tinyint(1) DEFAULT 0,
            scheduled_time datetime NOT NULL,
            status varchar(20) DEFAULT 'scheduled',
            timezone varchar(50) DEFAULT 'America/Sao_Paulo',
            facebook_post_id varchar(100) DEFAULT '',
            permalink varchar(500) DEFAULT '',
            error_message text,
            retry_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY scheduled_time (scheduled_time),
            KEY status (status),
            KEY post_type (post_type),
            KEY timezone (timezone),
            KEY created_by (created_by),
            KEY facebook_post_id (facebook_post_id)
        ) {$charset_collate};";
        
        // Page tokens table
        $page_tokens_table = $wpdb->prefix . 'fps_page_tokens';
        $page_tokens_sql = "CREATE TABLE {$page_tokens_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id varchar(50) NOT NULL,
            token_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY page_id (page_id)
        ) {$charset_collate};";
        
        // Activity logs table
        $logs_table = $wpdb->prefix . 'fps_logs';
        $logs_sql = "CREATE TABLE {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        // Page insights table (for analytics)
        $insights_table = $wpdb->prefix . 'fps_page_insights';
        $insights_sql = "CREATE TABLE {$insights_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value bigint(20) DEFAULT 0,
            period varchar(20) DEFAULT 'day',
            date_recorded date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric (page_id, metric_name, period, date_recorded),
            KEY page_id (page_id),
            KEY date_recorded (date_recorded)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($scheduled_posts_sql);
        dbDelta($page_tokens_sql);
        dbDelta($logs_sql);
        dbDelta($insights_sql);
        
        // Update database version
        update_option('fps_db_version', FPS_VERSION);
        
        FPS_Logger::log('Database tables created/updated', 'info');
    }
    
    /**
     * Drop plugin database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'fps_scheduled_posts',
            $wpdb->prefix . 'fps_page_tokens',
            $wpdb->prefix . 'fps_logs',
            $wpdb->prefix . 'fps_page_insights'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option('fps_db_version');
    }
    
    /**
     * Check if database needs update
     * 
     * @return bool True if update needed
     */
    public static function needs_update() {
        $current_version = get_option('fps_db_version', '0.0.0');
        return version_compare($current_version, FPS_VERSION, '<');
    }
    
    /**
     * Get database statistics
     * 
     * @return array Statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Table sizes
        $tables = array(
            'scheduled_posts' => $wpdb->prefix . 'fps_scheduled_posts',
            'page_tokens' => $wpdb->prefix . 'fps_page_tokens',
            'logs' => $wpdb->prefix . 'fps_logs',
            'page_insights' => $wpdb->prefix . 'fps_page_insights'
        );
        
        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $stats[$key . '_count'] = intval($count);
        }
        
        // Database version
        $stats['db_version'] = get_option('fps_db_version', '0.0.0');
        
        return $stats;
    }
    
    /**
     * Clean up old data
     * 
     * @param int $days Days to keep
     */
    public static function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old published posts
        $scheduled_posts_table = $wpdb->prefix . 'fps_scheduled_posts';
        $deleted_posts = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$scheduled_posts_table} WHERE status = 'published' AND published_at < %s",
            $cutoff_date
        ));
        
        // Clean up old logs
        $logs_table = $wpdb->prefix . 'fps_logs';
        $deleted_logs = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table} WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean up old insights
        $insights_table = $wpdb->prefix . 'fps_page_insights';
        $deleted_insights = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$insights_table} WHERE date_recorded < %s",
            date('Y-m-d', strtotime("-{$days} days"))
        ));
        
        FPS_Logger::log("Cleanup completed: {$deleted_posts} posts, {$deleted_logs} logs, {$deleted_insights} insights", 'info');
    }
}