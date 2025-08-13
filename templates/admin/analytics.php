<?php
/**
 * Analytics Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Analytics', 'facebook-post-scheduler'); ?></h1>
    
    <?php if (empty($pages)): ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('No Facebook pages found. Please', 'facebook-post-scheduler'); ?>
            <a href="<?php echo admin_url('admin.php?page=fps-settings'); ?>"><?php _e('connect your Facebook account', 'facebook-post-scheduler'); ?></a>
            <?php _e('first.', 'facebook-post-scheduler'); ?>
        </p>
    </div>
    <?php else: ?>
    
    <!-- Page Selection -->
    <div class="fps-page-selector">
        <form method="get">
            <input type="hidden" name="page" value="fps-analytics">
            <select name="page_id" onchange="this.form.submit()">
                <option value=""><?php _e('Select a page...', 'facebook-post-scheduler'); ?></option>
                <?php foreach ($pages as $page): ?>
                <option value="<?php echo esc_attr($page['id']); ?>" <?php selected($selected_page, $page['id']); ?>>
                    <?php echo esc_html($page['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <?php if ($selected_page && !empty($insights_data)): ?>
    <!-- Analytics Dashboard -->
    <div class="fps-analytics-dashboard">
        <div class="fps-insights-grid">
            <?php foreach ($insights_data as $insight): ?>
            <div class="fps-insight-card">
                <div class="fps-insight-header">
                    <h3><?php echo esc_html(str_replace('_', ' ', ucwords($insight['name']))); ?></h3>
                </div>
                <div class="fps-insight-content">
                    <div class="fps-insight-value">
                        <?php 
                        $value = isset($insight['values'][0]['value']) ? $insight['values'][0]['value'] : 0;
                        echo esc_html(number_format($value));
                        ?>
                    </div>
                    <div class="fps-insight-period">
                        <?php echo esc_html(ucfirst($insight['period'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($selected_page): ?>
    <div class="fps-empty-state">
        <span class="dashicons dashicons-chart-area"></span>
        <p><?php _e('No analytics data available for this page.', 'facebook-post-scheduler'); ?></p>
        <p class="description"><?php _e('Analytics data may take 24-48 hours to appear after connecting your page.', 'facebook-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>