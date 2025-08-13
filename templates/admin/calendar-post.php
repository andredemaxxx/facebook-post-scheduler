<?php
/**
 * Calendar Post Template - COMPLETELY SEPARATE from other functionalities
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="wrap">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-gray-900"><?php _e('Calendar Post', 'facebook-post-scheduler'); ?></h1>
        <button id="fps-create-recurring-times" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <?php _e('Create Recurring Times', 'facebook-post-scheduler'); ?>
        </button>
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
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Calendar View -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900"><?php _e('Calendar View', 'facebook-post-scheduler'); ?></h2>
                        <div class="flex items-center space-x-2">
                            <button id="fps-prev-month" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <span id="fps-current-month" class="text-lg font-medium text-gray-900">
                                <?php echo esc_html($calendar_data['month_name']); ?>
                            </span>
                            <button id="fps-next-month" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-7 gap-1 mb-4">
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Sun', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Mon', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Tue', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Wed', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Thu', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Fri', 'facebook-post-scheduler'); ?></div>
                        <div class="text-center text-sm font-medium text-gray-500 py-2"><?php _e('Sat', 'facebook-post-scheduler'); ?></div>
                    </div>
                    
                    <div id="fps-calendar-grid" class="grid grid-cols-7 gap-1">
                        <?php foreach ($calendar_data['days'] as $day): ?>
                        <div class="fps-calendar-day min-h-[100px] p-2 border border-gray-100 rounded-lg <?php echo $day['is_today'] ? 'bg-blue-50 border-blue-200' : ($day['is_past'] ? 'bg-gray-50' : 'bg-white hover:bg-gray-50'); ?>" 
                             data-date="<?php echo esc_attr($day['date']); ?>">
                            <div class="text-sm font-medium <?php echo $day['is_today'] ? 'text-blue-600' : ($day['is_past'] ? 'text-gray-400' : 'text-gray-900'); ?>">
                                <?php echo esc_html($day['day']); ?>
                            </div>
                            
                            <?php if (!empty($day['recurring_times'])): ?>
                            <div class="mt-1 space-y-1">
                                <?php foreach ($day['recurring_times'] as $recurring): ?>
                                <div class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded truncate" title="<?php echo esc_attr('Recurring time: ' . $recurring['time']); ?>">
                                    <?php echo esc_html($recurring['time']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($day['scheduled_posts'])): ?>
                            <div class="mt-1 space-y-1">
                                <?php foreach ($day['scheduled_posts'] as $post): ?>
                                <div class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                    <?php echo esc_html($post['time']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recurring Times Management -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900"><?php _e('Recurring Times', 'facebook-post-scheduler'); ?></h2>
                </div>
                
                <div class="p-6">
                    <?php if (!empty($recurring_times)): ?>
                    <div id="fps-recurring-times" class="space-y-4">
                        <?php foreach ($recurring_times as $time): ?>
                        <div class="fps-time-item border border-gray-200 rounded-lg p-4 <?php echo $time['active'] ? 'bg-white' : 'bg-gray-50'; ?>" 
                             data-time-id="<?php echo esc_attr($time['id']); ?>"
                             data-time="<?php echo esc_attr($time['time']); ?>"
                             data-days="<?php echo esc_attr(implode(',', $time['days'])); ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $time['active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo esc_html($time['time']); ?>
                                        </span>
                                        <?php if (!$time['active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <?php _e('Inactive', 'facebook-post-scheduler'); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        <?php echo esc_html(implode(', ', $time['day_names'])); ?>
                                    </p>
                                </div>
                                
                                <div class="flex items-center space-x-2 ml-4">
                                    <button class="fps-edit-time text-gray-400 hover:text-blue-600 p-1" 
                                            data-time-id="<?php echo esc_attr($time['id']); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button class="fps-toggle-time text-gray-400 hover:text-yellow-600 p-1" 
                                            data-time-id="<?php echo esc_attr($time['id']); ?>"
                                            data-active="<?php echo $time['active'] ? '1' : '0'; ?>">
                                        <?php if ($time['active']): ?>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <?php else: ?>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h1m4 0h1m-6-8h1m4 0h1M9 6h6"></path>
                                        </svg>
                                        <?php endif; ?>
                                    </button>
                                    <button class="fps-delete-time text-gray-400 hover:text-red-600 p-1" 
                                            data-time-id="<?php echo esc_attr($time['id']); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500 mb-4"><?php _e('No recurring times found', 'facebook-post-scheduler'); ?></p>
                        <button id="fps-add-first-time" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                            <?php _e('Create Your First Recurring Time', 'facebook-post-scheduler'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Timezone Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-blue-800 font-medium"><?php _e('Timezone: São Paulo (GMT-3)', 'facebook-post-scheduler'); ?></p>
                        <p class="text-blue-600 text-sm">
                            <?php 
                            $timezone_info = FPS_Timezone_Manager::get_timezone_info();
                            printf(__('Current time: %s', 'facebook-post-scheduler'), $timezone_info['current_time']);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-900 mb-2"><?php _e('How it works', 'facebook-post-scheduler'); ?></h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• <?php _e('Create recurring times for specific days and hours', 'facebook-post-scheduler'); ?></li>
                    <li>• <?php _e('These times will be available in Schedule Post Multi', 'facebook-post-scheduler'); ?></li>
                    <li>• <?php _e('WordPress cron will automatically distribute posts', 'facebook-post-scheduler'); ?></li>
                    <li>• <?php _e('All times use São Paulo timezone (GMT-3)', 'facebook-post-scheduler'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Create/Edit Recurring Time Modal -->
<div id="fps-time-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 id="fps-modal-title" class="text-lg font-semibold text-gray-900">
                        <?php _e('Create Recurring Times', 'facebook-post-scheduler'); ?>
                    </h3>
                    <button id="fps-close-modal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <form id="fps-time-form" class="p-6 space-y-4">
                <input type="hidden" id="fps-time-id" name="time_id" value="">
                
                <div>
                    <label for="fps-time-input" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php _e('Time', 'facebook-post-scheduler'); ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="time" id="fps-time-input" name="time" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <?php _e('Days of Week', 'facebook-post-scheduler'); ?> <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="0">
                            <span class="text-sm"><?php _e('Sunday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="1">
                            <span class="text-sm"><?php _e('Monday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="2">
                            <span class="text-sm"><?php _e('Tuesday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="3">
                            <span class="text-sm"><?php _e('Wednesday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="4">
                            <span class="text-sm"><?php _e('Thursday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="5">
                            <span class="text-sm"><?php _e('Friday', 'facebook-post-scheduler'); ?></span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="fps-day-checkbox text-blue-600 focus:ring-blue-500 rounded" value="6">
                            <span class="text-sm"><?php _e('Saturday', 'facebook-post-scheduler'); ?></span>
                        </label>
                    </div>
                    <div id="fps-selected-days-count" class="text-xs text-gray-500 mt-2">0 days selected</div>
                </div>
                
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <button type="button" id="fps-cancel-time" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors duration-200">
                        <?php _e('Cancel', 'facebook-post-scheduler'); ?>
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors duration-200">
                        <?php _e('Save Time', 'facebook-post-scheduler'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>