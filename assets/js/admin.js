jQuery(document).ready(function($) {
    'use strict';
    
    console.log('MenuMatic: Admin JavaScript loading...');
    console.log('MenuMatic: Available objects:', {
        menumaticAdmin: typeof menumaticAdmin !== 'undefined' ? menumaticAdmin : 'undefined',
        menumatic_ajax: typeof menumatic_ajax !== 'undefined' ? menumatic_ajax : 'undefined'
    });
    
    // Check if required elements exist
    var $uploadArea = $('#menumatic-dropzone');
    var $fileInput = $('#menu_image');
    
    console.log('MenuMatic: Upload area found:', $uploadArea.length > 0);
    console.log('MenuMatic: File input found:', $fileInput.length > 0);
    
    if ($uploadArea.length === 0) {
        console.error('MenuMatic: Upload area not found! Check if we are on the upload page.');
        return;
    }
    
    if ($fileInput.length === 0) {
        console.error('MenuMatic: File input not found! Check HTML structure.');
        return;
    }
    
    // Only run upload functionality on upload page
    if ($('#menumatic-dropzone').length === 0) {
        return;
    }
    
    // Upload functionality
    var dropzone = $('#menumatic-dropzone');
    var fileInput = $('#menu_image');
    var imagePreview = $('#image-preview');
    var previewImage = $('#preview-image');
    var removeImageBtn = $('#remove-image');
    var uploadForm = $('#menumatic-upload-form');
    var processBtn = $('#process-menu-btn');
    var processingStatus = $('#processing-status');
    var processingResults = $('#processing-results');
    
    // Debug logging
    console.log('MenuMatic: Initializing upload functionality');
    console.log('MenuMatic: Dropzone found:', dropzone.length);
    console.log('MenuMatic: File input found:', fileInput.length);
    
    // Dropzone click handler
    dropzone.on('click', function(e) {
        console.log('MenuMatic: Dropzone clicked, target:', e.target.className);
        if (!$(e.target).hasClass('remove-image') && 
            !$(e.target).parent().hasClass('remove-image') &&
            !$(e.target).hasClass('button') &&
            !$(e.target).parent().hasClass('button')) {
            console.log('MenuMatic: Triggering file input click');
            if (fileInput.length > 0) {
                fileInput[0].click(); // Use native click method
            } else {
                console.error('MenuMatic: File input not found when trying to click from dropzone');
            }
        } else {
            console.log('MenuMatic: Dropzone click ignored (clicked on button or remove element)');
        }
    });
    
    // Select file button handler
    $('#select-file-btn').on('click', function(e) {
        e.preventDefault();
        console.log('MenuMatic: Select file button clicked');
        console.log('MenuMatic: File input element:', fileInput.length > 0 ? 'found' : 'not found');
        if (fileInput.length > 0) {
            fileInput[0].click();
        } else {
            console.error('MenuMatic: File input not found when trying to click');
        }
    });
    
    // File input change handler
    fileInput.on('change', function(e) {
        handleFileSelect(e.target.files[0]);
    });
    
    // Drag and drop handlers
    dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    
    dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    
    dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    // Remove image handler
    removeImageBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        fileInput.val('');
        imagePreview.hide();
        dropzone.find('.dropzone-content').show();
    });
    
    // Handle file selection
    function handleFileSelect(file) {
        if (!file) return;
        
        console.log('MenuMatic: File selected:', file.name, file.type, file.size);
        
        // Validate file type
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Please select a JPG, PNG, or WEBP image.');
            return;
        }
        
        // Show preview
        var reader = new FileReader();
        reader.onload = function(e) {
            previewImage.attr('src', e.target.result);
            dropzone.find('.dropzone-content').hide();
            imagePreview.show();
        };
        reader.readAsDataURL(file);
    }
    
    // Form submission
    uploadForm.on('submit', function(e) {
        e.preventDefault();
        
        if (!fileInput.val()) {
            alert('Please select an image file');
            return;
        }
        
        if (!$('#menu_title').val().trim()) {
            alert('Please enter a menu title');
            return;
        }
        
        processMenu();
    });
    
    // Process menu with AI
    function processMenu() {
        var formData = new FormData();
        formData.append('action', 'menumatic_upload_image');
        formData.append('nonce', menumatic_ajax.nonce);
        formData.append('menu_image', fileInput[0].files[0]);
        formData.append('menu_title', $('#menu_title').val());
        formData.append('menu_style', $('#menu_style').val());
        formData.append('extract_allergens', $('[name="extract_allergens"]').is(':checked') ? 1 : 0);
        formData.append('detect_dietary', $('[name="detect_dietary"]').is(':checked') ? 1 : 0);
        formData.append('preserve_formatting', $('[name="preserve_formatting"]').is(':checked') ? 1 : 0);
        
        console.log('MenuMatic: Form data prepared:');
        for (var pair of formData.entries()) {
            if (pair[0] === 'menu_image') {
                console.log('  menu_image:', pair[1].name, pair[1].size + ' bytes');
            } else {
                console.log('  ' + pair[0] + ':', pair[1]);
            }
        }
        
        // Show processing status
        uploadForm.hide();
        processingStatus.show();
        updateProgress(0, 'Uploading image...');
        
        // Upload and process
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 50; // 50% for upload
                        updateProgress(percentComplete, 'Uploading image...');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                console.log('MenuMatic: Upload response:', response);
                if (response.success) {
                    updateProgress(50, 'Processing with AI...');
                    processWithAI(response.data.file_path, response.data.upload_id);
                } else {
                    console.error('MenuMatic: Upload error:', response.data);
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('MenuMatic: Upload AJAX error:', {
                    status: status,
                    error: error,
                    xhr_status: xhr.status,
                    response: xhr.responseText
                });
                showError('Upload failed. Please try again. Error: ' + error);
            }
        });
    }
    
    // Process with AI
    function processWithAI(filePath, uploadId) {
        console.log('MenuMatic: Starting AI processing for file:', filePath, 'Upload ID:', uploadId);
        
        // Start the processing
        updateProgress(60, 'Analyzing menu image...');
        
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_process_menu',
                nonce: menumatic_ajax.nonce,
                file_path: filePath,
                upload_id: uploadId,
                menu_title: $('#menu_title').val(),
                menu_style: $('#menu_style').val(),
                extract_allergens: $('[name="extract_allergens"]').is(':checked') ? 1 : 0,
                detect_dietary: $('[name="detect_dietary"]').is(':checked') ? 1 : 0,
                preserve_formatting: $('[name="preserve_formatting"]').is(':checked') ? 1 : 0
            },
            timeout: 180000, // 3 minutes timeout for large menu processing
            success: function(response) {
                console.log('MenuMatic: AI processing response:', response);
                if (response.success) {
                    updateProgress(100, 'Processing complete!');
                    
                    // Show chunking info if available
                    if (response.data.chunking_info) {
                        var info = response.data.chunking_info;
                        var infoMsg = '';
                        
                        switch (info.processing_method) {
                            case 'chunked':
                                infoMsg = 'Large menu processed in sections! ';
                                if (info.chunks_processed) {
                                    infoMsg += 'Processed ' + info.chunks_processed + ' image sections. ';
                                }
                                break;
                            case 'enhanced':
                                infoMsg = 'Enhanced processing used for large menu! ';
                                break;
                            case 'standard':
                                infoMsg = 'Processing complete! ';
                                break;
                            default:
                                infoMsg = 'Menu processed successfully! ';
                        }
                        
                        if (info.validation_performed) {
                            infoMsg += 'Secondary validation completed. ';
                        }
                        if (info.items_found) {
                            infoMsg += 'Found ' + info.items_found + ' menu items total.';
                        }
                        updateProgress(100, infoMsg);
                    }
                    
                    setTimeout(function() {
                        showResults(response.data);
                    }, 2000);
                } else {
                    console.error('MenuMatic: AI processing error:', response.data);
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('MenuMatic: AI processing AJAX error:', {
                    status: status,
                    error: error,
                    xhr_status: xhr.status,
                    response: xhr.responseText
                });
                
                if (status === 'timeout') {
                    showError('Processing timed out. This can happen with very large menus. Please try again or contact support.');
                } else if (xhr.responseText && xhr.responseText.includes('memory')) {
                    showError('Memory limit reached while processing this large image. Please try reducing the image size/resolution or contact your hosting provider to increase PHP memory limits.');
                } else {
                    showError('AI processing failed. Please try again. Error: ' + error);
                }
            }
        });
        
        // Simulate progress for better UX
        var progressInterval = setInterval(function() {
            var currentProgress = parseInt($('#progress-text').text());
            if (currentProgress < 90) {
                var messages = [
                    'Analyzing menu structure...',
                    'Extracting text content...',
                    'Identifying menu items...',
                    'Processing sections...',
                    'Detecting dietary information...',
                    'Finalizing menu data...'
                ];
                var randomMessage = messages[Math.floor(Math.random() * messages.length)];
                updateProgress(currentProgress + Math.random() * 5, randomMessage);
            }
        }, 3000);
        
        // Clear interval when done
        setTimeout(function() {
            clearInterval(progressInterval);
        }, 60000);
    }
    
    // Update progress
    function updateProgress(percent, message) {
        $('#progress-fill').css('width', percent + '%');
        $('#progress-text').text(Math.round(percent) + '%');
        $('#processing-description').text(message);
    }
    
    // Show results
    function showResults(data) {
        processingStatus.hide();
        $('#menu-preview').html(data.html);
        $('#menu-shortcode').text('[menumatic_display name="' + (data.title || '').replace(/"/g, '\\"') + '"]');
        processingResults.show();
        
        // Store menu data for saving
        processingResults.data('menu-data', data);
    }
    
    // Show error
    function showError(message) {
        console.error('MenuMatic: Showing error to user:', message);
        processingStatus.hide();
        uploadForm.show();
        
        // Show error in a more user-friendly way
        var errorDiv = $('<div class="notice notice-error"><p><strong>Error:</strong> ' + message + '</p></div>');
        $('#menumatic-upload-form').prepend(errorDiv);
        
        // Also show alert for immediate attention
        alert('Error: ' + message);
        
        // Remove error message after 10 seconds
        setTimeout(function() {
            errorDiv.fadeOut(function() {
                errorDiv.remove();
            });
        }, 10000);
    }
    
    // Save menu button
    $(document).on('click', '#save-menu-btn', function() {
        var menuData = processingResults.data('menu-data');
        if (menuData) {
            window.location.href = menumatic_ajax.admin_url + 'admin.php?page=menumatic-manage';
        }
    });
    
    // Try again button
    $(document).on('click', '#try-again-btn', function() {
        processingResults.hide();
        uploadForm.show();
    });
    
    // Copy shortcode functionality
    $(document).on('click', '.copy-shortcode, #copy-shortcode', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode') || $(this).prev('code').text();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shortcode).then(function() {
                showNotice('Shortcode copied to clipboard!');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = shortcode;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotice('Shortcode copied to clipboard!');
        }
    });
    
    // Preview menu functionality
    $(document).on('click', '.preview-menu', function(e) {
        e.preventDefault();
        var menuId = $(this).data('id');
        
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_preview_menu',
                nonce: menumatic_ajax.nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (response.success) {
                    $('#menu-preview-content').html(response.data);
                    $('#menu-preview-modal').show();
                }
            }
        });
    });
    
    // Close modal
    $(document).on('click', '.close, .menumatic-modal', function(e) {
        if (e.target === this) {
            $('.menumatic-modal').hide();
        }
    });
    
    // Show notice
    function showNotice(message) {
        var notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').prepend(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 3000);
    }
    
    // Settings page API test (global, works on any page)
    $(document).on('click', '#test-api-connection', function() {
        var button = $(this);
        var result = $('#api-test-result');
        
        button.prop('disabled', true).text('Testing...');
        result.html('');
        
        $.post(menumatic_ajax.ajax_url, {
            action: 'menumatic_test_api',
            nonce: menumatic_ajax.nonce
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
            } else {
                result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text('Test Connection');
        });
    });

    // Copy shortcode
    $('.copy-shortcode').on('click', function(e) {
        e.preventDefault();
        const shortcode = $(this).data('shortcode');
        navigator.clipboard.writeText(shortcode).then(() => {
            $(this).text('Copied!');
            setTimeout(() => $(this).text('Copy'), 2000);
        });
    });

    // Handle Edit Menu form toggle
    $('.edit-menu').on('click', function(e) {
        e.preventDefault();
        const menuId = $(this).data('id');
        $('#edit-menu-row-' + menuId).toggle();
    });

    $('.cancel-edit').on('click', function(e) {
        e.preventDefault();
        const menuId = $(this).data('id');
        $('#edit-menu-row-' + menuId).hide();
    });

    // Handle Edit Menu form submission
    $('.edit-menu-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const spinner = form.find('.spinner');
        const submitButton = form.find('button[type="submit"]');

        spinner.addClass('is-active');
        submitButton.prop('disabled', true);

        const formData = new FormData(this);
        const menuId = form.data('id');
        
        formData.append('action', 'menumatic_update_menu');
        formData.append('nonce', menumatic_admin_vars.nonce);
        formData.append('menu_id', menuId);

        // AJAX call to be fully implemented later
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Update UI dynamically
                    alert('Menu updated successfully!');
                    location.reload(); // Simple reload for now
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            },
            complete: function() {
                spinner.removeClass('is-active');
                submitButton.prop('disabled', false);
            }
        });
    });
}); 
