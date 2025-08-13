<?php
/**
 * Calendar Manager Class
 * 
 * Handles recurring TIME management for Calendar Post functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Calendar_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize calendar manager
     */
    public function init() {
        // Create recurring times table
        $this->create_recurring_times_table();
        
        // Setup cron schedules
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        
        // Hook for processing recurring posts
        add_action('fps_process_recurring_posts', array($this, 'process_recurring_posts'));
        
        // Schedule recurring cron if not already scheduled
        $this->schedule_recurring_cron();
    }
    
    /**
     * Create recurring times table
     */
    private function create_recurring_times_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            time varchar(5) NOT NULL,
            days varchar(20) NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY time_active (time, active),
            KEY days (days)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        FPS_Logger::info('[FPS] Recurring times table created/updated');
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules($schedules) {
        $schedules['fps_every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'facebook-post-scheduler')
        );
        
        $schedules['fps_every_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'facebook-post-scheduler')
        );
        
        return $schedules;
    }
    
    /**
     * Create recurring time
     * 
     * @param array $time_data Time data
     * @return int|false Time ID or false
     */
    public function create_recurring_time($time_data) {
        global $wpdb;
        
        $required_fields = array('time', 'days');
        
        foreach ($required_fields as $field) {
            if (empty($time_data[$field])) {
                FPS_Logger::error("[FPS] Missing required field for recurring time: {$field}");
                return false;
            }
        }
        
        // Validate time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_data['time'])) {
            FPS_Logger::error("[FPS] Invalid time format: {$time_data['time']}");
            return false;
        }
        
        // Validate days (array of 0-6)
        $days = $time_data['days'];
        if (!is_array($days) || empty($days)) {
            FPS_Logger::error("[FPS] Invalid days data");
            return false;
        }
        
        foreach ($days as $day) {
            if (!is_numeric($day) || $day < 0 || $day > 6) {
                FPS_Logger::error("[FPS] Invalid day value: {$day}");
                return false;
            }
        }
        
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'time' => sanitize_text_field($time_data['time']),
                'days' => implode(',', array_map('intval', $days)),
                'active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s')
        );
        
        if ($result !== false) {
            $time_id = $wpdb->insert_id;
            FPS_Logger::info("[FPS] Recurring time created with ID: {$time_id}");
            return $time_id;
        }
        
        FPS_Logger::error("[FPS] Failed to create recurring time");
        return false;
    }
    
    /**
     * Update recurring time
     * 
     * @param int $time_id Time ID
     * @param array $time_data Updated time data
     * @return bool Success
     */
    public function update_recurring_time($time_id, $time_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        
        $update_data = array();
        $update_formats = array();
        
        if (isset($time_data['time'])) {
            if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_data['time'])) {
                $update_data['time'] = sanitize_text_field($time_data['time']);
                $update_formats[] = '%s';
            }
        }
        
        if (isset($time_data['days'])) {
            if (is_array($time_data['days']) && !empty($time_data['days'])) {
                $update_data['days'] = implode(',', array_map('intval', $time_data['days']));
                $update_formats[] = '%s';
            }
        }
        
        if (isset($time_data['active'])) {
            $update_data['active'] = (bool) $time_data['active'] ? 1 : 0;
            $update_formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_formats[] = '%s';
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $time_id),
            $update_formats,
            array('%d')
        );
        
        if ($result !== false) {
            FPS_Logger::info("[FPS] Recurring time updated: {$time_id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete recurring time
     * 
     * @param int $time_id Time ID
     * @return bool Success
     */
    public function delete_recurring_time($time_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $time_id),
            array('%d')
        );
        
        if ($result !== false) {
            FPS_Logger::info("[FPS] Recurring time deleted: {$time_id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all recurring times - FIXED method name
     * 
     * @param array $args Query arguments
     * @return array Times
     */
    public function get_recurring_times($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        
        $defaults = array(
            'active_only' => false,
            'orderby' => 'time',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clause = '';
        if ($args['active_only']) {
            $where_clause = 'WHERE active = 1';
        }
        
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $query = "SELECT * FROM {$table_name} {$where_clause} {$order_clause}";
        $times = $wpdb->get_results($query);
        
        $formatted_times = array();
        
        foreach ($times as $time) {
            $days = explode(',', $time->days);
            $day_names = array();
            
            foreach ($days as $day) {
                $day_names[] = $this->get_day_name(intval($day));
            }
            
            $formatted_times[] = array(
                'id' => $time->id,
                'time' => $time->time,
                'days' => $days,
                'day_names' => $day_names,
                'active' => (bool) $time->active,
                'created_at' => $time->created_at,
                'updated_at' => $time->updated_at
            );
        }
        
        return $formatted_times;
    }
    
    /**
     * Alias for backward compatibility
     */
    public function get_recurring_schedules() {
        return $this->get_recurring_times();
    }
    
    /**
     * Get calendar data for display
     * 
     * @param string $month Month in Y-m format
     * @return array Calendar data
     */
    public function get_calendar_data($month = null) {
        if (!$month) {
            $month = date('Y-m');
        }
        
        // Set timezone to São Paulo
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $start_date = new DateTime($month . '-01', $timezone);
        $end_date = clone $start_date;
        $end_date->modify('last day of this month');
        
        // Get recurring times
        $recurring_times = $this->get_recurring_times(array('active_only' => true));
        
        // Get scheduled posts for this month
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $scheduled_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(scheduled_time) as date, TIME(scheduled_time) as time, status 
             FROM {$table_name} 
             WHERE DATE(scheduled_time) BETWEEN %s AND %s 
             ORDER BY scheduled_time",
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d')
        ));
        
        // Build calendar data
        $calendar_data = array(
            'month' => $month,
            'month_name' => $start_date->format('F Y'),
            'days' => array(),
            'recurring_times' => $recurring_times
        );
        
        // Generate days for the month
        $current_date = clone $start_date;
        while ($current_date <= $end_date) {
            $day_data = array(
                'date' => $current_date->format('Y-m-d'),
                'day' => $current_date->format('j'),
                'day_of_week' => $current_date->format('w'),
                'is_today' => $current_date->format('Y-m-d') === date('Y-m-d'),
                'is_past' => $current_date < new DateTime('today', $timezone),
                'recurring_times' => array(),
                'scheduled_posts' => array()
            );
            
            // Add recurring times for this day of week
            foreach ($recurring_times as $time) {
                if (in_array($day_data['day_of_week'], $time['days'])) {
                    $day_data['recurring_times'][] = array(
                        'time' => $time['time'],
                        'time_id' => $time['id']
                    );
                }
            }
            
            // Add scheduled posts for this date
            foreach ($scheduled_posts as $post) {
                if ($post->date === $day_data['date']) {
                    $day_data['scheduled_posts'][] = array(
                        'time' => $post->time,
                        'status' => $post->status
                    );
                }
            }
            
            $calendar_data['days'][] = $day_data;
            $current_date->modify('+1 day');
        }
        
        return $calendar_data;
    }
    
    /**
     * Get available time slots for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @return array Available time slots
     */
    public function get_available_time_slots($date) {
        // Set timezone to São Paulo
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $target_date = new DateTime($date, $timezone);
        $day_of_week = $target_date->format('w');
        
        // Get recurring times for this day
        $recurring_times = $this->get_recurring_times(array('active_only' => true));
        
        $available_slots = array();
        
        foreach ($recurring_times as $time) {
            if (in_array($day_of_week, $time['days'])) {
                $available_slots[] = $time['time'];
            }
        }
        
        // Get occupied slots from scheduled posts
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $occupied_slots = $wpdb->get_col($wpdb->prepare(
            "SELECT TIME(scheduled_time) as time_slot 
             FROM {$table_name} 
             WHERE DATE(scheduled_time) = %s 
             AND status IN ('scheduled', 'scheduled_facebook')
             ORDER BY scheduled_time",
            $date
        ));
        
        // Remove occupied slots from available slots
        $available_slots = array_diff($available_slots, $occupied_slots);
        
        return array_values($available_slots);
    }
    
    /**
     * Schedule recurring cron job
     */
    private function schedule_recurring_cron() {
        if (!wp_next_scheduled('fps_process_recurring_posts')) {
            wp_schedule_event(time(), 'fps_every_minute', 'fps_process_recurring_posts');
            FPS_Logger::info('[FPS] Recurring posts cron scheduled');
        }
    }
    
    /**
     * Process recurring posts (called by cron)
     */
    public function process_recurring_posts() {
        // Set timezone to São Paulo
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $now = new DateTime('now', $timezone);
        $current_day = $now->format('w'); // 0 (Sunday) to 6 (Saturday)
        $current_time = $now->format('H:i');
        
        FPS_Logger::debug("[FPS] Processing recurring posts for day {$current_day} at {$current_time}");
        
        // Get active recurring times for current day and time
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_recurring_times';
        
        $matching_times = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE time = %s 
             AND active = 1 
             AND FIND_IN_SET(%s, days) > 0",
            $current_time,
            $current_day
        ));
        
        if (empty($matching_times)) {
            return;
        }
        
        FPS_Logger::info("[FPS] Found " . count($matching_times) . " matching recurring times to process");
        
        // This is where Schedule Post Multi posts would be automatically distributed
        foreach ($matching_times as $time) {
            FPS_Logger::info("[FPS] Recurring time slot available: {$time->time} on day {$current_day}");
        }
    }
    
    /**
     * Get day name
     * 
     * @param int $day_of_week Day of week (0-6)
     * @return string Day name
     */
    private function get_day_name($day_of_week) {
        $days = array(
            0 => __('Sunday', 'facebook-post-scheduler'),
            1 => __('Monday', 'facebook-post-scheduler'),
            2 => __('Tuesday', 'facebook-post-scheduler'),
            3 => __('Wednesday', 'facebook-post-scheduler'),
            4 => __('Thursday', 'facebook-post-scheduler'),
            5 => __('Friday', 'facebook-post-scheduler'),
            6 => __('Saturday', 'facebook-post-scheduler')
        );
        
        return isset($days[$day_of_week]) ? $days[$day_of_week] : '';
    }
}