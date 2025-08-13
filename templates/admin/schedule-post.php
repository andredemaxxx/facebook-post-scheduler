<?php
/**
 * Schedule Post Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Schedule New Post', 'facebook-post-scheduler'); ?></h1>
    
    <?php if (empty($pages)): ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('No Facebook pages found. Please', 'facebook-post-scheduler'); ?>
            <a href="<?php echo admin_url('admin.php?page=fps-settings'); ?>"><?php _e('connect your Facebook account', 'facebook-post-scheduler'); ?></a>
            <?php _e('first.', 'facebook-post-scheduler'); ?>
        </p>
    </div>
    <?php else: ?>
    
    <form id="fps-schedule-form" class="fps-schedule-form">
        <?php wp_nonce_field('fps_admin_nonce', 'fps_nonce'); ?>
        
        <div class="fps-form-grid">
            <!-- Left Column -->
            <div class="fps-form-column">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Post Content', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <!-- Facebook Page Selection -->
                        <div class="fps-form-group">
                            <label for="fps-page-id"><?php _e('Facebook Page', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <select id="fps-page-id" name="page_id" required>
                                <option value=""><?php _e('Select a page...', 'facebook-post-scheduler'); ?></option>
                                <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page['id']); ?>">
                                    <?php echo esc_html($page['name']); ?>
                                    <?php if (isset($page['category'])): ?>
                                    (<?php echo esc_html($page['category']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Post Message -->
                        <div class="fps-form-group">
                            <label for="fps-message"><?php _e('Message', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <textarea id="fps-message" name="message" rows="6" placeholder="<?php _e('What do you want to share?', 'facebook-post-scheduler'); ?>" required></textarea>
                            <div class="fps-character-count">
                                <span id="fps-char-count">0</span> <?php _e('characters', 'facebook-post-scheduler'); ?>
                            </div>
                        </div>
                        
                        <!-- Link -->
                        <div class="fps-form-group">
                            <label for="fps-link"><?php _e('Link (Optional)', 'facebook-post-scheduler'); ?></label>
                            <input type="url" id="fps-link" name="link" placeholder="https://example.com">
                            <p class="description"><?php _e('Add a link to share with your post', 'facebook-post-scheduler'); ?></p>
                        </div>
                        
                        <!-- Media Upload -->
                        <div class="fps-form-group">
                            <label><?php _e('Media (Optional)', 'facebook-post-scheduler'); ?></label>
                            
                            <div class="fps-media-tabs">
                                <button type="button" class="fps-tab-button active" data-tab="carousel">
                                    <span class="dashicons dashicons-images-alt2"></span>
                                    <?php _e('Carousel (Multiple)', 'facebook-post-scheduler'); ?>
                                </button>
                                <button type="button" class="fps-tab-button" data-tab="image">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <?php _e('Single Image', 'facebook-post-scheduler'); ?>
                                </button>
                                <button type="button" class="fps-tab-button" data-tab="video">
                                    <span class="dashicons dashicons-format-video"></span>
                                    <?php _e('Video', 'facebook-post-scheduler'); ?>
                                </button>
                            </div>
                            
                            <!-- Carousel Tab (Multiple Images) -->
                            <div class="fps-tab-content active" id="fps-tab-carousel">
                                <div class="fps-upload-area">
                                    <button type="button" id="fps-images-upload" class="w-full h-full">
                                        <div class="fps-upload-placeholder">
                                            <span class="dashicons dashicons-images-alt2"></span>
                                            <p><?php _e('Click to upload multiple images for carousel post', 'facebook-post-scheduler'); ?></p>
                                            <p class="description"><?php _e('Supported formats: JPG, PNG, GIF (max 10 images, 10MB each)', 'facebook-post-scheduler'); ?></p>
                                        </div>
                                    </button>
                                </div>
                                
                                <div id="fps-images-container" class="fps-images-section mt-4 grid grid-cols-4 gap-4" style="display: none;">
                                    <!-- Multiple images will be displayed here -->
                                </div>
                                
                                <input type="hidden" id="fps-image-ids" name="image_ids" value="">
                            </div>
                            
                            <!-- Single Image Tab -->
                            <div class="fps-tab-content" id="fps-tab-image">
                                <div class="fps-upload-area">
                                    <button type="button" id="fps-image-upload" class="w-full h-full">
                                        <div class="fps-upload-placeholder">
                                            <span class="dashicons dashicons-cloud-upload"></span>
                                            <p><?php _e('Click to upload a single image', 'facebook-post-scheduler'); ?></p>
                                            <p class="description"><?php _e('Supported formats: JPG, PNG, GIF (max 10MB)', 'facebook-post-scheduler'); ?></p>
                                        </div>
                                    </button>
                                    
                                    <div class="fps-image-preview" style="display: none;">
                                        <img id="fps-image-preview-img" src="" alt="" class="max-w-full h-auto rounded">
                                        <button type="button" class="fps-remove-media absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600" data-type="image">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <input type="hidden" id="fps-image-id" name="image_id" value="">
                            </div>
                            
                            <!-- Video Tab -->
                            <div class="fps-tab-content" id="fps-tab-video">
                                <div class="fps-upload-area">
                                    <button type="button" id="fps-video-upload" class="w-full h-full">
                                        <div class="fps-upload-placeholder">
                                            <span class="dashicons dashicons-format-video"></span>
                                            <p><?php _e('Click to upload a video', 'facebook-post-scheduler'); ?></p>
                                            <p class="description"><?php _e('Supported formats: MP4, MOV, AVI (max 100MB)', 'facebook-post-scheduler'); ?></p>
                                        </div>
                                    </button>
                                    
                                    <div class="fps-video-preview" style="display: none;">
                                        <video id="fps-video-preview-video" controls class="max-w-full h-auto rounded"></video>
                                        <button type="button" class="fps-remove-media absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600" data-type="video">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <input type="hidden" id="fps-video-id" name="video_id" value="">
                            </div>
                        </div>
                        
                        <!-- Share to Story Option -->
                        <div class="fps-form-group">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="fps-share-to-story" name="share_to_story" value="1" class="rounded text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700"><?php _e('Share to Story automatically', 'facebook-post-scheduler'); ?></span>
                            </label>
                            <p class="description text-xs text-gray-500 mt-1"><?php _e('Automatically share this post to your Facebook page story', 'facebook-post-scheduler'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduling Options -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Scheduling', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="fps-form-group">
                            <label for="fps-scheduled-date"><?php _e('Date', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <input type="date" id="fps-scheduled-date" name="scheduled_date" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="fps-form-group">
                            <label for="fps-scheduled-time"><?php _e('Time', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <input type="time" id="fps-scheduled-time" name="scheduled_time" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="fps-timezone-info flex items-center space-x-2 text-sm text-gray-600 mt-2">
                            <span class="dashicons dashicons-clock"></span>
                            <span><?php _e('Timezone: São Paulo (GMT-3)', 'facebook-post-scheduler'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="fps-form-column">
                <!-- Post Preview -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Preview', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="fps-post-preview">
                            <div class="fps-preview-placeholder text-center py-8 text-gray-500">
                                <span class="dashicons dashicons-facebook text-4xl text-blue-500 mb-4"></span>
                                <p><?php _e('Preview will appear here as you type', 'facebook-post-scheduler'); ?></p>
                            </div>
                        </div>
                        
                        <button type="button" id="fps-refresh-preview" class="button mt-4">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh Preview', 'facebook-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Submit Actions -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Actions', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="fps-submit-actions space-y-3">
                            <button type="submit" class="button button-primary button-large w-full">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php _e('Schedule Post', 'facebook-post-scheduler'); ?>
                            </button>
                            
                            <button type="button" id="fps-save-draft" class="button w-full">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save as Draft', 'facebook-post-scheduler'); ?>
                            </button>
                        </div>
                        
                        <div class="fps-form-help mt-4 pt-4 border-t border-gray-200">
                            <p class="description text-sm text-gray-600">
                                <?php _e('Your post will be automatically published to Facebook at the scheduled time.', 'facebook-post-scheduler'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="fps-loading-overlay" style="display: none;">
    <div class="fps-loading-content">
        <div class="fps-spinner"></div>
        <p><?php _e('Scheduling your post...', 'facebook-post-scheduler'); ?></p>
    </div>
</div>