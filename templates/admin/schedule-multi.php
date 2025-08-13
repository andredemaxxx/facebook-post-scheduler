<?php
/**
 * Schedule Multi Template - COMPLETELY SEPARATE from normal scheduling
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="wrap">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-gray-900"><?php _e('Schedule Post Multi', 'facebook-post-scheduler'); ?></h1>
        <div class="text-sm text-gray-500">
            <span class="dashicons dashicons-clock"></span>
            <?php _e('Timezone: São Paulo (GMT-3)', 'facebook-post-scheduler'); ?>
        </div>
    </div>
    
    <?php if (empty($pages)): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <div>
                <p class="text-yellow-800 font-medium">
                    <?php _e('No Facebook pages found. Please', 'facebook-post-scheduler'); ?>
                    <a href="<?php echo admin_url('admin.php?page=fps-settings'); ?>" class="underline hover:no-underline">
                        <?php _e('connect your Facebook account', 'facebook-post-scheduler'); ?>
                    </a>
                    <?php _e('first.', 'facebook-post-scheduler'); ?>
                </p>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Left Column: Configuration and Upload -->
            <div class="xl:col-span-2 space-y-6">
                <!-- Facebook Page Selection -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <?php _e('Select Facebook Page', 'facebook-post-scheduler'); ?>
                    </h3>
                    <select id="fps-multi-page-id" name="page_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                
                <!-- Upload Mode Selection -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                        </svg>
                        <?php _e('Upload Mode', 'facebook-post-scheduler'); ?>
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="upload_mode" value="filename" checked class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div>
                                <div class="font-medium text-gray-900"><?php _e('Pair by numeric part', 'facebook-post-scheduler'); ?></div>
                                <div class="text-sm text-gray-600"><?php _e('image1.jpg + text1.txt (same number)', 'facebook-post-scheduler'); ?></div>
                                <div class="text-xs text-blue-600 mt-1"><?php _e('Supports carousel: image1a.jpg, image1b.jpg → grouped', 'facebook-post-scheduler'); ?></div>
                            </div>
                        </label>
                        <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="upload_mode" value="order" class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div>
                                <div class="font-medium text-gray-900"><?php _e('Pair by upload order', 'facebook-post-scheduler'); ?></div>
                                <div class="text-sm text-gray-600"><?php _e('First image + first text, second image + second text', 'facebook-post-scheduler'); ?></div>
                                <div class="text-xs text-blue-600 mt-1"><?php _e('Can group consecutive images as carousel', 'facebook-post-scheduler'); ?></div>
                            </div>
                        </label>
                        <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="upload_mode" value="manual" class="mt-1 text-blue-600 focus:ring-blue-500">
                            <div>
                                <div class="font-medium text-gray-900"><?php _e('Images only + manual text', 'facebook-post-scheduler'); ?></div>
                                <div class="text-sm text-gray-600"><?php _e('Upload images and enter text manually for each', 'facebook-post-scheduler'); ?></div>
                                <div class="text-xs text-blue-600 mt-1"><?php _e('Can group images in carousel with shared text', 'facebook-post-scheduler'); ?></div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Image Upload -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <?php _e('Upload Images', 'facebook-post-scheduler'); ?>
                    </h3>
                    
                    <div id="fps-image-dropzone" class="fps-dropzone border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors duration-200 cursor-pointer">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-gray-600 mb-2"><?php _e('Click to upload images or drag and drop', 'facebook-post-scheduler'); ?></p>
                        <p class="text-sm text-gray-500"><?php _e('Supported formats: JPG, PNG, GIF, WebP (max 10MB each)', 'facebook-post-scheduler'); ?></p>
                    </div>
                    
                    <input type="file" id="fps-upload-images" multiple accept="image/*" class="hidden">
                    
                    <div id="fps-images-list" class="mt-4 space-y-2">
                        <!-- Images will be listed here -->
                    </div>
                </div>
                
                <!-- Text Upload (hidden for manual mode) -->
                <div id="fps-text-upload-section" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <?php _e('Upload Text Files', 'facebook-post-scheduler'); ?>
                    </h3>
                    
                    <div id="fps-text-dropzone" class="fps-dropzone border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors duration-200 cursor-pointer">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-600 mb-2"><?php _e('Click to upload text files or drag and drop', 'facebook-post-scheduler'); ?></p>
                        <p class="text-sm text-gray-500"><?php _e('Supported format: TXT (max 1MB each)', 'facebook-post-scheduler'); ?></p>
                    </div>
                    
                    <input type="file" id="fps-upload-texts" multiple accept=".txt" class="hidden">
                    
                    <div id="fps-texts-list" class="mt-4 space-y-2">
                        <!-- Text files will be listed here -->
                    </div>
                </div>
                
                <!-- Manual Text Input (hidden by default) -->
                <div id="fps-manual-text-section" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <?php _e('Manual Text Input', 'facebook-post-scheduler'); ?>
                    </h3>
                    <div id="fps-manual-texts" class="space-y-4">
                        <!-- Manual text inputs will be generated here -->
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex space-x-4">
                        <button type="button" id="fps-process-files" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                            <?php _e('Process Files', 'facebook-post-scheduler'); ?>
                        </button>
                        <button type="button" id="fps-clear-files" class="px-6 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <?php _e('Clear All', 'facebook-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Preview and Scheduling -->
            <div class="space-y-6">
                <!-- Post Preview -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <?php _e('Post Preview', 'facebook-post-scheduler'); ?>
                    </h3>
                    <div id="fps-preview-list" class="space-y-4 max-h-96 overflow-y-auto">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <p><?php _e('Preview will appear here after processing files', 'facebook-post-scheduler'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduling Options -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <svg class="w-5 h-5 inline-block mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4m-6 0h6m-6 0V7a1 1 0 00-1 1v11a2 2 0 002 2h6a2 2 0 002-2V8a1 1 0 00-1-1V7"></path>
                        </svg>
                        <?php _e('Scheduling Options', 'facebook-post-scheduler'); ?>
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="fps-schedule-date" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php _e('Schedule Date', 'facebook-post-scheduler'); ?>
                            </label>
                            <input type="date" id="fps-schedule-date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php _e('Available Time Slots', 'facebook-post-scheduler'); ?>
                            </label>
                            <div class="text-xs text-gray-500 mb-3">
                                <?php _e('Select time slots from Calendar Post recurring times', 'facebook-post-scheduler'); ?>
                            </div>
                            <div id="fps-available-slots" class="grid grid-cols-3 gap-2">
                                <div class="text-center py-4 text-gray-500 col-span-3">
                                    <p class="text-sm"><?php _e('Loading available time slots...', 'facebook-post-scheduler'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Share to Story Option -->
                        <div class="border-t border-gray-200 pt-4">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="fps-share-to-story" class="text-blue-600 focus:ring-blue-500 rounded">
                                <span class="text-sm font-medium text-gray-700"><?php _e('Share to Story automatically', 'facebook-post-scheduler'); ?></span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1"><?php _e('Automatically share posts to Facebook page story', 'facebook-post-scheduler'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Final Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="text-center">
                        <button type="button" id="fps-schedule-multi" class="w-full bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" disabled>
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4m-6 0h6m-6 0V7a1 1 0 00-1 1v11a2 2 0 002 2h6a2 2 0 002-2V8a1 1 0 00-1-1V7"></path>
                            </svg>
                            <?php _e('Schedule All Posts', 'facebook-post-scheduler'); ?>
                        </button>
                        <p id="fps-schedule-info" class="text-sm text-gray-500 mt-2">
                            <?php _e('Upload files to begin', 'facebook-post-scheduler'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>