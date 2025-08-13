/**
 * Facebook Post Scheduler - Calendar JavaScript
 * Handles CALENDAR POST functionality ONLY
 * COMPLETELY SEPARATE from other functionalities
 */

(function($) {
    'use strict';

    // Prevent multiple initializations
    if (window.FPSCalendar) {
        console.warn('[FPS] Calendar: Already initialized, skipping');
        return;
    }

    // Calendar object - ONLY for calendar functionality
    window.FPSCalendar = {
        initialized: false,
        currentMonth: null,
        
        init: function() {
            if (this.initialized) {
                console.warn('[FPS] Calendar: Already initialized');
                return;
            }
            
            console.log('[FPS] Calendar: Initializing calendar');
            this.bindEvents();
            this.loadCalendarData();
            this.initialized = true;
        },

        bindEvents: function() {
            // CRITICAL: Remove ALL existing event handlers to prevent recursion
            $(document).off('.fps-calendar');
            
            // Create recurring times (changed from "Add Recurring Schedule")
            $(document).on('click.fps-calendar', '#fps-create-recurring-times, #fps-add-first-time', this.showTimeModal.bind(this));
            
            // Close modal
            $(document).on('click.fps-calendar', '#fps-close-modal, #fps-cancel-time', this.hideTimeModal.bind(this));
            
            // Time form submit
            $(document).on('submit.fps-calendar', '#fps-time-form', this.handleTimeSubmit.bind(this));
            
            // Edit time
            $(document).on('click.fps-calendar', '.fps-edit-time', this.handleEditTime.bind(this));
            
            // Delete time
            $(document).on('click.fps-calendar', '.fps-delete-time', this.handleDeleteTime.bind(this));
            
            // Toggle time
            $(document).on('click.fps-calendar', '.fps-toggle-time', this.handleToggleTime.bind(this));
            
            // Calendar navigation
            $(document).on('click.fps-calendar', '#fps-prev-month', this.handlePrevMonth.bind(this));
            $(document).on('click.fps-calendar', '#fps-next-month', this.handleNextMonth.bind(this));
            
            // Calendar day click
            $(document).on('click.fps-calendar', '.fps-calendar-day', this.handleDayClick.bind(this));
            
            // Modal backdrop click
            $(document).on('click.fps-calendar', '#fps-time-modal', function(e) {
                if (e.target === this) {
                    FPSCalendar.hideTimeModal();
                }
            });
            
            // Day checkboxes
            $(document).on('change.fps-calendar', '.fps-day-checkbox', this.handleDayCheckboxChange.bind(this));
            
            console.log('[FPS] Calendar: Event handlers bound');
        },

        showTimeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            console.log('[FPS] Calendar: Show time modal');
            
            // Reset form
            $('#fps-time-form')[0].reset();
            $('#fps-time-id').val('');
            $('#fps-modal-title').text(fpsCalendar.strings.addTime);
            
            // Uncheck all day checkboxes
            $('.fps-day-checkbox').prop('checked', false);
            
            $('#fps-time-modal').removeClass('hidden');
        },

        hideTimeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            console.log('[FPS] Calendar: Hide time modal');
            
            $('#fps-time-modal').addClass('hidden');
        },

        handleTimeSubmit: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Calendar: Time form submitted');
            
            var time = $('#fps-time-input').val();
            var selectedDays = [];
            
            $('.fps-day-checkbox:checked').each(function() {
                selectedDays.push($(this).val());
            });
            
            // Validation
            if (!time) {
                this.showNotice(fpsCalendar.strings.invalidTime, 'error');
                return;
            }
            
            if (selectedDays.length === 0) {
                this.showNotice(fpsCalendar.strings.selectDays, 'error');
                return;
            }
            
            var timeId = $('#fps-time-id').val();
            var action = timeId ? 'fps_update_recurring_time' : 'fps_create_recurring_time';
            
            var self = this;
            
            $.ajax({
                url: fpsCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    time_id: timeId,
                    time: time,
                    days: selectedDays,
                    nonce: fpsCalendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.hideTimeModal();
                        self.loadCalendarData();
                        self.loadRecurringTimes();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Calendar: Time submit error', error);
                    self.showNotice(fpsCalendar.strings.error, 'error');
                }
            });
        },

        handleEditTime: function(e) {
            e.preventDefault();
            
            var timeId = $(this).data('time-id');
            console.log('[FPS] Calendar: Edit time', timeId);
            
            // Get time data from the DOM or fetch from server
            var $timeItem = $(this).closest('.fps-time-item');
            var time = $timeItem.data('time');
            var days = $timeItem.data('days');
            
            // Populate form
            $('#fps-time-id').val(timeId);
            $('#fps-time-input').val(time);
            $('#fps-modal-title').text(fpsCalendar.strings.editTime);
            
            // Check appropriate day checkboxes
            $('.fps-day-checkbox').prop('checked', false);
            if (days) {
                days.split(',').forEach(function(day) {
                    $('.fps-day-checkbox[value="' + day + '"]').prop('checked', true);
                });
            }
            
            this.showTimeModal();
        },

        handleDeleteTime: function(e) {
            e.preventDefault();
            
            if (!confirm(fpsCalendar.strings.confirmDelete)) {
                return;
            }
            
            var timeId = $(this).data('time-id');
            console.log('[FPS] Calendar: Delete time', timeId);
            
            var self = this;
            
            $.ajax({
                url: fpsCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_delete_recurring_time',
                    time_id: timeId,
                    nonce: fpsCalendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.loadCalendarData();
                        self.loadRecurringTimes();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Calendar: Delete time error', error);
                    self.showNotice(fpsCalendar.strings.error, 'error');
                }
            });
        },

        handleToggleTime: function(e) {
            e.preventDefault();
            
            var timeId = $(this).data('time-id');
            var isActive = $(this).data('active') === '1';
            var newStatus = !isActive;
            
            console.log('[FPS] Calendar: Toggle time', timeId, 'to', newStatus);
            
            var self = this;
            
            $.ajax({
                url: fpsCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_toggle_recurring_time',
                    time_id: timeId,
                    active: newStatus,
                    nonce: fpsCalendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.loadCalendarData();
                        self.loadRecurringTimes();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Calendar: Toggle time error', error);
                    self.showNotice(fpsCalendar.strings.error, 'error');
                }
            });
        },

        handlePrevMonth: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Calendar: Previous month');
            
            var currentDate = new Date(this.currentMonth + '-01');
            currentDate.setMonth(currentDate.getMonth() - 1);
            
            var newMonth = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
            this.loadCalendarData(newMonth);
        },

        handleNextMonth: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Calendar: Next month');
            
            var currentDate = new Date(this.currentMonth + '-01');
            currentDate.setMonth(currentDate.getMonth() + 1);
            
            var newMonth = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
            this.loadCalendarData(newMonth);
        },

        handleDayClick: function(e) {
            var date = $(this).data('date');
            console.log('[FPS] Calendar: Day clicked', date);
            
            // Show day details or allow quick time creation
        },

        handleDayCheckboxChange: function() {
            var checkedCount = $('.fps-day-checkbox:checked').length;
            $('#fps-selected-days-count').text(checkedCount + ' days selected');
        },

        loadCalendarData: function(month) {
            if (!month) {
                month = fpsCalendar.currentMonth;
            }
            
            console.log('[FPS] Calendar: Loading calendar data for', month);
            
            var self = this;
            
            $.ajax({
                url: fpsCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_get_calendar_data',
                    month: month,
                    nonce: fpsCalendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCalendar(response.data.calendar_data);
                        self.currentMonth = month;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Calendar: Load calendar data error', error);
                }
            });
        },

        loadRecurringTimes: function() {
            console.log('[FPS] Calendar: Loading recurring times');
            
            // Reload the page to refresh the times list
            // In a more sophisticated implementation, you would update just the times section
            setTimeout(function() {
                location.reload();
            }, 1000);
        },

        renderCalendar: function(calendarData) {
            console.log('[FPS] Calendar: Rendering calendar', calendarData);
            
            // Update month name
            $('#fps-current-month').text(calendarData.month_name);
            
            // Update calendar grid
            var $grid = $('#fps-calendar-grid');
            $grid.empty();
            
            calendarData.days.forEach(function(day) {
                var dayClasses = 'fps-calendar-day min-h-[100px] p-2 border border-gray-100 rounded-lg cursor-pointer transition-colors duration-200';
                
                if (day.is_today) {
                    dayClasses += ' bg-blue-50 border-blue-200';
                } else if (day.is_past) {
                    dayClasses += ' bg-gray-50 text-gray-400 cursor-not-allowed';
                } else {
                    dayClasses += ' bg-white hover:bg-gray-50';
                }
                
                var $day = $('<div class="' + dayClasses + '" data-date="' + day.date + '">');
                
                // Add day number
                var dayNumberClass = day.is_today ? 'text-blue-600' : (day.is_past ? 'text-gray-400' : 'text-gray-900');
                $day.append('<div class="text-sm font-medium ' + dayNumberClass + '">' + day.day + '</div>');
                
                // Add recurring times
                if (day.recurring_times && day.recurring_times.length > 0) {
                    var $recurringContainer = $('<div class="mt-1 space-y-1">');
                    day.recurring_times.forEach(function(recurring) {
                        var $recurring = $('<div class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded truncate" title="Recurring time: ' + recurring.time + '">' + recurring.time + '</div>');
                        $recurringContainer.append($recurring);
                    });
                    $day.append($recurringContainer);
                }
                
                // Add scheduled posts
                if (day.scheduled_posts && day.scheduled_posts.length > 0) {
                    var $postsContainer = $('<div class="mt-1 space-y-1">');
                    day.scheduled_posts.forEach(function(post) {
                        var $post = $('<div class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">' + post.time + '</div>');
                        $postsContainer.append($post);
                    });
                    $day.append($postsContainer);
                }
                
                $grid.append($day);
            });
        },

        showNotice: function(message, type) {
            // Remove existing notices
            $('.fps-calendar-notice').remove();
            
            var noticeClass = 'notice-' + type;
            var notice = $('<div class="notice fps-calendar-notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        }
    };

    // Initialize ONLY when document is ready and ONLY on calendar page
    $(document).ready(function() {
        // Check if we're on the calendar page specifically
        if (typeof fpsCalendar !== 'undefined' && fpsCalendar.currentMonth) {
            console.log('[FPS] Calendar: Initializing on calendar page');
            FPSCalendar.init();
        }
    });

})(jQuery);