<?php
/**
 * Facebook API Handler Class
 * 
 * Handles all Facebook Graph API interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Facebook_API {
    
    /**
     * Token manager instance
     * @var FPS_Token_Manager
     */
    private $token_manager;
    
    /**
     * Facebook Graph API base URL
     * @var string
     */
    private $api_base_url = 'https://graph.facebook.com/v23.0/';
    
    /**
     * Constructor
     * 
     * @param FPS_Token_Manager $token_manager Token manager instance
     */
    public function __construct($token_manager) {
        $this->token_manager = $token_manager;
    }
    
    /**
     * Get Facebook login URL
     * 
     * @param string $redirect_uri Redirect URI
     * @param array $permissions Required permissions
     * @return string Login URL
     */
    public function get_login_url($redirect_uri, $permissions = array()) {
        $app_id = get_option('fps_facebook_app_id');
        
        if (!$app_id) {
            FPS_Logger::log('App ID not configured for login URL generation', 'error');
            return false;
        }
        
        $default_permissions = array(
            'pages_manage_posts',
            'pages_read_engagement',
            'pages_show_list',
            'pages_manage_metadata',
            'business_management'
        );
        
        $permissions = array_merge($default_permissions, $permissions);
        
        $params = array(
            'client_id' => $app_id,
            'redirect_uri' => $redirect_uri,
            'scope' => implode(',', $permissions),
            'response_type' => 'code',
            'state' => wp_create_nonce('fps_facebook_oauth'),
            'auth_type' => 'rerequest'
        );
        
        $login_url = 'https://www.facebook.com/v23.0/dialog/oauth?' . http_build_query($params);
        FPS_Logger::log('Generated Facebook login URL with permissions: ' . implode(', ', $permissions), 'info');
        
        return $login_url;
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @param string $redirect_uri Redirect URI
     * @return array|false Token data or false
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            FPS_Logger::log('App ID or App Secret not configured for token exchange', 'error');
            return false;
        }
        
        FPS_Logger::log('Starting token exchange process', 'info');
        
        $url = $this->api_base_url . 'oauth/access_token';
        $params = array(
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code
        );
        
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Token exchange HTTP error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log('Token exchange HTTP error code: ' . $http_code, 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log('Token exchange JSON decode error: ' . json_last_error_msg(), 'error');
            return false;
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log('Token exchange API error: ' . $data['error']['message'] . ' (Code: ' . $data['error']['code'] . ')', 'error');
            return false;
        }
        
        if (!isset($data['access_token'])) {
            FPS_Logger::log('Invalid token exchange response - no access_token field', 'error');
            return false;
        }
        
        FPS_Logger::log('Token exchange successful, proceeding to long-lived token exchange', 'info');
        
        // Exchange for long-lived token
        return $this->token_manager->exchange_for_long_lived_token($data['access_token']);
    }
    
    /**
     * Validate access token
     * 
     * @param string $access_token Access token to validate
     * @return array|false Token info or false if invalid
     */
    public function validate_token($access_token) {
        if (empty($access_token)) {
            FPS_Logger::log('Empty access token provided for validation', 'error');
            return false;
        }
        
        FPS_Logger::log('Validating access token', 'info');
        
        $url = $this->api_base_url . 'me';
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
            FPS_Logger::log('Token validation HTTP error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log('Token validation HTTP error code: ' . $http_code, 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log('Token validation JSON decode error: ' . json_last_error_msg(), 'error');
            return false;
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log('Token validation API error: ' . $data['error']['message'] . ' (Code: ' . $data['error']['code'] . ')', 'error');
            return false;
        }
        
        if (!isset($data['id']) || !isset($data['name'])) {
            FPS_Logger::log('Invalid token validation response - missing required fields', 'error');
            return false;
        }
        
        FPS_Logger::log('Token validation successful for user: ' . $data['name'] . ' (ID: ' . $data['id'] . ')', 'info');
        return $data;
    }
    
    /**
     * Get user's Facebook pages with complete pagination support
     * 
     * @param int $user_id User ID
     * @return array|false Pages data or false
     */
    public function get_user_pages($user_id) {
        FPS_Logger::log('Starting get_user_pages for user ID: ' . $user_id, 'info');
        
        $token_data = $this->token_manager->get_user_token($user_id);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            FPS_Logger::log('No valid user token found for user ' . $user_id, 'error');
            return false;
        }
        
        // Validate token before proceeding
        $token_validation = $this->validate_token($token_data['access_token']);
        if (!$token_validation) {
            FPS_Logger::log('Token validation failed for user ' . $user_id, 'error');
            return false;
        }
        
        FPS_Logger::log('Token validation successful for user: ' . $token_validation['name'], 'info');
        
        // Check token permissions
        $permissions = $this->get_token_permissions($token_data['access_token']);
        if (!$this->validate_required_permissions($permissions)) {
            FPS_Logger::log('Required permissions not granted for user ' . $user_id, 'error');
            return false;
        }
        
        // Fetch all pages with pagination
        $all_pages = array();
        $next_url = null;
        $page_count = 0;
        $batch_count = 0;
        $max_pages = 200; // Safety limit
        $max_batches = 10; // Safety limit for API calls
        
        do {
            $batch_count++;
            FPS_Logger::log('Fetching pages batch #' . $batch_count, 'info');
            
            $pages_data = $this->fetch_pages_batch($token_data['access_token'], $next_url);
            
            if ($pages_data === false) {
                FPS_Logger::log('Failed to fetch pages batch #' . $batch_count . ' for user ' . $user_id, 'error');
                break;
            }
            
            if (isset($pages_data['data']) && is_array($pages_data['data'])) {
                $batch_pages = $pages_data['data'];
                $all_pages = array_merge($all_pages, $batch_pages);
                $page_count += count($batch_pages);
                
                FPS_Logger::log('Batch #' . $batch_count . ' returned ' . count($batch_pages) . ' pages', 'info');
            } else {
                FPS_Logger::log('Batch #' . $batch_count . ' returned no data array', 'warning');
            }
            
            // Check for next page
            $next_url = isset($pages_data['paging']['next']) ? $pages_data['paging']['next'] : null;
            
            if ($next_url) {
                FPS_Logger::log('Next page URL found, continuing pagination', 'info');
            }
            
        } while ($next_url && $page_count < $max_pages && $batch_count < $max_batches);
        
        FPS_Logger::log('Total raw pages found: ' . count($all_pages), 'info');
        
        if (empty($all_pages)) {
            FPS_Logger::log('No pages found for user ' . $user_id, 'warning');
            return array();
        }
        
        // Filter and format pages
        $valid_pages = array();
        foreach ($all_pages as $page) {
            if ($this->validate_page_permissions($page)) {
                $formatted_page = $this->format_page_data($page);
                if ($formatted_page) {
                    $valid_pages[] = $formatted_page;
                    
                    // Store page token for future use
                    if (isset($page['access_token'])) {
                        $page_token_data = array(
                            'access_token' => $page['access_token'],
                            'page_id' => $page['id'],
                            'page_name' => $page['name'],
                            'created_at' => time(),
                            'expires_at' => 0 // Page tokens don't expire
                        );
                        
                        $this->token_manager->store_page_token($page['id'], $page_token_data);
                        FPS_Logger::log('Stored token for page: ' . $page['name'] . ' (ID: ' . $page['id'] . ')', 'info');
                    }
                }
            }
        }
        
        FPS_Logger::log('Valid pages after filtering: ' . count($valid_pages), 'info');
        
        return $valid_pages;
    }
    
    /**
     * Fetch a batch of pages from Facebook API
     * 
     * @param string $access_token User access token
     * @param string|null $next_url Next page URL or null for first request
     * @return array|false Pages data or false
     */
    private function fetch_pages_batch($access_token, $next_url = null) {
        if ($next_url) {
            $url = $next_url;
            FPS_Logger::log('Using pagination URL for batch request', 'debug');
        } else {
            $url = $this->api_base_url . 'me/accounts';
            $params = array(
                'access_token' => $access_token,
                'fields' => 'id,name,access_token,category,picture.width(100).height(100),fan_count,tasks',
                'limit' => 25 // Facebook's default, can be up to 100
            );
            $url = add_query_arg($params, $url);
            FPS_Logger::log('Using initial URL for batch request', 'debug');
        }
        
        FPS_Logger::log('Fetching pages from URL: ' . substr($url, 0, 100) . '...', 'debug');
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('HTTP error fetching pages batch: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log('HTTP error code fetching pages batch: ' . $http_code, 'error');
            
            // Log response body for debugging
            $body = wp_remote_retrieve_body($response);
            if ($body) {
                $error_data = json_decode($body, true);
                if (isset($error_data['error'])) {
                    FPS_Logger::log('API error details: ' . $error_data['error']['message'], 'error');
                }
            }
            
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log('JSON decode error fetching pages batch: ' . json_last_error_msg(), 'error');
            return false;
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log('Facebook API error fetching pages: ' . $data['error']['message'] . ' (Code: ' . $data['error']['code'] . ')', 'error');
            
            // Log additional error details if available
            if (isset($data['error']['error_subcode'])) {
                FPS_Logger::log('Error subcode: ' . $data['error']['error_subcode'], 'error');
            }
            if (isset($data['error']['fbtrace_id'])) {
                FPS_Logger::log('Facebook trace ID: ' . $data['error']['fbtrace_id'], 'error');
            }
            
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get token permissions
     * 
     * @param string $access_token Access token
     * @return array|false Permissions or false
     */
    private function get_token_permissions($access_token) {
        FPS_Logger::log('Checking token permissions', 'info');
        
        $url = $this->api_base_url . 'me/permissions';
        $params = array(
            'access_token' => $access_token
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('HTTP error checking permissions: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            FPS_Logger::log('HTTP error code checking permissions: ' . $http_code, 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            FPS_Logger::log('JSON decode error checking permissions: ' . json_last_error_msg(), 'error');
            return false;
        }
        
        if (isset($data['error'])) {
            FPS_Logger::log('API error checking permissions: ' . $data['error']['message'], 'error');
            return false;
        }
        
        $permissions = isset($data['data']) ? $data['data'] : array();
        FPS_Logger::log('Found ' . count($permissions) . ' permissions', 'info');
        
        return $permissions;
    }
    
    /**
     * Validate if required permissions are granted
     * 
     * @param array $permissions Permissions array from API
     * @return bool True if all required permissions are granted
     */
    private function validate_required_permissions($permissions) {
        if (!is_array($permissions)) {
            FPS_Logger::log('Invalid permissions data provided', 'error');
            return false;
        }
        
        $required_permissions = array(
            'pages_show_list',
            'pages_manage_posts',
            'pages_read_engagement'
        );
        
        $granted_permissions = array();
        foreach ($permissions as $permission) {
            if (isset($permission['permission']) && isset($permission['status']) && $permission['status'] === 'granted') {
                $granted_permissions[] = $permission['permission'];
            }
        }
        
        FPS_Logger::log('Granted permissions: ' . implode(', ', $granted_permissions), 'info');
        
        $missing_permissions = array();
        foreach ($required_permissions as $required) {
            if (!in_array($required, $granted_permissions)) {
                $missing_permissions[] = $required;
            }
        }
        
        if (!empty($missing_permissions)) {
            FPS_Logger::log('Missing required permissions: ' . implode(', ', $missing_permissions), 'error');
            return false;
        }
        
        FPS_Logger::log('All required permissions are granted', 'info');
        return true;
    }
    
    /**
     * Validate if user has adequate permissions on a page
     * 
     * @param array $page Page data from Facebook
     * @return bool True if user has adequate permissions
     */
    private function validate_page_permissions($page) {
        if (!isset($page['id']) || !isset($page['name'])) {
            FPS_Logger::log('Invalid page data - missing id or name', 'warning');
            return false;
        }
        
        // Check if user has access token for the page
        if (!isset($page['access_token']) || empty($page['access_token'])) {
            FPS_Logger::log('No access token for page: ' . $page['name'] . ' (ID: ' . $page['id'] . ')', 'warning');
            return false;
        }
        
        // Check if user has required tasks
        if (isset($page['tasks']) && is_array($page['tasks'])) {
            $required_tasks = array('MANAGE', 'CREATE_CONTENT', 'MODERATE', 'ADVERTISE');
            $user_tasks = $page['tasks'];
            
            $has_required_task = false;
            foreach ($required_tasks as $required_task) {
                if (in_array($required_task, $user_tasks)) {
                    $has_required_task = true;
                    break;
                }
            }
            
            if (!$has_required_task) {
                FPS_Logger::log('Insufficient tasks for page: ' . $page['name'] . '. User tasks: ' . implode(', ', $user_tasks), 'warning');
                return false;
            }
        }
        
        // Check specific permissions if available
        if (isset($page['perms']) && is_array($page['perms'])) {
            $required_perms = array('CREATE_CONTENT', 'MANAGE');
            $user_perms = $page['perms'];
            
            $has_required_perm = false;
            foreach ($required_perms as $required_perm) {
                if (in_array($required_perm, $user_perms)) {
                    $has_required_perm = true;
                    break;
                }
            }
            
            if (!$has_required_perm) {
                FPS_Logger::log('Insufficient perms for page: ' . $page['name'] . '. User perms: ' . implode(', ', $user_perms), 'warning');
                return false;
            }
        }
        
        FPS_Logger::log('Page validation successful for: ' . $page['name'] . ' (ID: ' . $page['id'] . ')', 'debug');
        return true;
    }
    
    /**
     * Format page data for consistent return structure
     * 
     * @param array $page Raw page data from Facebook API
     * @return array|false Formatted page data or false
     */
    private function format_page_data($page) {
        if (!isset($page['id']) || !isset($page['name'])) {
            return false;
        }
        
        $formatted = array(
            'id' => $page['id'],
            'name' => $page['name'],
            'access_token' => isset($page['access_token']) ? $page['access_token'] : '',
            'category' => isset($page['category']) ? $page['category'] : '',
            'fan_count' => isset($page['fan_count']) ? intval($page['fan_count']) : 0,
            'tasks' => isset($page['tasks']) ? $page['tasks'] : array(),
            'perms' => isset($page['perms']) ? $page['perms'] : array()
        );
        
        // Add picture URL if available
        if (isset($page['picture']['data']['url'])) {
            $formatted['picture'] = array(
                'data' => array(
                    'url' => $page['picture']['data']['url']
                )
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get detailed information about why no pages were found (diagnostic function)
     * 
     * @param int $user_id User ID
     * @return array Diagnostic information
     */
    public function diagnose_pages_issue($user_id) {
        FPS_Logger::log('Starting pages diagnostic for user ' . $user_id, 'info');
        
        $token_data = $this->token_manager->get_user_token($user_id);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return array(
                'success' => false,
                'message' => 'No user token found',
                'details' => array()
            );
        }
        
        // 1. Check user info
        $user_info = $this->validate_token($token_data['access_token']);
        
        // 2. Check permissions
        $permissions = $this->get_token_permissions($token_data['access_token']);
        
        // 3. Try to fetch raw pages data
        $raw_pages = $this->fetch_pages_batch($token_data['access_token']);
        
        $diagnostic_data = array(
            'success' => true,
            'user_info' => $user_info,
            'permissions' => $permissions,
            'raw_pages_count' => isset($raw_pages['data']) ? count($raw_pages['data']) : 0,
            'raw_pages' => $raw_pages
        );
        
        FPS_Logger::log('Diagnostic completed for user ' . $user_id, 'info');
        
        return $diagnostic_data;
    }
    
    /**
     * Create a Facebook post
     * 
     * @param string $page_id Page ID
     * @param array $post_data Post data
     * @return array|false Post response or false
     */
    public function create_post($page_id, $post_data) {
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            FPS_Logger::log("No valid token for page {$page_id}", 'error');
            return false;
        }
        
        // Prepare post parameters
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        // Add message
        if (!empty($post_data['message'])) {
            $params['message'] = $post_data['message'];
        }
        
        // Add link
        if (!empty($post_data['link'])) {
            $params['link'] = $post_data['link'];
        }
        
        // Add scheduled publish time
        if (!empty($post_data['scheduled_publish_time'])) {
            $params['published'] = 'false';
            $params['scheduled_publish_time'] = $post_data['scheduled_publish_time'];
        }
        
        // Determine endpoint based on content type
        $endpoint = 'feed';
        
        // Handle image upload
        if (!empty($post_data['image_path']) && file_exists($post_data['image_path'])) {
            $endpoint = 'photos';
            $params['source'] = new CURLFile($post_data['image_path']);
            
            if (!empty($post_data['message'])) {
                $params['caption'] = $post_data['message'];
                unset($params['message']);
            }
        } elseif (!empty($post_data['image_url'])) {
            $endpoint = 'photos';
            $params['url'] = $post_data['image_url'];
            
            if (!empty($post_data['message'])) {
                $params['caption'] = $post_data['message'];
                unset($params['message']);
            }
        }
        
        // Handle video upload
        if (!empty($post_data['video_path']) && file_exists($post_data['video_path'])) {
            $endpoint = 'videos';
            $params['source'] = new CURLFile($post_data['video_path']);
            
            if (!empty($post_data['message'])) {
                $params['description'] = $post_data['message'];
                unset($params['message']);
            }
        }
        
        $url = $this->api_base_url . $page_id . '/' . $endpoint;
        
        // Use cURL for file uploads, wp_remote_post for others
        if (isset($params['source'])) {
            return $this->curl_post($url, $params);
        } else {
            return $this->wp_remote_post($url, $params);
        }
    }
    
    /**
     * Update a scheduled post
     * 
     * @param string $post_id Post ID
     * @param array $post_data Updated post data
     * @return array|false Response or false
     */
    public function update_post($post_id, $post_data) {
        // Get the page ID from the post
        $post_info = $this->get_post($post_id);
        
        if (!$post_info || !isset($post_info['from']['id'])) {
            return false;
        }
        
        $page_id = $post_info['from']['id'];
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        // Add updatable fields
        if (isset($post_data['message'])) {
            $params['message'] = $post_data['message'];
        }
        
        if (isset($post_data['scheduled_publish_time'])) {
            $params['scheduled_publish_time'] = $post_data['scheduled_publish_time'];
        }
        
        $url = $this->api_base_url . $post_id;
        
        return $this->wp_remote_post($url, $params);
    }
    
    /**
     * Delete a post
     * 
     * @param string $post_id Post ID
     * @return bool Success
     */
    public function delete_post($post_id) {
        // Get the page ID from the post
        $post_info = $this->get_post($post_id);
        
        if (!$post_info || !isset($post_info['from']['id'])) {
            return false;
        }
        
        $page_id = $post_info['from']['id'];
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $url = $this->api_base_url . $post_id;
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Failed to delete post: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('Delete post error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return isset($data['success']) && $data['success'];
    }
    
    /**
     * Get post information
     * 
     * @param string $post_id Post ID
     * @return array|false Post data or false
     */
    public function get_post($post_id) {
        // Try to get page token from stored posts first
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT page_id FROM {$table_name} WHERE facebook_post_id = %s",
            $post_id
        ));
        
        if (!$page_id) {
            // Try to extract page ID from post ID format
            $parts = explode('_', $post_id);
            if (count($parts) >= 2) {
                $page_id = $parts[0];
            }
        }
        
        if (!$page_id) {
            return false;
        }
        
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $url = $this->api_base_url . $post_id;
        $params = array(
            'access_token' => $token_data['access_token'],
            'fields' => 'id,message,created_time,scheduled_publish_time,is_published,from,permalink_url'
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
     * Get page insights
     * 
     * @param string $page_id Page ID
     * @param array $metrics Metrics to retrieve
     * @param string $period Time period
     * @return array|false Insights data or false
     */
    public function get_page_insights($page_id, $metrics = array(), $period = 'day') {
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $default_metrics = array(
            'page_impressions',
            'page_reach',
            'page_engaged_users',
            'page_post_engagements'
        );
        
        $metrics = empty($metrics) ? $default_metrics : $metrics;
        
        $url = $this->api_base_url . $page_id . '/insights';
        $params = array(
            'access_token' => $token_data['access_token'],
            'metric' => implode(',', $metrics),
            'period' => $period
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
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
        
        return isset($data['data']) ? $data['data'] : array();
    }
    
    /**
     * Make POST request using wp_remote_post
     * 
     * @param string $url URL
     * @param array $params Parameters
     * @return array|false Response or false
     */
    private function wp_remote_post($url, $params) {
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('API request failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('API error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Make POST request using cURL (for file uploads)
     * 
     * @param string $url URL
     * @param array $params Parameters
     * @return array|false Response or false
     */
    private function curl_post($url, $params) {
        if (!function_exists('curl_init')) {
            FPS_Logger::log('cURL not available for file upload', 'error');
            return false;
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'WordPress Facebook Post Scheduler v' . FPS_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            FPS_Logger::log('cURL error: ' . $error, 'error');
            return false;
        }
        
        if ($http_code !== 200) {
            FPS_Logger::log('HTTP error: ' . $http_code, 'error');
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('API error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Test API connection
     * 
     * @param string $access_token Access token to test
     * @return array Test result
     */
    public function test_connection($access_token) {
        $validation_result = $this->validate_token($access_token);
        
        if ($validation_result) {
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'data' => $validation_result
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Connection failed - invalid or expired token'
            );
        }
    }
}