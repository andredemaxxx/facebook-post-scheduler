/**
 * Facebook Post Scheduler - Admin JavaScript
 * Handles NORMAL post scheduling functionality ONLY
 * COMPLETELY SEPARATE from multi-scheduler functionality
 */

(function($) {
    'use strict';

    // Prevent multiple initializations
    if (window.FPSAdmin) {
        console.warn('[FPS] Admin: Already initialized, skipping');
        return;
    }

    // Main admin object - ONLY for normal post scheduling
    window.FPSAdmin = {
        initialized: false,
        mediaFrames: {}, // Store media frames to prevent recreation
        
        init: function() {
            if (this.initialized) {
                console.warn('[FPS] Admin: Already initialized');
                return;
            }
            
            console.log('[FPS] Admin: Initializing normal post scheduler');
            this.bindEvents();
            this.initDatePicker();
            this.initMediaUploader();
            this.initialized = true;
        },

        bindEvents: function() {
            // CRITICAL: Remove ALL existing event handlers to prevent recursion
            $(document).off('.fps-admin');
            
            // Schedule post form
            $(document).on('submit.fps-admin', '#fps-schedule-form', this.handleSchedulePost.bind(this));
            
            // Edit post
            $(document).on('click.fps-admin', '.fps-edit-post', this.handleEditPost.bind(this));
            
            // Delete post
            $(document).on('click.fps-admin', '.fps-delete-post', this.handleDeletePost.bind(this));
            
            // Test connection
            $(document).on('click.fps-admin', '#fps-test-connection', this.handleTestConnection.bind(this));
            
            // Disconnect Facebook
            $(document).on('click.fps-admin', '#fps-disconnect', this.handleDisconnectFacebook.bind(this));
            
            // Refresh pages
            $(document).on('click.fps-admin', '#fps-refresh-pages', this.handleRefreshPages.bind(this));
            
            // Refresh preview
            $(document).on('click.fps-admin', '#fps-refresh-preview', this.handleRefreshPreview.bind(this));
            
            // Character count
            $(document).on('input.fps-admin', '#fps-message', this.updateCharacterCount.bind(this));
            
            // Auto preview on input (with debounce)
            $(document).on('input.fps-admin', '#fps-message, #fps-link', 
                this.debounce(this.handleRefreshPreview.bind(this), 1000));
            
            // Page selection change - LIVE preview update
            $(document).on('change.fps-admin', '#fps-page-id', this.handlePageChange.bind(this));
            
            // Media tabs
            $(document).on('click.fps-admin', '.fps-tab-button', this.handleMediaTab.bind(this));
            
            // Remove media
            $(document).on('click.fps-admin', '.fps-remove-media', this.handleRemoveMedia.bind(this));
            
            // Settings tabs - FIXED
            $(document).on('click.fps-admin', '.nav-tab', this.handleSettingsTab.bind(this));
            
            console.log('[FPS] Admin: Event handlers bound');
        },

        initDatePicker: function() {
            // Remove existing datepicker to prevent conflicts
            if ($('#fps-scheduled-date').hasClass('hasDatepicker')) {
                $('#fps-scheduled-date').datepicker('destroy');
            }
            
            // Remove broken UI datepicker
            $('.ui-datepicker-div').remove();
            
            // Use HTML5 date input with custom styling
            var $dateInput = $('#fps-scheduled-date');
            if ($dateInput.length) {
                // Set minimum date to today
                var today = new Date().toISOString().split('T')[0];
                $dateInput.attr('min', today);
                
                console.log('[FPS] Admin: Date picker initialized');
            }
        },

        initMediaUploader: function() {
            var self = this;
            
            // CRITICAL: Remove existing handlers to prevent recursion
            $(document).off('click.fps-media');
            
            // Multiple images upload (Carousel)
            $(document).on('click.fps-media', '#fps-images-upload', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('[FPS] Admin: Multiple images upload clicked');
                
                if (!wp.media) {
                    console.error('[FPS] Admin: wp.media not available');
                    return;
                }
                
                // Create or reuse media frame
                if (!self.mediaFrames.images) {
                    self.mediaFrames.images = wp.media({
                        title: fpsAdmin.strings.uploadFiles,
                        button: {
                            text: fpsAdmin.strings.selectFiles
                        },
                        multiple: true,
                        library: {
                            type: 'image'
                        }
                    });

                    self.mediaFrames.images.on('select', function() {
                        var attachments = self.mediaFrames.images.state().get('selection').toJSON();
                        self.handleMultipleImagesSelected(attachments);
                    });
                }

                self.mediaFrames.images.open();
            });
            
            // Single image upload
            $(document).on('click.fps-media', '#fps-image-upload', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('[FPS] Admin: Single image upload clicked');
                
                if (!wp.media) {
                    console.error('[FPS] Admin: wp.media not available');
                    return;
                }
                
                // Create or reuse media frame
                if (!self.mediaFrames.image) {
                    self.mediaFrames.image = wp.media({
                        title: fpsAdmin.strings.uploadFiles,
                        button: {
                            text: fpsAdmin.strings.selectFiles
                        },
                        multiple: false,
                        library: {
                            type: 'image'
                        }
                    });

                    self.mediaFrames.image.on('select', function() {
                        var attachment = self.mediaFrames.image.state().get('selection').first().toJSON();
                        self.handleImageSelected(attachment);
                    });
                }

                self.mediaFrames.image.open();
            });
            
            // Video upload
            $(document).on('click.fps-media', '#fps-video-upload', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('[FPS] Admin: Video upload clicked');
                
                if (!wp.media) {
                    console.error('[FPS] Admin: wp.media not available');
                    return;
                }
                
                // Create or reuse media frame
                if (!self.mediaFrames.video) {
                    self.mediaFrames.video = wp.media({
                        title: fpsAdmin.strings.uploadFiles,
                        button: {
                            text: fpsAdmin.strings.selectFiles
                        },
                        multiple: false,
                        library: {
                            type: 'video'
                        }
                    });

                    self.mediaFrames.video.on('select', function() {
                        var attachment = self.mediaFrames.video.state().get('selection').first().toJSON();
                        self.handleVideoSelected(attachment);
                    });
                }

                self.mediaFrames.video.open();
            });
            
            console.log('[FPS] Admin: Media uploader initialized');
        },

        handleMultipleImagesSelected: function(attachments) {
            console.log('[FPS] Admin: Multiple images selected', attachments.length);
            
            var $container = $('#fps-images-container');
            $container.empty();
            
            var imageIds = [];
            
            attachments.forEach(function(attachment, index) {
                imageIds.push(attachment.id);
                
                var $imageItem = $('<div class="fps-image-item" data-attachment-id="' + attachment.id + '">');
                $imageItem.html(`
                    <div class="relative">
                        <img src="${attachment.url}" alt="" class="w-20 h-20 object-cover rounded">
                        <button type="button" class="fps-remove-image absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600" data-index="${index}">
                            ×
                        </button>
                    </div>
                    <div class="text-xs text-gray-600 mt-1 truncate">${attachment.filename}</div>
                `);
                $container.append($imageItem);
            });
            
            // Store image IDs for form submission
            $('#fps-image-ids').val(JSON.stringify(imageIds));
            
            $('.fps-images-section').show();
            this.handleRefreshPreview();
        },

        handleImageSelected: function(attachment) {
            console.log('[FPS] Admin: Single image selected', attachment);
            
            $('#fps-image-preview-img').attr('src', attachment.url);
            $('#fps-image-id').val(attachment.id);
            $('.fps-upload-placeholder').hide();
            $('.fps-image-preview').show();
            
            this.handleRefreshPreview();
        },

        handleVideoSelected: function(attachment) {
            console.log('[FPS] Admin: Video selected', attachment);
            
            $('#fps-video-preview-video').attr('src', attachment.url);
            $('#fps-video-id').val(attachment.id);
            $('.fps-upload-placeholder').hide();
            $('.fps-video-preview').show();
            
            this.handleRefreshPreview();
        },

        handleSchedulePost: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Admin: Schedule post form submitted');
            
            var formData = new FormData(e.target);
            formData.append('action', 'fps_schedule_post');
            formData.append('nonce', fpsAdmin.nonce);
            
            // Add multiple images if selected
            var imageIds = $('#fps-image-ids').val();
            if (imageIds) {
                formData.append('image_ids', imageIds);
            }
            
            // Add single image if selected
            var imageId = $('#fps-image-id').val();
            if (imageId) {
                formData.append('image_id', imageId);
            }
            
            // Add video if selected
            var videoId = $('#fps-video-id').val();
            if (videoId) {
                formData.append('video_id', videoId);
            }
            
            // Add share to story option
            if ($('#fps-share-to-story').is(':checked')) {
                formData.append('share_to_story', '1');
            }
            
            this.showLoading();
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    FPSAdmin.hideLoading();
                    
                    if (response.success) {
                        FPSAdmin.showNotice(response.data.message, 'success');
                        
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    FPSAdmin.hideLoading();
                    console.error('[FPS] Admin: Schedule post error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        handlePageChange: function(e) {
            var pageId = $(this).val();
            console.log('[FPS] Admin: Page changed to', pageId);
            
            if (pageId) {
                this.loadPageInfo(pageId);
                this.handleRefreshPreview(); // LIVE preview update
            }
        },

        loadPageInfo: function(pageId) {
            var self = this;
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_get_page_info',
                    page_id: pageId,
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.page) {
                        self.updatePagePreview(response.data.page);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Admin: Load page info error', error);
                }
            });
        },

        updatePagePreview: function(page) {
            $('.fps-page-name').text(page.name);
            
            if (page.picture && page.picture.data && page.picture.data.url) {
                $('.fps-page-avatar img').attr('src', page.picture.data.url);
                $('.fps-page-avatar').show();
            }
        },

        handleRefreshPreview: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            console.log('[FPS] Admin: Refresh preview');
            
            var message = $('#fps-message').val();
            var link = $('#fps-link').val();
            var pageId = $('#fps-page-id').val();
            var imageIds = $('#fps-image-ids').val();
            var imageId = $('#fps-image-id').val();
            var videoId = $('#fps-video-id').val();
            
            if (!message && !link && !imageIds && !imageId && !videoId) {
                $('#fps-post-preview').html(this.getEmptyPreview());
                return;
            }
            
            // Show loading state
            $('#fps-post-preview').html('<div class="fps-preview-loading"><div class="fps-spinner"></div><p>' + fpsAdmin.strings.loadingPreview + '</p></div>');
            
            var self = this;
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_get_post_preview',
                    message: message,
                    link: link,
                    page_id: pageId,
                    image_ids: imageIds,
                    image_id: imageId,
                    video_id: videoId,
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#fps-post-preview').html(response.data.preview);
                    } else {
                        $('#fps-post-preview').html('<div class="fps-preview-error"><p>' + fpsAdmin.strings.previewError + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Admin: Preview error', error);
                    $('#fps-post-preview').html('<div class="fps-preview-error"><p>' + fpsAdmin.strings.previewError + '</p></div>');
                }
            });
        },

        getEmptyPreview: function() {
            return '<div class="fps-preview-placeholder"><span class="dashicons dashicons-facebook"></span><p>Preview will appear here as you type</p></div>';
        },

        updateCharacterCount: function() {
            var message = $(this).val();
            var count = message.length;
            $('#fps-char-count').text(count);
            
            if (count > 2000) {
                $('#fps-char-count').addClass('over-limit');
            } else {
                $('#fps-char-count').removeClass('over-limit');
            }
        },

        handleMediaTab: function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            console.log('[FPS] Admin: Media tab changed to', tab);
            
            $('.fps-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.fps-tab-content').removeClass('active');
            $('#fps-tab-' + tab).addClass('active');
            
            // Clear previous selections when switching tabs
            this.clearMediaSelections();
        },

        clearMediaSelections: function() {
            // Clear all media selections
            $('#fps-image-ids').val('');
            $('#fps-image-id').val('');
            $('#fps-video-id').val('');
            
            // Hide previews
            $('.fps-image-preview, .fps-video-preview, .fps-images-section').hide();
            $('.fps-upload-placeholder').show();
            
            // Clear containers
            $('#fps-images-container').empty();
        },

        handleRemoveMedia: function(e) {
            e.preventDefault();
            
            var type = $(this).data('type');
            var index = $(this).data('index');
            
            console.log('[FPS] Admin: Remove media', type, index);
            
            if (type === 'image') {
                $('.fps-image-preview').hide();
                $('.fps-upload-placeholder').show();
                $('#fps-image-id').val('');
            } else if (type === 'video') {
                $('.fps-video-preview').hide();
                $('.fps-upload-placeholder').show();
                $('#fps-video-id').val('');
            } else if (type === 'carousel') {
                // Remove specific image from carousel
                $(this).closest('.fps-image-item').remove();
                this.updateCarouselIds();
            }
            
            this.handleRefreshPreview();
        },

        updateCarouselIds: function() {
            var imageIds = [];
            $('#fps-images-container .fps-image-item').each(function() {
                imageIds.push($(this).data('attachment-id'));
            });
            
            $('#fps-image-ids').val(JSON.stringify(imageIds));
            
            if (imageIds.length === 0) {
                $('.fps-images-section').hide();
            }
        },

        handleSettingsTab: function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            console.log('[FPS] Admin: Settings tab clicked', target);
            
            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Hide all tab contents
            $('.fps-tab-content').removeClass('active');
            
            // Show target tab content
            $(target).addClass('active');
        },

        handleEditPost: function(e) {
            e.preventDefault();
            
            var postId = $(this).data('post-id');
            console.log('[FPS] Admin: Edit post', postId);
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_edit_post',
                    post_id: postId,
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FPSAdmin.showEditModal(response.data.post);
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Admin: Edit post error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        handleDeletePost: function(e) {
            e.preventDefault();
            
            if (!confirm(fpsAdmin.strings.confirmDelete)) {
                return;
            }
            
            var postId = $(this).data('post-id');
            console.log('[FPS] Admin: Delete post', postId);
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_delete_post',
                    post_id: postId,
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FPSAdmin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Admin: Delete post error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        handleTestConnection: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Admin: Test connection');
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(fpsAdmin.strings.testing).prop('disabled', true);
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_test_connection',
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    $button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        FPSAdmin.showNotice(response.data.message, 'success');
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $button.text(originalText).prop('disabled', false);
                    console.error('[FPS] Admin: Test connection error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        handleDisconnectFacebook: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to disconnect your Facebook account?')) {
                return;
            }
            
            console.log('[FPS] Admin: Disconnect Facebook');
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_disconnect_facebook',
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FPSAdmin.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Admin: Disconnect Facebook error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        handleRefreshPages: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Admin: Refresh pages');
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Refreshing...').prop('disabled', true);
            
            $.ajax({
                url: fpsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_refresh_pages',
                    nonce: fpsAdmin.nonce
                },
                success: function(response) {
                    $button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        FPSAdmin.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        FPSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $button.text(originalText).prop('disabled', false);
                    console.error('[FPS] Admin: Refresh pages error', error);
                    FPSAdmin.showNotice(fpsAdmin.strings.error, 'error');
                }
            });
        },

        showEditModal: function(post) {
            // Implementation for edit modal
            console.log('[FPS] Admin: Show edit modal', post);
            // TODO: Implement edit modal
        },

        showLoading: function() {
            if ($('#fps-loading-overlay').length === 0) {
                $('body').append('<div id="fps-loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"><div class="bg-white rounded-lg p-6 text-center"><div class="fps-spinner mx-auto mb-4"></div><p>Processing...</p></div></div>');
            }
            $('#fps-loading-overlay').show();
        },

        hideLoading: function() {
            $('#fps-loading-overlay').hide();
        },

        showNotice: function(message, type) {
            // Remove existing notices
            $('.fps-notice').remove();
            
            var noticeClass = 'notice-' + type;
            var notice = $('<div class="notice fps-notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize ONLY when document is ready and ONLY on correct pages
    $(document).ready(function() {
        // Check if we're on the right page and have the right data
        if (typeof fpsAdmin !== 'undefined' && fpsAdmin.currentPage) {
            var currentPage = fpsAdmin.currentPage;
            
            // ONLY initialize on normal scheduling pages (NOT multi scheduler)
            if (currentPage.indexOf('fps-schedule-post') !== -1 && 
                currentPage.indexOf('fps-schedule-multi') === -1) {
                console.log('[FPS] Admin: Initializing on schedule post page');
                FPSAdmin.init();
            } else if (currentPage.indexOf('fps-scheduled-posts') !== -1 ||
                       currentPage.indexOf('fps-settings') !== -1 ||
                       currentPage.indexOf('fps-analytics') !== -1 ||
                       currentPage.indexOf('facebook-post-scheduler') !== -1) {
                console.log('[FPS] Admin: Initializing on admin page');
                FPSAdmin.init();
            }
        }
    });

})(jQuery);