<?php
/**
 * Logs Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Activity Logs', 'facebook-post-scheduler'); ?></h1>
    
    <!-- Log Statistics -->
    <div class="fps-stats-grid">
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['total']); ?></div>
                <div class="fps-stat-label"><?php _e('Total Logs', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['error']); ?></div>
                <div class="fps-stat-label"><?php _e('Errors', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-info"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['info']); ?></div>
                <div class="fps-stat-label"><?php _e('Info', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
        
        <div class="fps-stat-card">
            <div class="fps-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="fps-stat-content">
                <div class="fps-stat-number"><?php echo esc_html($stats['recent']); ?></div>
                <div class="fps-stat-label"><?php _e('Last 24h', 'facebook-post-scheduler'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Log Actions -->
    <div class="fps-log-actions">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('fps_log_actions', 'fps_logs_nonce'); ?>
            <input type="hidden" name="action" value="cleanup_logs">
            <button type="submit" class="button">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clean Old Logs', 'facebook-post-scheduler'); ?>
            </button>
        </form>
        
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('fps_log_actions', 'fps_logs_nonce'); ?>
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="button" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'facebook-post-scheduler'); ?>')">
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Clear All Logs', 'facebook-post-scheduler'); ?>
            </button>
        </form>
    </div>
    
    <!-- Logs Filter -->
    <div class="fps-filters">
        <form method="get">
            <input type="hidden" name="page" value="fps-logs">
            <select name="level" onchange="this.form.submit()">
                <option value="all"><?php _e('All Levels', 'facebook-post-scheduler'); ?></option>
                <option value="error" <?php selected($level_filter, 'error'); ?>><?php _e('Errors', 'facebook-post-scheduler'); ?></option>
                <option value="warning" <?php selected($level_filter, 'warning'); ?>><?php _e('Warnings', 'facebook-post-scheduler'); ?></option>
                <option value="info" <?php selected($level_filter, 'info'); ?>><?php _e('Info', 'facebook-post-scheduler'); ?></option>
                <option value="debug" <?php selected($level_filter, 'debug'); ?>><?php _e('Debug', 'facebook-post-scheduler'); ?></option>
            </select>
        </form>
    </div>
    
    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="manage-column"><?php _e('Time', 'facebook-post-scheduler'); ?></th>
                <th class="manage-column"><?php _e('Level', 'facebook-post-scheduler'); ?></th>
                <th class="manage-column"><?php _e('Message', 'facebook-post-scheduler'); ?></th>
                <th class="manage-column"><?php _e('User', 'facebook-post-scheduler'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="4" class="fps-empty-state">
                    <span class="dashicons dashicons-list-view"></span>
                    <p><?php _e('No logs found', 'facebook-post-scheduler'); ?></p>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?>
                </td>
                <td>
                    <span class="fps-log-level fps-level-<?php echo esc_attr($log->level); ?>">
                        <?php echo esc_html(ucfirst($log->level)); ?>
                    </span>
                </td>
                <td>
                    <?php echo esc_html($log->message); ?>
                    
                    <?php if (!empty($log->context)): ?>
                    <details class="fps-log-context">
                        <summary><?php _e('Context', 'facebook-post-scheduler'); ?></summary>
                        <pre><?php echo esc_html($log->context); ?></pre>
                    </details>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    if ($log->user_id > 0) {
                        $user = get_user_by('id', $log->user_id);
                        echo $user ? esc_html($user->display_name) : __('Unknown User', 'facebook-post-scheduler');
                    } else {
                        _e('System', 'facebook-post-scheduler');
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>