<?php
/**
 * Scheduled Posts Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Scheduled Posts', 'facebook-post-scheduler'); ?></h1>
    
    <!-- Statistics -->
    <div class="fps-stats-grid">
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['scheduled'] + $stats['scheduled_facebook']); ?></div>
                <div class="fps-stat-label"><?php _e('Scheduled', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['published']); ?></div>
                <div class="fps-stat-label"><?php _e('Published', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['failed']); ?></div>
                <div class="fps-stat-label"><?php _e('Failed', 'facebook-post-scheduler'); ?></div>
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
    
    <!-- Filters -->
    <div class="fps-filters">
        <form method="get" class="fps-filter-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <input type="text" name="search" value="<?php echo esc_attr(isset($_GET['search']) ? $_GET['search'] : ''); ?>" 
                   placeholder="<?php _e('Search posts...', 'facebook-post-scheduler'); ?>" class="fps-search-input">
            
            <select name="status" class="fps-status-filter">
                <option value="all"><?php _e('All Status', 'facebook-post-scheduler'); ?></option>
                <option value="scheduled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'scheduled'); ?>><?php _e('Scheduled', 'facebook-post-scheduler'); ?></option>
                <option value="scheduled_facebook" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'scheduled_facebook'); ?>><?php _e('Scheduled (Facebook)', 'facebook-post-scheduler'); ?></option>
                <option value="published" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'published'); ?>><?php _e('Published', 'facebook-post-scheduler'); ?></option>
                <option value="failed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'failed'); ?>><?php _e('Failed', 'facebook-post-scheduler'); ?></option>
            </select>
            
            <?php if (!empty($pages)): ?>
            <select name="page_id" class="fps-page-filter">
                <option value=""><?php _e('All Pages', 'facebook-post-scheduler'); ?></option>
                <?php foreach ($pages as $page): ?>
                <option value="<?php echo esc_attr($page['id']); ?>" <?php selected(isset($_GET['page_id']) ? $_GET['page_id'] : '', $page['id']); ?>>
                    <?php echo esc_html($page['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <button type="submit" class="button"><?php _e('Filter', 'facebook-post-scheduler'); ?></button>
            
            <a href="<?php echo admin_url('admin.php?page=fps-scheduled-posts'); ?>" class="button">
                <?php _e('Clear', 'facebook-post-scheduler'); ?>
            </a>
        </form>
    </div>
    
    <!-- Posts Table -->
    <form method="post">
        <?php wp_nonce_field('bulk-posts'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1"><?php _e('Bulk Actions', 'facebook-post-scheduler'); ?></option>
                    <option value="delete"><?php _e('Delete', 'facebook-post-scheduler'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'facebook-post-scheduler'); ?>">
            </div>
            
            <div class="alignright">
                <a href="<?php echo admin_url('admin.php?page=fps-schedule-post'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Schedule New Post', 'facebook-post-scheduler'); ?>
                </a>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th class="manage-column"><?php _e('Content', 'facebook-post-scheduler'); ?></th>
                    <th class="manage-column"><?php _e('Page', 'facebook-post-scheduler'); ?></th>
                    <th class="manage-column"><?php _e('Scheduled Time', 'facebook-post-scheduler'); ?></th>
                    <th class="manage-column"><?php _e('Status', 'facebook-post-scheduler'); ?></th>
                    <th class="manage-column"><?php _e('Actions', 'facebook-post-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="6" class="fps-empty-state">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <p><?php _e('No posts found', 'facebook-post-scheduler'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=fps-schedule-post'); ?>" class="button button-primary">
                            <?php _e('Schedule Your First Post', 'facebook-post-scheduler'); ?>
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" name="post[]" value="<?php echo esc_attr($post->id); ?>">
                    </th>
                    <td>
                        <div class="fps-post-content">
                            <?php if (!empty($post->image_url)): ?>
                            <img src="<?php echo esc_url($post->image_url); ?>" class="fps-post-thumbnail" alt="">
                            <?php endif; ?>
                            
                            <div class="fps-post-text">
                                <div class="fps-content-preview">
                                    <?php echo esc_html(wp_trim_words($post->message, 20)); ?>
                                </div>
                                
                                <?php if (!empty($post->link)): ?>
                                <div class="fps-post-link">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <a href="<?php echo esc_url($post->link); ?>" target="_blank">
                                        <?php echo esc_html(parse_url($post->link, PHP_URL_HOST)); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <div class="fps-post-meta">
                                    <?php if (!empty($post->image_url)): ?>
                                    <span class="fps-has-media">
                                        <span class="dashicons dashicons-format-image"></span>
                                        <?php _e('Image', 'facebook-post-scheduler'); ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($post->video_url)): ?>
                                    <span class="fps-has-media">
                                        <span class="dashicons dashicons-format-video"></span>
                                        <?php _e('Video', 'facebook-post-scheduler'); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        // Find page name
                        $page_name = $post->page_id;
                        foreach ($pages as $page) {
                            if ($page['id'] === $post->page_id) {
                                $page_name = $page['name'];
                                break;
                            }
                        }
                        echo esc_html($page_name);
                        ?>
                    </td>
                    <td>
                        <div class="fps-scheduled-time">
                            <div class="fps-date">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($post->scheduled_time))); ?>
                            </div>
                            <div class="fps-time">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($post->scheduled_time))); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fps-status-info">
                            <span class="fps-status-badge fps-status-<?php echo esc_attr($post->status); ?>">
                                <?php
                                switch ($post->status) {
                                    case 'scheduled':
                                        _e('Scheduled (WP)', 'facebook-post-scheduler');
                                        break;
                                    case 'scheduled_facebook':
                                        _e('Scheduled (FB)', 'facebook-post-scheduler');
                                        break;
                                    case 'published':
                                        _e('Published', 'facebook-post-scheduler');
                                        break;
                                    case 'failed':
                                        _e('Failed', 'facebook-post-scheduler');
                                        break;
                                    case 'publishing':
                                        _e('Publishing...', 'facebook-post-scheduler');
                                        break;
                                    default:
                                        echo esc_html(ucfirst($post->status));
                                }
                                ?>
                            </span>
                            
                            <?php if (!empty($post->permalink)): ?>
                            <div class="fps-permalink">
                                <a href="<?php echo esc_url($post->permalink); ?>" target="_blank" class="fps-view-post">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php _e('View Post', 'facebook-post-scheduler'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($post->error_message)): ?>
                            <div class="fps-error-message" title="<?php echo esc_attr($post->error_message); ?>">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Error details', 'facebook-post-scheduler'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="fps-post-actions">
                            <?php if (in_array($post->status, array('scheduled', 'scheduled_facebook', 'failed'))): ?>
                            <button type="button" class="button fps-edit-post" data-post-id="<?php echo esc_attr($post->id); ?>">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Edit', 'facebook-post-scheduler'); ?>
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button fps-delete-post" data-post-id="<?php echo esc_attr($post->id); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Delete', 'facebook-post-scheduler'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>