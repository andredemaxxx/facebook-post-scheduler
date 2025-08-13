<?php
/**
 * Timezone Manager Class
 * 
 * Handles timezone operations for São Paulo (America/Sao_Paulo)
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Timezone_Manager {
    
    /**
     * Plugin timezone
     */
    const PLUGIN_TIMEZONE = 'America/Sao_Paulo';
    
    /**
     * Get plugin timezone
     * 
     * @return DateTimeZone
     */
    public static function get_timezone() {
        return new DateTimeZone(self::PLUGIN_TIMEZONE);
    }
    
    /**
     * Get current time in plugin timezone
     * 
     * @param string $format Date format
     * @return string Formatted date
     */
    public static function get_current_time($format = 'Y-m-d H:i:s') {
        $datetime = new DateTime('now', self::get_timezone());
        return $datetime->format($format);
    }
    
    /**
     * Convert datetime to plugin timezone
     * 
     * @param string $datetime Datetime string
     * @param string $from_timezone Source timezone
     * @return DateTime DateTime object in plugin timezone
     */
    public static function convert_to_plugin_timezone($datetime, $from_timezone = 'UTC') {
        $source_timezone = new DateTimeZone($from_timezone);
        $dt = new DateTime($datetime, $source_timezone);
        $dt->setTimezone(self::get_timezone());
        
        return $dt;
    }
    
    /**
     * Convert datetime from plugin timezone to another timezone
     * 
     * @param string $datetime Datetime string
     * @param string $to_timezone Target timezone
     * @return DateTime DateTime object in target timezone
     */
    public static function convert_from_plugin_timezone($datetime, $to_timezone = 'UTC') {
        $dt = new DateTime($datetime, self::get_timezone());
        $target_timezone = new DateTimeZone($to_timezone);
        $dt->setTimezone($target_timezone);
        
        return $dt;
    }
    
    /**
     * Format datetime for Facebook API
     * 
     * @param string $datetime Datetime string in plugin timezone
     * @return int Unix timestamp
     */
    public static function format_for_facebook($datetime) {
        $dt = new DateTime($datetime, self::get_timezone());
        return $dt->getTimestamp();
    }
    
    /**
     * Format datetime for display
     * 
     * @param string $datetime Datetime string
     * @param string $format Display format
     * @return string Formatted datetime
     */
    public static function format_for_display($datetime, $format = 'd/m/Y H:i') {
        $dt = new DateTime($datetime, self::get_timezone());
        return $dt->format($format);
    }
    
    /**
     * Check if datetime is in the future
     * 
     * @param string $datetime Datetime string
     * @return bool True if in future
     */
    public static function is_future($datetime) {
        $dt = new DateTime($datetime, self::get_timezone());
        $now = new DateTime('now', self::get_timezone());
        
        return $dt > $now;
    }
    
    /**
     * Check if datetime is today
     * 
     * @param string $datetime Datetime string
     * @return bool True if today
     */
    public static function is_today($datetime) {
        $dt = new DateTime($datetime, self::get_timezone());
        $today = new DateTime('today', self::get_timezone());
        
        return $dt->format('Y-m-d') === $today->format('Y-m-d');
    }
    
    /**
     * Get timezone offset string
     * 
     * @return string Timezone offset (e.g., "-03:00")
     */
    public static function get_offset_string() {
        $timezone = self::get_timezone();
        $datetime = new DateTime('now', $timezone);
        
        return $datetime->format('P');
    }
    
    /**
     * Get timezone info for display
     * 
     * @return array Timezone information
     */
    public static function get_timezone_info() {
        $timezone = self::get_timezone();
        $datetime = new DateTime('now', $timezone);
        
        return array(
            'name' => self::PLUGIN_TIMEZONE,
            'display_name' => 'São Paulo (GMT-3)',
            'offset' => $datetime->format('P'),
            'current_time' => $datetime->format('d/m/Y H:i:s')
        );
    }
}