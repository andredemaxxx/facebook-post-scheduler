/**
 * Facebook Post Scheduler - Multi Scheduler JavaScript
 * Handles MULTI-POST scheduling functionality ONLY
 * COMPLETELY SEPARATE from normal post scheduling
 */

(function($) {
    'use strict';

    // Prevent multiple initializations
    if (window.FPSMultiScheduler) {
        console.warn('[FPS] Multi: Already initialized, skipping');
        return;
    }

    // Multi scheduler object - ONLY for multi-post scheduling
    window.FPSMultiScheduler = {
        initialized: false,
        uploadedImages: [],
        uploadedTexts: [],
        pairedPosts: [],
        selectedTimeSlots: [],
        carouselGroups: {},
        manualTexts: {},
        
        init: function() {
            if (this.initialized) {
                console.warn('[FPS] Multi: Already initialized');
                return;
            }
            
            console.log('[FPS] Multi: Initializing multi scheduler');
            this.bindEvents();
            this.initDropzones();
            this.loadAvailableTimeSlots();
            this.initialized = true;
        },

        bindEvents: function() {
            // CRITICAL: Remove ALL existing event handlers to prevent recursion
            $(document).off('.fps-multi');
            
            // Upload mode change
            $(document).on('change.fps-multi', 'input[name="upload_mode"]', this.handleUploadModeChange.bind(this));
            
            // Process files button
            $(document).on('click.fps-multi', '#fps-process-files', this.handleProcessFiles.bind(this));
            
            // Clear files button
            $(document).on('click.fps-multi', '#fps-clear-files', this.handleClearFiles.bind(this));
            
            // Schedule date change
            $(document).on('change.fps-multi', '#fps-schedule-date', this.handleDateChange.bind(this));
            
            // Time slot selection
            $(document).on('click.fps-multi', '.fps-time-slot', this.handleTimeSlotClick.bind(this));
            
            // Schedule all posts
            $(document).on('click.fps-multi', '#fps-schedule-multi', this.handleScheduleMultiPosts.bind(this));
            
            // Manual text input
            $(document).on('input.fps-multi', '.fps-manual-text-input', 
                this.debounce(this.handleManualTextChange.bind(this), 500));
            
            // Carousel grouping
            $(document).on('click.fps-multi', '.fps-group-carousel', this.handleGroupCarousel.bind(this));
            $(document).on('click.fps-multi', '.fps-ungroup-carousel', this.handleUngroupCarousel.bind(this));
            
            // Remove file
            $(document).on('click.fps-multi', '.fps-remove-file', this.handleRemoveFile.bind(this));
            
            console.log('[FPS] Multi: Event handlers bound');
        },

        initDropzones: function() {
            var self = this;
            
            // CRITICAL: Remove existing handlers to prevent recursion
            $('.fps-dropzone').off('.fps-dropzone');
            
            // Image dropzone
            var $imageDropzone = $('#fps-image-dropzone');
            var $imageInput = $('#fps-upload-images');
            
            $imageDropzone.on('click.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[FPS] Multi: Image dropzone clicked');
                $imageInput.trigger('click');
            });
            
            $imageDropzone.on('dragover.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $imageDropzone.on('dragleave.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $imageDropzone.on('drop.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                self.handleImageFiles(files);
            });
            
            $imageInput.on('change.fps-dropzone', function() {
                var files = this.files;
                self.handleImageFiles(files);
            });
            
            // Text dropzone (only for filename and order modes)
            var $textDropzone = $('#fps-text-dropzone');
            var $textInput = $('#fps-upload-texts');
            
            $textDropzone.on('click.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[FPS] Multi: Text dropzone clicked');
                $textInput.trigger('click');
            });
            
            $textDropzone.on('dragover.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $textDropzone.on('dragleave.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $textDropzone.on('drop.fps-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                self.handleTextFiles(files);
            });
            
            $textInput.on('change.fps-dropzone', function() {
                var files = this.files;
                self.handleTextFiles(files);
            });
            
            console.log('[FPS] Multi: Dropzones initialized');
        },

        handleImageFiles: function(files) {
            console.log('[FPS] Multi: Processing image files', files.length);
            
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                
                if (!this.validateImageFile(file)) {
                    continue;
                }
                
                this.addImageFile(file);
            }
            
            this.updateImagesList();
        },

        handleTextFiles: function(files) {
            console.log('[FPS] Multi: Processing text files', files.length);
            
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                
                if (!this.validateTextFile(file)) {
                    continue;
                }
                
                this.addTextFile(file);
            }
            
            this.updateTextsList();
        },

        validateImageFile: function(file) {
            var allowedTypes = fpsMulti.settings.allowedImageTypes;
            var maxSize = fpsMulti.settings.maxImageSize;
            
            if (allowedTypes.indexOf(file.type) === -1) {
                this.showNotice(fpsMulti.strings.invalidFileType + ': ' + file.name, 'error');
                return false;
            }
            
            if (file.size > maxSize) {
                this.showNotice(fpsMulti.strings.fileTooLarge + ': ' + file.name, 'error');
                return false;
            }
            
            return true;
        },

        validateTextFile: function(file) {
            var allowedTypes = fpsMulti.settings.allowedTextTypes;
            var maxSize = fpsMulti.settings.maxTextSize;
            
            if (allowedTypes.indexOf(file.type) === -1) {
                this.showNotice(fpsMulti.strings.invalidFileType + ': ' + file.name, 'error');
                return false;
            }
            
            if (file.size > maxSize) {
                this.showNotice(fpsMulti.strings.fileTooLarge + ': ' + file.name, 'error');
                return false;
            }
            
            return true;
        },

        addImageFile: function(file) {
            var reader = new FileReader();
            var self = this;
            
            reader.onload = function(e) {
                var imageData = {
                    file: file,
                    name: file.name,
                    originalName: file.name.replace(/\.[^/.]+$/, ""),
                    numericPart: self.extractNumericPart(file.name),
                    url: e.target.result,
                    size: file.size,
                    type: 'image',
                    id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
                };
                
                self.uploadedImages.push(imageData);
                self.updateImagesList();
            };
            
            reader.readAsDataURL(file);
        },

        addTextFile: function(file) {
            var reader = new FileReader();
            var self = this;
            
            reader.onload = function(e) {
                var textData = {
                    file: file,
                    name: file.name,
                    originalName: file.name.replace(/\.[^/.]+$/, ""),
                    numericPart: self.extractNumericPart(file.name),
                    content: e.target.result,
                    size: file.size,
                    type: 'text',
                    id: 'txt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
                };
                
                self.uploadedTexts.push(textData);
                self.updateTextsList();
            };
            
            reader.readAsText(file);
        },

        extractNumericPart: function(filename) {
            // Extract numeric part for pairing (e.g., "image1a.jpg" -> "1")
            var match = filename.match(/(\d+)/);
            return match ? match[1] : null;
        },

        updateImagesList: function() {
            var $list = $('#fps-images-list');
            $list.empty();
            
            var self = this;
            
            this.uploadedImages.forEach(function(image, index) {
                var $item = $('<div class="fps-file-item fps-image-file" data-id="' + image.id + '" data-index="' + index + '">');
                
                $item.html(`
                    <div class="flex items-center space-x-3 p-3 bg-white border border-gray-200 rounded-lg hover:shadow-sm transition-shadow">
                        <img src="${image.url}" class="w-16 h-16 object-cover rounded">
                        <div class="flex-1">
                            <div class="font-medium text-sm text-gray-900">${image.name}</div>
                            <div class="text-xs text-gray-500">${self.formatFileSize(image.size)}</div>
                            ${image.numericPart ? '<div class="text-xs text-blue-600">Numeric: ' + image.numericPart + '</div>' : ''}
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="fps-group-carousel text-blue-600 hover:text-blue-800 p-1" data-id="${image.id}" title="${fpsMulti.strings.groupImages}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </button>
                            <button class="fps-remove-file text-red-600 hover:text-red-800 p-1" data-id="${image.id}" data-type="image">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `);
                
                $list.append($item);
            });
            
            this.updateManualTextInputs();
        },

        updateTextsList: function() {
            var $list = $('#fps-texts-list');
            $list.empty();
            
            var self = this;
            
            this.uploadedTexts.forEach(function(text, index) {
                var $item = $('<div class="fps-file-item fps-text-file" data-id="' + text.id + '" data-index="' + index + '">');
                
                $item.html(`
                    <div class="flex items-center space-x-3 p-3 bg-white border border-gray-200 rounded-lg hover:shadow-sm transition-shadow">
                        <div class="w-16 h-16 bg-yellow-100 rounded flex items-center justify-center">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm text-gray-900">${text.name}</div>
                            <div class="text-xs text-gray-500">${self.formatFileSize(text.size)}</div>
                            ${text.numericPart ? '<div class="text-xs text-blue-600">Numeric: ' + text.numericPart + '</div>' : ''}
                        </div>
                        <button class="fps-remove-file text-red-600 hover:text-red-800 p-1" data-id="${text.id}" data-type="text">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                `);
                
                $list.append($item);
            });
        },

        handleUploadModeChange: function() {
            var mode = $('input[name="upload_mode"]:checked').val();
            console.log('[FPS] Multi: Upload mode changed to', mode);
            
            // Show/hide sections based on mode
            if (mode === 'manual') {
                $('#fps-text-upload-section').hide();
                $('#fps-manual-text-section').show();
            } else {
                $('#fps-text-upload-section').show();
                $('#fps-manual-text-section').hide();
            }
            
            this.updateManualTextInputs();
        },

        updateManualTextInputs: function() {
            var mode = $('input[name="upload_mode"]:checked').val();
            
            if (mode === 'manual') {
                var $container = $('#fps-manual-texts');
                $container.empty();
                
                var self = this;
                
                this.uploadedImages.forEach(function(image, index) {
                    var $inputGroup = $('<div class="fps-manual-input-group space-y-2 p-4 bg-gray-50 rounded-lg">');
                    
                    $inputGroup.html(`
                        <div class="flex items-center space-x-3">
                            <img src="${image.url}" class="w-12 h-12 object-cover rounded">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">Text for ${image.name}</label>
                                <textarea class="fps-manual-text-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mt-1" 
                                          data-index="${index}" 
                                          rows="3" 
                                          placeholder="Enter text for this image...">${self.manualTexts[index] || ''}</textarea>
                            </div>
                        </div>
                    `);
                    
                    $container.append($inputGroup);
                });
            }
        },

        handleManualTextChange: function() {
            var index = $(this).data('index');
            var text = $(this).val();
            
            this.manualTexts[index] = text;
            console.log('[FPS] Multi: Manual text updated for index', index);
        },

        handleProcessFiles: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Multi: Process files clicked');
            
            var pageId = $('#fps-multi-page-id').val();
            var uploadMode = $('input[name="upload_mode"]:checked').val();
            
            if (!pageId) {
                this.showNotice(fpsMulti.strings.selectPage, 'error');
                return;
            }
            
            if (this.uploadedImages.length === 0) {
                this.showNotice(fpsMulti.strings.noFilesSelected, 'error');
                return;
            }
            
            this.showLoading(fpsMulti.strings.processFiles);
            this.pairFiles(uploadMode);
        },

        pairFiles: function(mode) {
            console.log('[FPS] Multi: Pairing files with mode', mode);
            
            this.pairedPosts = [];
            
            try {
                if (mode === 'filename') {
                    this.pairByNumericPart();
                } else if (mode === 'order') {
                    this.pairByOrder();
                } else if (mode === 'manual') {
                    this.pairWithManualText();
                }
                
                this.updatePreview();
                this.hideLoading();
                this.showNotice(fpsMulti.strings.filesProcessed, 'success');
                
            } catch (error) {
                console.error('[FPS] Multi: Error pairing files', error);
                this.hideLoading();
                this.showNotice(fpsMulti.strings.error, 'error');
            }
        },

        pairByNumericPart: function() {
            // Group images by numeric part (e.g., image1a.jpg, image1b.jpg -> group "1")
            var imageGroups = {};
            var textGroups = {};
            
            // Group images by numeric part
            this.uploadedImages.forEach(function(image) {
                var numericPart = image.numericPart || 'no_number';
                if (!imageGroups[numericPart]) {
                    imageGroups[numericPart] = [];
                }
                imageGroups[numericPart].push(image);
            });
            
            // Group texts by numeric part
            this.uploadedTexts.forEach(function(text) {
                var numericPart = text.numericPart || 'no_number';
                textGroups[numericPart] = text;
            });
            
            // Create pairs
            Object.keys(imageGroups).forEach(function(numericPart) {
                var images = imageGroups[numericPart];
                var text = textGroups[numericPart];
                
                if (text) {
                    var postType = images.length > 1 ? 'carousel' : 'single';
                    
                    FPSMultiScheduler.pairedPosts.push({
                        type: postType,
                        images: images,
                        text: text,
                        content: text.content,
                        pairMethod: 'filename',
                        numericPart: numericPart
                    });
                }
            });
        },

        pairByOrder: function() {
            var maxPairs = Math.min(this.uploadedImages.length, this.uploadedTexts.length);
            
            for (var i = 0; i < maxPairs; i++) {
                this.pairedPosts.push({
                    type: 'single',
                    images: [this.uploadedImages[i]],
                    text: this.uploadedTexts[i],
                    content: this.uploadedTexts[i].content,
                    pairMethod: 'order'
                });
            }
        },

        pairWithManualText: function() {
            var self = this;
            
            this.uploadedImages.forEach(function(image, index) {
                var manualText = self.manualTexts[index] || '';
                
                if (manualText.trim()) {
                    self.pairedPosts.push({
                        type: 'single',
                        images: [image],
                        text: null,
                        content: manualText,
                        pairMethod: 'manual'
                    });
                }
            });
        },

        updatePreview: function() {
            var $container = $('#fps-preview-list');
            $container.empty();
            
            if (this.pairedPosts.length === 0) {
                $container.html('<div class="text-center py-8 text-gray-500"><p>No paired posts to preview</p></div>');
                $('#fps-schedule-multi').prop('disabled', true);
                return;
            }
            
            var self = this;
            
            this.pairedPosts.forEach(function(pair, index) {
                var $preview = $('<div class="fps-post-preview-item bg-white border border-gray-200 rounded-lg p-4 space-y-3">');
                
                var imagesHtml = '';
                if (pair.type === 'carousel') {
                    imagesHtml = '<div class="flex space-x-2 overflow-x-auto">';
                    pair.images.forEach(function(img) {
                        imagesHtml += '<img src="' + img.url + '" class="w-20 h-20 object-cover rounded flex-shrink-0">';
                    });
                    imagesHtml += '</div>';
                    imagesHtml += '<div class="text-xs text-blue-600 font-medium">📷 Carousel (' + pair.images.length + ' images)</div>';
                } else {
                    imagesHtml = '<img src="' + pair.images[0].url + '" class="w-20 h-20 object-cover rounded">';
                }
                
                $preview.html(`
                    <div class="flex items-start space-x-3">
                        ${imagesHtml}
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900">Post ${index + 1}</h4>
                            <p class="text-sm text-gray-600 mt-1">${pair.content.substring(0, 100)}${pair.content.length > 100 ? '...' : ''}</p>
                            <div class="text-xs text-gray-500 mt-2">
                                Method: ${pair.pairMethod}${pair.numericPart ? ' (Group: ' + pair.numericPart + ')' : ''}
                            </div>
                        </div>
                    </div>
                `);
                
                $container.append($preview);
            });
            
            // Enable schedule button
            $('#fps-schedule-multi').prop('disabled', false);
        },

        handleDateChange: function() {
            var selectedDate = $(this).val();
            console.log('[FPS] Multi: Date changed to', selectedDate);
            
            this.loadAvailableTimeSlots(selectedDate);
        },

        loadAvailableTimeSlots: function(date) {
            var self = this;
            
            if (!date) {
                date = $('#fps-schedule-date').val() || new Date().toISOString().split('T')[0];
            }
            
            $.ajax({
                url: fpsMulti.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_get_available_time_slots',
                    date: date,
                    nonce: fpsMulti.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayTimeSlots(response.data.available_slots, date);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[FPS] Multi: Load time slots error', error);
                }
            });
        },

        displayTimeSlots: function(availableSlots, date) {
            var $container = $('#fps-available-slots');
            $container.empty();
            
            if (availableSlots.length === 0) {
                $container.html('<div class="col-span-full text-center py-8 text-gray-500"><p>No available time slots for this date</p><p class="text-sm">Create recurring times in Calendar Post first</p></div>');
                return;
            }
            
            var self = this;
            
            availableSlots.forEach(function(slot) {
                var $slot = $('<div class="fps-time-slot available px-3 py-2 text-sm border border-gray-300 rounded-lg text-center cursor-pointer hover:bg-blue-50 hover:border-blue-300" data-time="' + slot + '">' + slot + '</div>');
                $container.append($slot);
            });
        },

        handleTimeSlotClick: function(e) {
            e.preventDefault();
            
            var $slot = $(this);
            var time = $slot.data('time');
            
            if ($slot.hasClass('occupied')) {
                return;
            }
            
            if ($slot.hasClass('selected')) {
                $slot.removeClass('selected bg-blue-600 border-blue-600 text-white').addClass('available');
                this.selectedTimeSlots = this.selectedTimeSlots.filter(function(t) {
                    return t !== time;
                });
            } else {
                $slot.removeClass('available').addClass('selected bg-blue-600 border-blue-600 text-white');
                this.selectedTimeSlots.push(time);
            }
            
            console.log('[FPS] Multi: Selected time slots', this.selectedTimeSlots);
            this.updateScheduleButton();
        },

        updateScheduleButton: function() {
            var canSchedule = this.pairedPosts.length > 0 && 
                             this.selectedTimeSlots.length >= this.pairedPosts.length;
            
            $('#fps-schedule-multi').prop('disabled', !canSchedule);
            
            if (canSchedule) {
                $('#fps-schedule-info').text(`Ready to schedule ${this.pairedPosts.length} posts`);
            } else if (this.pairedPosts.length === 0) {
                $('#fps-schedule-info').text('Process files first');
            } else {
                $('#fps-schedule-info').text(`Select ${this.pairedPosts.length - this.selectedTimeSlots.length} more time slots`);
            }
        },

        handleScheduleMultiPosts: function(e) {
            e.preventDefault();
            
            console.log('[FPS] Multi: Schedule multi posts');
            
            var pageId = $('#fps-multi-page-id').val();
            var selectedDate = $('#fps-schedule-date').val();
            var shareToStory = $('#fps-share-to-story').is(':checked');
            
            if (!pageId) {
                this.showNotice(fpsMulti.strings.selectPage, 'error');
                return;
            }
            
            if (this.pairedPosts.length === 0) {
                this.showNotice('No posts to schedule', 'error');
                return;
            }
            
            if (this.selectedTimeSlots.length < this.pairedPosts.length) {
                this.showNotice('Please select enough time slots for all posts', 'error');
                return;
            }
            
            this.showLoading(fpsMulti.strings.schedulingPosts);
            
            var self = this;
            
            $.ajax({
                url: fpsMulti.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fps_schedule_multi_posts',
                    page_id: pageId,
                    pairs: this.pairedPosts,
                    selected_date: selectedDate,
                    time_slots: this.selectedTimeSlots,
                    share_to_story: shareToStory,
                    nonce: fpsMulti.nonce
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        
                        // Reset form after successful scheduling
                        setTimeout(function() {
                            self.handleClearFiles();
                        }, 2000);
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.hideLoading();
                    console.error('[FPS] Multi: Schedule posts error', error);
                    self.showNotice(fpsMulti.strings.error, 'error');
                }
            });
        },

        handleClearFiles: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            if (this.uploadedImages.length > 0 || this.uploadedTexts.length > 0) {
                if (!confirm(fpsMulti.strings.confirmClear)) {
                    return;
                }
            }
            
            console.log('[FPS] Multi: Clear files');
            
            // Reset all data
            this.uploadedImages = [];
            this.uploadedTexts = [];
            this.pairedPosts = [];
            this.selectedTimeSlots = [];
            this.manualTexts = {};
            this.carouselGroups = {};
            
            // Clear UI
            $('#fps-images-list').empty();
            $('#fps-texts-list').empty();
            $('#fps-manual-texts').empty();
            $('#fps-preview-list').html('<div class="text-center py-8 text-gray-500"><p>Preview will appear here after processing files</p></div>');
            
            // Reset form inputs
            $('#fps-upload-images').val('');
            $('#fps-upload-texts').val('');
            
            // Reset buttons
            $('#fps-schedule-multi').prop('disabled', true);
            $('#fps-schedule-info').text('Upload files to begin');
            
            // Clear selected time slots
            $('.fps-time-slot.selected').removeClass('selected bg-blue-600 border-blue-600 text-white').addClass('available');
        },

        handleGroupCarousel: function(e) {
            e.preventDefault();
            
            var imageId = $(this).data('id');
            console.log('[FPS] Multi: Group carousel for image', imageId);
            
            // TODO: Implement carousel grouping interface
            this.showNotice('Carousel grouping feature coming soon', 'info');
        },

        handleUngroupCarousel: function(e) {
            e.preventDefault();
            
            var imageId = $(this).data('id');
            console.log('[FPS] Multi: Ungroup carousel for image', imageId);
            
            // TODO: Implement carousel ungrouping
        },

        handleRemoveFile: function(e) {
            e.preventDefault();
            
            var fileId = $(this).data('id');
            var fileType = $(this).data('type');
            
            console.log('[FPS] Multi: Remove file', fileId, fileType);
            
            if (fileType === 'image') {
                this.uploadedImages = this.uploadedImages.filter(function(img) {
                    return img.id !== fileId;
                });
                this.updateImagesList();
            } else if (fileType === 'text') {
                this.uploadedTexts = this.uploadedTexts.filter(function(txt) {
                    return txt.id !== fileId;
                });
                this.updateTextsList();
            }
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        showLoading: function(message) {
            if ($('#fps-multi-loading').length === 0) {
                $('body').append('<div id="fps-multi-loading" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"><div class="bg-white rounded-lg p-6 text-center"><div class="fps-spinner mx-auto mb-4"></div><p class="text-gray-700">' + (message || 'Loading...') + '</p></div></div>');
            }
            $('#fps-multi-loading').show();
        },

        hideLoading: function() {
            $('#fps-multi-loading').hide();
        },

        showNotice: function(message, type) {
            // Remove existing notices
            $('.fps-multi-notice').remove();
            
            var noticeClass = 'notice-' + type;
            var notice = $('<div class="notice fps-multi-notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
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

    // Initialize ONLY when document is ready and ONLY on multi scheduler page
    $(document).ready(function() {
        // Check if we're on the multi scheduler page specifically
        if (typeof fpsMulti !== 'undefined' && fpsMulti.currentPage && 
            fpsMulti.currentPage.indexOf('fps-schedule-multi') !== -1) {
            console.log('[FPS] Multi: Initializing on multi scheduler page');
            FPSMultiScheduler.init();
        }
    });

})(jQuery);