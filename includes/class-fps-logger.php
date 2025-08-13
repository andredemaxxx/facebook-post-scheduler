<?php
/**
 * Logger Class
 * 
 * Handles logging of plugin activities
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Log a message
     * 
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = array()) {
        global $wpdb;
        
        // Don't log if logging is disabled
        if (!self::is_logging_enabled()) {
            return;
        }
        
        // Don't log debug messages in production
        if ($level === self::LEVEL_DEBUG && !WP_DEBUG) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'fps_logs';
        
        // Get current user info
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Prepare context data
        $context_json = !empty($context) ? wp_json_encode($context) : '';
        
        // Insert log entry
        $wpdb->insert(
            $table_name,
            array(
                'level' => sanitize_text_field($level),
                'message' => sanitize_text_field($message),
                'context' => $context_json,
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Also log to WordPress debug log if enabled
        if (WP_DEBUG_LOG) {
            $log_message = sprintf(
                '[FPS] [%s] %s',
                strtoupper($level),
                $message
            );
            
            if (!empty($context)) {
                $log_message .= ' Context: ' . wp_json_encode($context);
            }
            
            error_log($log_message);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    public static function error($message, $context = array()) {
        self::log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function warning($message, $context = array()) {
        self::log($message, self::LEVEL_WARNING, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function info($message, $context = array()) {
        self::log($message, self::LEVEL_INFO, $context);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     */
    public static function debug($message, $context = array()) {
        self::log($message, self::LEVEL_DEBUG, $context);
    }
    
    /**
     * Get recent logs
     * 
     * @param array $args Query arguments
     * @return array Log entries
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_logs';
        
        // Default arguments
        $defaults = array(
            'level' => 'all',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where_conditions = array();
        $query_params = array();
        
        if ($args['level'] !== 'all') {
            $where_conditions[] = 'level = %s';
            $query_params[] = $args['level'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Build ORDER BY clause
        $allowed_orderby = array('id', 'level', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
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
     * Get log statistics
     * 
     * @return array Statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_logs';
        
        $stats = array();
        
        // Total logs
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Logs by level
        $level_counts = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$table_name} GROUP BY level",
            OBJECT_K
        );
        
        $stats['error'] = isset($level_counts['error']) ? intval($level_counts['error']->count) : 0;
        $stats['warning'] = isset($level_counts['warning']) ? intval($level_counts['warning']->count) : 0;
        $stats['info'] = isset($level_counts['info']) ? intval($level_counts['info']->count) : 0;
        $stats['debug'] = isset($level_counts['debug']) ? intval($level_counts['debug']->count) : 0;
        
        // Recent activity (last 24 hours)
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stats['recent'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            $yesterday
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old logs
     * 
     * @param int $days Days to keep logs
     */
    public static function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            self::info("Cleaned up {$deleted} old log entries");
        }
    }
    
    /**
     * Clear all logs
     * 
     * @return bool Success
     */
    public static function clear_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_logs';
        $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        if ($result !== false) {
            self::info('All logs cleared');
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if logging is enabled
     * 
     * @return bool True if logging is enabled
     */
    private static function is_logging_enabled() {
        $settings = get_option('fps_post_settings', array());
        return isset($settings['enable_logging']) ? $settings['enable_logging'] : true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}