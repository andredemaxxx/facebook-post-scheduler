<?php
/**
 * Settings Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Facebook Post Scheduler Settings', 'facebook-post-scheduler'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('fps_save_settings', 'fps_settings_nonce'); ?>
        
        <div class="fps-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#facebook-app" class="nav-tab nav-tab-active"><?php _e('Facebook App', 'facebook-post-scheduler'); ?></a>
                <a href="#account" class="nav-tab"><?php _e('Account', 'facebook-post-scheduler'); ?></a>
                <a href="#pages" class="nav-tab"><?php _e('Pages', 'facebook-post-scheduler'); ?></a>
                <a href="#posting" class="nav-tab"><?php _e('Posting', 'facebook-post-scheduler'); ?></a>
                <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'facebook-post-scheduler'); ?></a>
            </nav>
            
            <!-- Facebook App Settings -->
            <div id="facebook-app" class="fps-tab-content active">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Facebook App Configuration', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <p><?php _e('To use this plugin, you need to create a Facebook App. Follow the instructions in the README file.', 'facebook-post-scheduler'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="facebook_app_id"><?php _e('App ID', 'facebook-post-scheduler'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="facebook_app_id" name="facebook_app_id" value="<?php echo esc_attr($app_id); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Facebook App ID from the Facebook Developer Console', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="facebook_app_secret"><?php _e('App Secret', 'facebook-post-scheduler'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="facebook_app_secret" name="facebook_app_secret" value="<?php echo esc_attr($app_secret); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Your Facebook App Secret (keep this secure!)', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($app_id && $app_secret): ?>
                        <div class="fps-app-status">
                            <span class="fps-status-ok">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Facebook App configured successfully', 'facebook-post-scheduler'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Account Settings -->
            <div id="account" class="fps-tab-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Facebook Account', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php if ($user_token): ?>
                        <div class="fps-account-connected">
                            <div class="fps-connection-status">
                                <span class="fps-status-ok">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Facebook account connected', 'facebook-post-scheduler'); ?>
                                </span>
                            </div>
                            
                            <div class="fps-account-actions">
                                <button type="button" id="fps-test-connection" class="button">
                                    <span class="dashicons dashicons-admin-network"></span>
                                    <?php _e('Test Connection', 'facebook-post-scheduler'); ?>
                                </button>
                                
                                <button type="button" id="fps-refresh-pages" class="button">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Refresh Pages', 'facebook-post-scheduler'); ?>
                                </button>
                                
                                <button type="button" id="fps-diagnose-pages" class="button">
                                    <span class="dashicons dashicons-search"></span>
                                    <?php _e('Diagnose Pages Issue', 'facebook-post-scheduler'); ?>
                                </button>
                                
                                <button type="button" id="fps-disconnect" class="button button-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Disconnect', 'facebook-post-scheduler'); ?>
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="fps-account-disconnected">
                            <?php if ($app_id && $app_secret): ?>
                            <p><?php _e('Connect your Facebook account to start scheduling posts.', 'facebook-post-scheduler'); ?></p>
                            
                            <?php
                            $redirect_uri = admin_url('admin.php?page=fps-settings');
                            $login_url = $this->facebook_api->get_login_url($redirect_uri);
                            ?>
                            
                            <?php if ($login_url): ?>
                            <a href="<?php echo esc_url($login_url); ?>" class="button button-primary button-large">
                                <span class="dashicons dashicons-facebook"></span>
                                <?php _e('Connect Facebook Account', 'facebook-post-scheduler'); ?>
                            </a>
                            <?php else: ?>
                            <div class="notice notice-error inline">
                                <p><?php _e('Unable to generate Facebook login URL. Please check your App ID and App Secret.', 'facebook-post-scheduler'); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Please configure your Facebook App ID and App Secret first.', 'facebook-post-scheduler'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pages Settings -->
            <div id="pages" class="fps-tab-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Facebook Pages', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php if (!empty($pages)): ?>
                        <p><?php _e('Select the Facebook pages you want to manage with this plugin:', 'facebook-post-scheduler'); ?></p>
                        
                        <div class="fps-pages-list">
                            <?php foreach ($pages as $page): ?>
                            <div class="fps-page-item">
                                <label>
                                    <input type="checkbox" name="selected_pages[]" value="<?php echo esc_attr($page['id']); ?>" 
                                           <?php checked(in_array($page['id'], $selected_pages)); ?> />
                                    
                                    <div class="fps-page-info">
                                        <?php if (isset($page['picture']['data']['url'])): ?>
                                        <img src="<?php echo esc_url($page['picture']['data']['url']); ?>" alt="" class="fps-page-avatar" />
                                        <?php endif; ?>
                                        
                                        <div class="fps-page-details">
                                            <div class="fps-page-name"><?php echo esc_html($page['name']); ?></div>
                                            <div class="fps-page-meta">
                                                <?php if (isset($page['category'])): ?>
                                                <span class="fps-page-category"><?php echo esc_html($page['category']); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($page['fan_count'])): ?>
                                                <span class="fps-page-fans">
                                                    <?php printf(__('%s followers', 'facebook-post-scheduler'), number_format($page['fan_count'])); ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="fps-empty-state">
                            <span class="dashicons dashicons-facebook"></span>
                            <p><?php _e('No Facebook pages found. Make sure you have admin access to at least one Facebook page.', 'facebook-post-scheduler'); ?></p>
                            
                            <?php if ($user_token): ?>
                            <button type="button" id="fps-refresh-pages" class="button">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Refresh Pages', 'facebook-post-scheduler'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Posting Settings -->
            <div id="posting" class="fps-tab-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Posting Options', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Default Post Status', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <select name="default_status">
                                        <option value="published" <?php selected(isset($post_settings['default_status']) ? $post_settings['default_status'] : 'published', 'published'); ?>>
                                            <?php _e('Published', 'facebook-post-scheduler'); ?>
                                        </option>
                                        <option value="draft" <?php selected(isset($post_settings['default_status']) ? $post_settings['default_status'] : 'published', 'draft'); ?>>
                                            <?php _e('Draft', 'facebook-post-scheduler'); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php _e('Default status for new posts', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Link Preview', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="auto_link_preview" value="1" 
                                               <?php checked(isset($post_settings['auto_link_preview']) ? $post_settings['auto_link_preview'] : true); ?> />
                                        <?php _e('Automatically generate link previews', 'facebook-post-scheduler'); ?>
                                    </label>
                                    <p class="description"><?php _e('When enabled, Facebook will automatically generate previews for links in your posts', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Image Quality', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <select name="image_quality">
                                        <option value="high" <?php selected(isset($post_settings['image_quality']) ? $post_settings['image_quality'] : 'high', 'high'); ?>>
                                            <?php _e('High Quality', 'facebook-post-scheduler'); ?>
                                        </option>
                                        <option value="medium" <?php selected(isset($post_settings['image_quality']) ? $post_settings['image_quality'] : 'high', 'medium'); ?>>
                                            <?php _e('Medium Quality', 'facebook-post-scheduler'); ?>
                                        </option>
                                        <option value="low" <?php selected(isset($post_settings['image_quality']) ? $post_settings['image_quality'] : 'high', 'low'); ?>>
                                            <?php _e('Low Quality (Faster Upload)', 'facebook-post-scheduler'); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php _e('Image quality for uploaded photos', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Scheduling Method', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="use_facebook_scheduling" value="1" 
                                               <?php checked(isset($post_settings['use_facebook_scheduling']) ? $post_settings['use_facebook_scheduling'] : true); ?> />
                                        <?php _e('Use Facebook\'s native scheduling (recommended)', 'facebook-post-scheduler'); ?>
                                    </label>
                                    <p class="description"><?php _e('When enabled, posts will be scheduled directly with Facebook. Otherwise, WordPress cron will be used as backup.', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Settings -->
            <div id="advanced" class="fps-tab-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Advanced Options', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Logging', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_logging" value="1" 
                                               <?php checked(isset($post_settings['enable_logging']) ? $post_settings['enable_logging'] : true); ?> />
                                        <?php _e('Enable activity logging', 'facebook-post-scheduler'); ?>
                                    </label>
                                    <p class="description"><?php _e('Log plugin activities for debugging and monitoring', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Data Cleanup', 'facebook-post-scheduler'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="remove_data_on_uninstall" value="1" 
                                               <?php checked(get_option('fps_remove_data_on_uninstall', false)); ?> />
                                        <?php _e('Remove all data when plugin is uninstalled', 'facebook-post-scheduler'); ?>
                                    </label>
                                    <p class="description"><?php _e('Warning: This will permanently delete all scheduled posts, logs, and settings when you uninstall the plugin', 'facebook-post-scheduler'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('System Information', 'facebook-post-scheduler'); ?></h3>
                        <div class="fps-system-info">
                            <table class="widefat">
                                <tbody>
                                    <tr>
                                        <td><strong><?php _e('Plugin Version', 'facebook-post-scheduler'); ?></strong></td>
                                        <td><?php echo FPS_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php _e('WordPress Version', 'facebook-post-scheduler'); ?></strong></td>
                                        <td><?php echo get_bloginfo('version'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php _e('PHP Version', 'facebook-post-scheduler'); ?></strong></td>
                                        <td><?php echo PHP_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php _e('Database Version', 'facebook-post-scheduler'); ?></strong></td>
                                        <td><?php echo get_option('fps_db_version', '0.0.0'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php _e('WordPress Cron', 'facebook-post-scheduler'); ?></strong></td>
                                        <td>
                                            <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                                            <span class="fps-status-warning"><?php _e('Disabled', 'facebook-post-scheduler'); ?></span>
                                            <?php else: ?>
                                            <span class="fps-status-ok"><?php _e('Enabled', 'facebook-post-scheduler'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'facebook-post-scheduler')); ?>
    </form>
</div>