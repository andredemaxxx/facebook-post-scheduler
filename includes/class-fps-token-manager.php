<?php
/**
 * Token Manager Class
 * 
 * Handles Facebook OAuth tokens with encryption and automatic refresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Token_Manager {
    
    /**
     * Encryption key for tokens
     * @var string
     */
    private $encryption_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();
    }
    
    /**
     * Get or create encryption key
     * 
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option('fps_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            add_option('fps_encryption_key', $key, '', false); // Don't autoload
        }
        
        return $key;
    }
    
    /**
     * Encrypt token data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private function encrypt($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback to base64
        }
        
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $this->encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token data
     * 
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    private function decrypt($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback from base64
        }
        
        $data = base64_decode($encrypted_data);
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $this->encryption_key, 0, $iv);
    }
    
    /**
     * Store user access token
     * 
     * @param int $user_id User ID
     * @param array $token_data Token data
     * @return bool Success
     */
    public function store_user_token($user_id, $token_data) {
        $encrypted_data = $this->encrypt(json_encode($token_data));
        
        $result = update_user_meta($user_id, 'fps_facebook_token', $encrypted_data);
        
        if ($result) {
            FPS_Logger::log("User token stored for user {$user_id}", 'info');
            return true;
        }
        
        FPS_Logger::log("Failed to store user token for user {$user_id}", 'error');
        return false;
    }
    
    /**
     * Get user access token
     * 
     * @param int $user_id User ID
     * @return array|false Token data or false
     */
    public function get_user_token($user_id) {
        $encrypted_data = get_user_meta($user_id, 'fps_facebook_token', true);
        
        if (!$encrypted_data) {
            return false;
        }
        
        $decrypted_data = $this->decrypt($encrypted_data);
        $token_data = json_decode($decrypted_data, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (isset($token_data['expires_at']) && time() > $token_data['expires_at']) {
            FPS_Logger::log("Token expired for user {$user_id}", 'warning');
            return false;
        }
        
        return $token_data;
    }
    
    /**
     * Store page access token
     * 
     * @param string $page_id Page ID
     * @param array $token_data Token data
     * @return bool Success
     */
    public function store_page_token($page_id, $token_data) {
        global $wpdb;
        
        // First, try to exchange for long-lived page token if it's not already
        if (isset($token_data['access_token']) && !isset($token_data['is_long_lived'])) {
            FPS_Logger::log("Attempting to exchange page token for long-lived token for page {$page_id}", 'info');
            $long_lived_token = $this->exchange_page_token_for_long_lived($token_data['access_token'], $page_id);
            
            if ($long_lived_token) {
                $token_data = array_merge($token_data, $long_lived_token);
                $token_data['is_long_lived'] = true;
                FPS_Logger::log("Successfully exchanged for long-lived page token for page {$page_id}", 'info');
            } else {
                FPS_Logger::log("Failed to exchange for long-lived page token for page {$page_id}, using original token", 'warning');
            }
        }
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        $encrypted_data = $this->encrypt(json_encode($token_data));
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'page_id' => $page_id,
                'token_data' => $encrypted_data,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            FPS_Logger::log("Page token stored for page {$page_id}", 'info');
            return true;
        }
        
        FPS_Logger::log("Failed to store page token for page {$page_id}", 'error');
        return false;
    }
    
    /**
     * Get page access token
     * 
     * @param string $page_id Page ID
     * @return array|false Token data or false
     */
    public function get_page_token($page_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        
        $encrypted_data = $wpdb->get_var($wpdb->prepare(
            "SELECT token_data FROM {$table_name} WHERE page_id = %s",
            $page_id
        ));
        
        if (!$encrypted_data) {
            FPS_Logger::log("No token found in database for page {$page_id}", 'warning');
            return false;
        }
        
        $decrypted_data = $this->decrypt($encrypted_data);
        $token_data = json_decode($decrypted_data, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            FPS_Logger::log("Invalid token data for page {$page_id}", 'error');
            return false;
        }
        
        // Validate token before returning
        $validation_result = $this->validate_page_token($token_data['access_token'], $page_id);
        
        if (!$validation_result['valid']) {
            FPS_Logger::log("Token validation failed for page {$page_id}: {$validation_result['message']}", 'warning');
            
            // Try to refresh the token
            $refreshed_token = $this->refresh_page_token($page_id, $token_data);
            if ($refreshed_token) {
                FPS_Logger::log("Successfully refreshed token for page {$page_id}", 'info');
                return $refreshed_token;
            }
            
            FPS_Logger::log("Failed to refresh token for page {$page_id}", 'error');
            return false;
        }
        
        FPS_Logger::log("Valid token retrieved for page {$page_id}", 'info');
        return $token_data;
    }
    
    /**
     * Exchange page token for long-lived token
     * 
     * @param string $page_token Page access token
     * @param string $page_id Page ID for logging
     * @return array|false Long-lived token data or false
     */
    private function exchange_page_token_for_long_lived($page_token, $page_id) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            FPS_Logger::log('App ID or App Secret not configured for page token exchange', 'error');
            return false;
        }
        
        FPS_Logger::log("Starting page token exchange for page {$page_id}", 'info');
        
        $url = 'https://graph.facebook.com/v19.0/oauth/access_token';
        $params = array(
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'fb_exchange_token' => $page_token
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log("Page token exchange HTTP error for page {$page_id}: " . $response->get_error_message(), 'error');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log("Page token exchange HTTP error code for page {$page_id}: {$http_code}", 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log("Page token exchange JSON decode error for page {$page_id}: " . json_last_error_msg(), 'error');
            return false;
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log("Page token exchange API error for page {$page_id}: " . $data['error']['message'], 'error');
            return false;
        }
        
        if (!isset($data['access_token'])) {
            FPS_Logger::log("Invalid page token exchange response for page {$page_id} - no access_token field", 'error');
            return false;
        }
        
        // Page tokens don't expire, but we'll set a far future date for consistency
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 0;
        $expires_at = $expires_in > 0 ? time() + $expires_in : 0; // 0 means never expires
        
        $long_lived_token_data = array(
            'access_token' => $data['access_token'],
            'token_type' => isset($data['token_type']) ? $data['token_type'] : 'bearer',
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'created_at' => time(),
            'is_long_lived' => true
        );
        
        FPS_Logger::log("Long-lived page token obtained successfully for page {$page_id}", 'info');
        return $long_lived_token_data;
    }
    
    /**
     * Validate page token using Facebook's debug_token endpoint
     * 
     * @param string $page_token Page access token
     * @param string $page_id Page ID for logging
     * @return array Validation result with 'valid' boolean and 'message'
     */
    private function validate_page_token($page_token, $page_id) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            return array('valid' => false, 'message' => 'App credentials not configured');
        }
        
        // Create app access token for debug_token endpoint
        $app_access_token = $app_id . '|' . $app_secret;
        
        $url = 'https://graph.facebook.com/v19.0/debug_token';
        $params = array(
            'input_token' => $page_token,
            'access_token' => $app_access_token
        );
        
        FPS_Logger::log("Validating page token for page {$page_id} using debug_token endpoint", 'debug');
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log("Token validation HTTP error for page {$page_id}: " . $response->get_error_message(), 'error');
            return array('valid' => false, 'message' => 'HTTP error during validation');
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log("Token validation HTTP error code for page {$page_id}: {$http_code}", 'error');
            return array('valid' => false, 'message' => "HTTP error code: {$http_code}");
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log("Token validation JSON decode error for page {$page_id}: " . json_last_error_msg(), 'error');
            return array('valid' => false, 'message' => 'JSON decode error');
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log("Token validation API error for page {$page_id}: " . $data['error']['message'], 'error');
            return array('valid' => false, 'message' => $data['error']['message']);
        }
        
        if (!isset($data['data'])) {
            FPS_Logger::log("Invalid token validation response for page {$page_id} - no data field", 'error');
            return array('valid' => false, 'message' => 'Invalid validation response');
        }
        
        $token_info = $data['data'];
        
        // Check if token is valid
        if (!isset($token_info['is_valid']) || !$token_info['is_valid']) {
            $error_message = isset($token_info['error']['message']) ? $token_info['error']['message'] : 'Token is invalid';
            FPS_Logger::log("Token is invalid for page {$page_id}: {$error_message}", 'warning');
            return array('valid' => false, 'message' => $error_message);
        }
        
        // Check if token has expired
        if (isset($token_info['expires_at']) && $token_info['expires_at'] > 0 && $token_info['expires_at'] < time()) {
            FPS_Logger::log("Token has expired for page {$page_id}", 'warning');
            return array('valid' => false, 'message' => 'Token has expired');
        }
        
        // Check required scopes
        $required_scopes = array('pages_manage_posts', 'pages_show_list');
        if (isset($token_info['scopes'])) {
            $missing_scopes = array_diff($required_scopes, $token_info['scopes']);
            if (!empty($missing_scopes)) {
                $missing_scopes_str = implode(', ', $missing_scopes);
                FPS_Logger::log("Token missing required scopes for page {$page_id}: {$missing_scopes_str}", 'warning');
                return array('valid' => false, 'message' => "Missing scopes: {$missing_scopes_str}");
            }
        }
        
        FPS_Logger::log("Token validation successful for page {$page_id}", 'debug');
        return array('valid' => true, 'message' => 'Token is valid');
    }
    
    /**
     * Refresh page token
     * 
     * @param string $page_id Page ID
     * @param array $current_token_data Current token data
     * @return array|false Refreshed token data or false
     */
    private function refresh_page_token($page_id, $current_token_data) {
        FPS_Logger::log("Attempting to refresh page token for page {$page_id}", 'info');
        
        // For page tokens, we need to get a fresh token from the user's pages
        // This requires the user to re-authenticate or we need to use a stored user token
        $user_id = get_current_user_id();
        $user_token_data = $this->get_user_token($user_id);
        
        if (!$user_token_data) {
            FPS_Logger::log("No valid user token found for refreshing page token for page {$page_id}", 'error');
            return false;
        }
        
        // Get fresh page data from Facebook
        $facebook_api = new FPS_Facebook_API($this);
        $pages = $facebook_api->get_user_pages($user_id);
        
        if (!$pages) {
            FPS_Logger::log("Failed to get fresh pages data for refreshing page token for page {$page_id}", 'error');
            return false;
        }
        
        // Find the specific page and get its fresh token
        foreach ($pages as $page) {
            if ($page['id'] === $page_id && isset($page['access_token'])) {
                $fresh_token_data = array(
                    'access_token' => $page['access_token'],
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'created_at' => time(),
                    'expires_at' => 0 // Page tokens don't expire
                );
                
                // Store the refreshed token
                if ($this->store_page_token($page_id, $fresh_token_data)) {
                    FPS_Logger::log("Successfully refreshed and stored page token for page {$page_id}", 'info');
                    return $fresh_token_data;
                }
                
                break;
            }
        }
        
        FPS_Logger::log("Failed to find page {$page_id} in fresh pages data for token refresh", 'error');
        return false;
    }
    
    /**
     * Exchange short-lived token for long-lived token
     * 
     * @param string $short_token Short-lived access token
     * @return array|false Long-lived token data or false
     */
    public function exchange_for_long_lived_token($short_token) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            FPS_Logger::log('App ID or App Secret not configured', 'error');
            return false;
        }
        
        $url = 'https://graph.facebook.com/v18.0/oauth/access_token';
        $params = array(
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'fb_exchange_token' => $short_token
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Token exchange failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('Token exchange error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        if (!isset($data['access_token'])) {
            FPS_Logger::log('Invalid token exchange response', 'error');
            return false;
        }
        
        // Calculate expiration time
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 5184000; // 60 days default
        $expires_at = time() + $expires_in;
        
        $token_data = array(
            'access_token' => $data['access_token'],
            'token_type' => isset($data['token_type']) ? $data['token_type'] : 'bearer',
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'created_at' => time()
        );
        
        FPS_Logger::log('Long-lived token obtained successfully', 'info');
        return $token_data;
    }
    
    /**
     * Refresh all stored tokens
     */
    public function refresh_all_tokens() {
        global $wpdb;
        
        // Get all page tokens
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        $tokens = $wpdb->get_results("SELECT page_id, token_data FROM {$table_name}");
        
        foreach ($tokens as $token_row) {
            $token_data = json_decode($this->decrypt($token_row->token_data), true);
            
            if (!$token_data || !isset($token_data['access_token'])) {
                continue;
            }
            
            // Check if token expires within 7 days
            $expires_at = isset($token_data['expires_at']) ? $token_data['expires_at'] : 0;
            $seven_days = 7 * DAY_IN_SECONDS;
            
            if ($expires_at > 0 && ($expires_at - time()) < $seven_days) {
                $new_token_data = $this->exchange_for_long_lived_token($token_data['access_token']);
                
                if ($new_token_data) {
                    $this->store_page_token($token_row->page_id, $new_token_data);
                    FPS_Logger::log("Token refreshed for page {$token_row->page_id}", 'info');
                } else {
                    FPS_Logger::log("Failed to refresh token for page {$token_row->page_id}", 'error');
                }
            }
        }
    }
    
    /**
     * Validate access token
     * 
     * @param string $access_token Access token to validate
     * @return array|false Token info or false
     */
    public function validate_token($access_token) {
        $url = 'https://graph.facebook.com/v18.0/me';
        $params = array(
            'access_token' => $access_token,
            'fields' => 'id,name,email'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Remove user token
     * 
     * @param int $user_id User ID
     * @return bool Success
     */
    public function remove_user_token($user_id) {
        $result = delete_user_meta($user_id, 'fps_facebook_token');
        
        if ($result) {
            FPS_Logger::log("User token removed for user {$user_id}", 'info');
        }
        
        return $result;
    }
    
    /**
     * Remove page token
     * 
     * @param string $page_id Page ID
     * @return bool Success
     */
    public function remove_page_token($page_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        
        $result = $wpdb->delete(
            $table_name,
            array('page_id' => $page_id),
            array('%s')
        );
        
        if ($result !== false) {
            FPS_Logger::log("Page token removed for page {$page_id}", 'info');
            return true;
        }
        
        return false;
    }
}