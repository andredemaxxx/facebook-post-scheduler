<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Facebook Post Scheduler Dashboard', 'facebook-post-scheduler'); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="fps-stats-grid">
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['scheduled'] + $stats['scheduled_facebook']); ?></div>
                <div class="fps-stat-label"><?php _e('Scheduled Posts', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['published']); ?></div>
                <div class="fps-stat-label"><?php _e('Published Posts', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['failed']); ?></div>
                <div class="fps-stat-label"><?php _e('Failed Posts', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['today']); ?></div>
                <div class="fps-stat-label"><?php _e('Today', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="fps-dashboard-content">
        <!-- Quick Actions -->
        <div class="fps-dashboard-section">
            <div class="fps-section-header">
                <h2><?php _e('Quick Actions', 'facebook-post-scheduler'); ?></h2>
            </div>
            
            <div class="fps-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=fps-schedule-post'); ?>" class="fps-quick-action">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <span><?php _e('Schedule New Post', 'facebook-post-scheduler'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=fps-scheduled-posts'); ?>" class="fps-quick-action">
                    <span class="dashicons dashicons-list-view"></span>
                    <span><?php _e('View Scheduled Posts', 'facebook-post-scheduler'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=fps-analytics'); ?>" class="fps-quick-action">
                    <span class="dashicons dashicons-chart-area"></span>
                    <span><?php _e('View Analytics', 'facebook-post-scheduler'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=fps-settings'); ?>" class="fps-quick-action">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span><?php _e('Settings', 'facebook-post-scheduler'); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Recent Posts -->
        <div class="fps-dashboard-section">
            <div class="fps-section-header">
                <h2><?php _e('Recent Posts', 'facebook-post-scheduler'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=fps-scheduled-posts'); ?>" class="button">
                    <?php _e('View All', 'facebook-post-scheduler'); ?>
                </a>
            </div>
            
            <?php if (!empty($recent_posts)): ?>
            <div class="fps-recent-posts">
                <?php foreach ($recent_posts as $post): ?>
                <div class="fps-recent-post">
                    <div class="fps-post-status">
                        <span class="fps-status-badge fps-status-<?php echo esc_attr($post->status); ?>">
                            <?php echo esc_html(ucfirst($post->status)); ?>
                        </span>
                    </div>
                    
                    <div class="fps-post-content">
                        <div class="fps-post-message">
                            <?php echo esc_html(wp_trim_words($post->message, 15)); ?>
                        </div>
                        <div class="fps-post-meta">
                            <span class="fps-post-time">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->scheduled_time))); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="fps-post-actions">
                        <?php if (in_array($post->status, array('scheduled', 'scheduled_facebook', 'failed'))): ?>
                        <a href="#" class="fps-edit-post" data-post-id="<?php echo esc_attr($post->id); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($post->status === 'published' && !empty($post->permalink)): ?>
                        <a href="<?php echo esc_url($post->permalink); ?>" target="_blank">
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="fps-empty-state">
                <span class="dashicons dashicons-calendar-alt"></span>
                <p><?php _e('No posts scheduled yet.', 'facebook-post-scheduler'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=fps-schedule-post'); ?>" class="button button-primary">
                    <?php _e('Schedule Your First Post', 'facebook-post-scheduler'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- System Status -->
        <div class="fps-dashboard-section">
            <div class="fps-section-header">
                <h2><?php _e('System Status', 'facebook-post-scheduler'); ?></h2>
            </div>
            
            <div class="fps-system-status">
                <?php
                $app_id = get_option('fps_facebook_app_id');
                $app_secret = get_option('fps_facebook_app_secret');
                $user_id = get_current_user_id();
                $token_manager = new FPS_Token_Manager();
                $user_token = $token_manager->get_user_token($user_id);
                $pages = get_user_meta($user_id, 'fps_facebook_pages', true);
                ?>
                
                <div class="fps-status-item">
                    <span class="fps-status-icon <?php echo ($app_id && $app_secret) ? 'fps-status-ok' : 'fps-status-error'; ?>">
                        <span class="dashicons dashicons-<?php echo ($app_id && $app_secret) ? 'yes-alt' : 'warning'; ?>"></span>
                    </span>
                    <span class="fps-status-text">
                        <?php _e('Facebook App Configuration', 'facebook-post-scheduler'); ?>
                    </span>
                </div>
                
                <div class="fps-status-item">
                    <span class="fps-status-icon <?php echo $user_token ? 'fps-status-ok' : 'fps-status-error'; ?>">
                        <span class="dashicons dashicons-<?php echo $user_token ? 'yes-alt' : 'warning'; ?>"></span>
                    </span>
                    <span class="fps-status-text">
                        <?php _e('Facebook Account Connected', 'facebook-post-scheduler'); ?>
                    </span>
                </div>
                
                <div class="fps-status-item">
                    <span class="fps-status-icon <?php echo (!empty($pages)) ? 'fps-status-ok' : 'fps-status-warning'; ?>">
                        <span class="dashicons dashicons-<?php echo (!empty($pages)) ? 'yes-alt' : 'warning'; ?>"></span>
                    </span>
                    <span class="fps-status-text">
                        <?php 
                        if (!empty($pages)) {
                            printf(__('%d Facebook Pages Available', 'facebook-post-scheduler'), count($pages));
                        } else {
                            _e('No Facebook Pages Found', 'facebook-post-scheduler');
                        }
                        ?>
                    </span>
                </div>
                
                <div class="fps-status-item">
                    <span class="fps-status-icon <?php echo wp_next_scheduled('fps_publish_scheduled_post') ? 'fps-status-ok' : 'fps-status-warning'; ?>">
                        <span class="dashicons dashicons-<?php echo wp_next_scheduled('fps_publish_scheduled_post') ? 'yes-alt' : 'warning'; ?>"></span>
                    </span>
                    <span class="fps-status-text">
                        <?php _e('WordPress Cron System', 'facebook-post-scheduler'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>