jQuery(document).ready(function($) {
    'use strict';

    const MENU_LAYOUT_OPTIONS = {
        columns: ['auto', '1', '2', '3'],
        price_position: ['inline', 'top-right', 'below-description'],
        text_layout: ['stacked', 'split', 'compact']
    };
    
    // Initialize menu functionality
    initMenuFeatures();
    
    function initMenuFeatures() {
        // Add smooth scrolling for menu sections
        addSmoothScrolling();
        

        
        // Add print functionality
        addPrintButton();
        
        // Add accessibility features
        enhanceAccessibility();
        
        // Add mobile menu toggle for long menus
        addMobileMenuToggle();
    }
    
    // Smooth scrolling for menu navigation
    function addSmoothScrolling() {
        $('.menumatic-menu').each(function() {
            var $menu = $(this);
            var sections = $menu.find('.menu-section');
            
            if (sections.length > 3) {
                // Create navigation for menus with multiple sections
                var $nav = $('<nav class="menu-navigation"><ul></ul></nav>');
                
                sections.each(function(index) {
                    var $section = $(this);
                    var sectionTitle = $section.find('h2').text();
                    var sectionId = 'menu-section-' + index;
                    
                    $section.attr('id', sectionId);
                    $nav.find('ul').append(
                        '<li><a href="#' + sectionId + '">' + sectionTitle + '</a></li>'
                    );
                });
                
                $menu.prepend($nav);
            }
        });
        
        // Smooth scroll behavior
        $(document).on('click', '.menu-navigation a', function(e) {
            e.preventDefault();
            var target = $(this.getAttribute('href'));
            
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 20
                }, 500);
            }
        });
    }
    

    
    // Add print button
    function addPrintButton() {
        $('.menumatic-menu').each(function() {
            var $menu = $(this);
            var $printButton = $('<button class="menu-print-btn" title="Print Menu">' +
                '<span class="dashicons dashicons-printer"></span> Print Menu' +
                '</button>');
            
            $menu.prepend($printButton);
            
            $printButton.on('click', function() {
                // Create print-friendly version
                var printContent = $menu.clone();
                printContent.find('.menu-navigation, .menu-print-btn').remove();
                
                var printWindow = window.open('', '_blank');
                printWindow.document.write(
                    '<html><head><title>Menu</title>' +
                    '<style>' + getPrintStyles() + '</style>' +
                    '</head><body>' +
                    printContent[0].outerHTML +
                    '</body></html>'
                );
                printWindow.document.close();
                printWindow.print();
            });
        });
    }
    
    // Get print-specific styles
    function getPrintStyles() {
        return `
            body { font-family: Arial, sans-serif; margin: 20px; }
            .menumatic-menu { max-width: none; }
            .menu-container { box-shadow: none; border: 1px solid #000; }
            .menu-section h2 { color: #000; border-bottom: 2px solid #000; }
            .menu-item { border-bottom: 1px solid #ccc; }
            .item-name { color: #000; font-weight: bold; }
            .item-price { color: #000; font-weight: bold; }
            .dietary-indicator { border: 1px solid #000; background: #fff; color: #000; }
        `;
    }
    
    // Enhance accessibility
    function enhanceAccessibility() {
        $('.menumatic-menu').each(function() {
            var $menu = $(this);
            
            // Add ARIA labels
            $menu.attr('role', 'region');
            $menu.attr('aria-label', 'Restaurant Menu');
            
            // Add proper heading hierarchy
            $menu.find('.menu-section').each(function() {
                $(this).attr('role', 'region');
                $(this).attr('aria-labelledby', $(this).find('h2').attr('id', 'section-' + Math.random().toString(36).substr(2, 9)).attr('id'));
            });
            
            // Add keyboard navigation
            $menu.find('.menu-item').attr('tabindex', '0');
            
            // Add focus styles
            $menu.find('.menu-item').on('focus', function() {
                $(this).addClass('focused');
            }).on('blur', function() {
                $(this).removeClass('focused');
            });
        });
    }
    
    // Add mobile menu toggle for long menus
    function addMobileMenuToggle() {
        if ($(window).width() <= 768) {
            $('.menumatic-menu').each(function() {
                var $menu = $(this);
                var sections = $menu.find('.menu-section');
                
                if (sections.length > 3) {
                    var $toggleButton = $('<button class="mobile-menu-toggle">Show All Sections</button>');
                    $menu.prepend($toggleButton);
                    
                    // Initially hide sections beyond the first 2
                    sections.slice(2).hide();
                    
                    $toggleButton.on('click', function() {
                        var hiddenSections = sections.slice(2).filter(':hidden');
                        
                        if (hiddenSections.length > 0) {
                            hiddenSections.slideDown();
                            $(this).text('Show Less');
                        } else {
                            sections.slice(2).slideUp();
                            $(this).text('Show All Sections');
                        }
                    });
                }
            });
        }
    }
    
    // Responsive adjustments
    $(window).on('resize', function() {
        // Reinitialize mobile features on resize
        $('.mobile-menu-toggle').remove();
        addMobileMenuToggle();
    });
    
    // Add smooth animations
    $('.menu-item').hover(
        function() {
            $(this).css('transform', 'scale(1.05)');
        },
        function() {
            $(this).css('transform', 'scale(1)');
        }
    );
    
    // Initialize frontend management if present
    if ($('.menumatic-frontend-manager').length) {
        initFrontendManager();
    }
    
    function initFrontendManager() {
        const $manager = $('.menumatic-frontend-manager');
        const nonce = $manager.data('nonce') || menumatic_ajax.nonce;
        
        initializeMenuPills();

        // Handle menu selection dropdown
        $('#frontend-menu-select').on('change', function() {
            const $titleGroup = $('#new-menu-title-group');
            const $updateModeGroup = $('#frontend-update-mode-group');
            const $titleInput = $('#frontend-menu-title');
            const $submitBtn = $('.menumatic-btn-primary .btn-text');
            const $styleSelect = $('#frontend-menu-style');
            const selectedStyleId = $(this).find('option:selected').data('style-id');
            
            if ($(this).val() === 'new') {
                $titleGroup.show();
                $updateModeGroup.hide();
                $titleInput.prop('required', true);
                $submitBtn.text('Create Menu');
                if ($styleSelect.data('default-style-id')) {
                    $styleSelect.val($styleSelect.data('default-style-id'));
                }
            } else if ($(this).val()) {
                $titleGroup.hide();
                $updateModeGroup.show();
                $titleInput.prop('required', false);
                $submitBtn.text('Update Menu');
                if (selectedStyleId) {
                    $styleSelect.val(selectedStyleId);
                }
            } else {
                $titleGroup.hide();
                $updateModeGroup.hide();
                $titleInput.prop('required', false);
                $submitBtn.text('Update Menu');
            }
        });
        
        // Upload form handling
        $('#menumatic-frontend-upload-form').on('submit', function(e) {
            e.preventDefault();
            handleFrontendUpload(this, nonce);
        });
        
        // Preview button
        $(document).on('click', '.preview-menu', function() {
            const menuId = $(this).data('menu-id');
            showMenuPreview(menuId);
        });
        
        // Delete button
        $(document).on('click', '.delete-menu', function() {
            const menuId = $(this).data('menu-id');
            const $card = $(this).closest('.menu-card');
            const menuTitle = $card.find('h5').text();
            
            if (confirm('Are you sure you want to delete "' + menuTitle + '"? This action cannot be undone.')) {
                handleMenuDelete(menuId, $card, nonce);
            }
        });
        
        // Regenerate button
        $(document).on('click', '.regenerate-menu', function() {
            const menuId = $(this).data('menu-id');
            const $card = $(this).closest('.menu-card');
            handleMenuRegenerate(menuId, $card, nonce);
        });

        // Modal close
        $(document).on('click', '.modal-close, .modal-overlay', function() {
            $('#menumatic-preview-modal').hide();
        });
        
        // Prevent modal close when clicking inside content
        $(document).on('click', '.modal-content', function(e) {
            e.stopPropagation();
        });

        $(document).on('change', '.menu-style-select', function() {
            const menuId = $(this).data('menu-id');
            hydrateStyleEditorFromDefinition(menuId, $(this).val());
            applyStylePreview(menuId);
            markAsChanged();
        });

        $(document).on('change', '.menu-layout-control', function() {
            const menuId = $(this).data('menu-id');
            applyStylePreview(menuId);
            markAsChanged();
        });

        $(document).on('input', '.menu-custom-css', function() {
            const menuId = $(this).data('menu-id');
            applyStylePreview(menuId);
            markAsChanged();
        });

        $(document).on('click', '.save-style-only', function() {
            const menuId = $(this).data('menu-id');
            saveMenuStyleOnly(menuId, $(this));
        });

        $(document).on('click', '.menu-style-tab', function() {
            const menuId = $(this).data('menu-id');
            const targetTab = $(this).data('tab');
            const $editor = $('.menu-style-editor[data-menu-id="' + menuId + '"]');

            $editor.find('.menu-style-tab').removeClass('is-active');
            $editor.find('.menu-style-panel').removeClass('is-active');
            $(this).addClass('is-active');
            $editor.find('.menu-style-panel[data-panel="' + targetTab + '"]').addClass('is-active');
        });
    }

    function initializeMenuPills() {
        $(document).on('click', '.menu-pill', function() {
            const menuId = $(this).data('menu-id');
            const isActive = $(this).hasClass('is-active');

            $('.menu-pill').removeClass('is-active');

            if (isActive) {
                $('.menu-card.is-active').removeClass('is-active').stop(true, true).slideUp(260);
                return;
            }

            $(this).addClass('is-active');
            openMenuPanel(menuId);
        });
    }

    function openMenuPanel(menuId) {
        const $target = $('.menu-card[data-menu-id="' + menuId + '"]');
        const $activePanels = $('.menu-card.is-active').not($target);

        $activePanels.removeClass('is-active').stop(true, true).slideUp(220);

        $target.addClass('is-active').stop(true, true).slideDown(280, function() {
            $('html, body').animate({
                scrollTop: $target.offset().top - 24
            }, 250);
        });

        applyStylePreview(menuId);
    }
    
    function handleFrontendUpload(form, nonce) {
        const $form = $(form);
        const $submitBtn = $form.find('button[type="submit"]');
        const $btnText = $submitBtn.find('.btn-text');
        const $btnLoading = $submitBtn.find('.btn-loading');
        const $progress = $('#frontend-upload-progress');
        
        // Validate form
        const menuId = $form.find('#frontend-menu-select').val();
        const title = $form.find('#frontend-menu-title').val().trim();
        const file = $form.find('#frontend-menu-image')[0].files[0];
        
        if (!menuId) {
            showMessage('Please select a menu to update.', 'error');
            return;
        }
        
        if (menuId === 'new' && !title) {
            showMessage('Please enter a title for the new menu.', 'error');
            return;
        }
        
        if (!file) {
            showMessage('Please select an image file.', 'error');
            return;
        }
        
        // Check file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
            showMessage('File size must be less than 10MB.', 'error');
            return;
        }
        
        // Show loading state
        $submitBtn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        $progress.show();
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'menumatic_frontend_upload');
        formData.append('nonce', nonce);
        formData.append('menu_id', menuId);
        if (menuId === 'new') {
            formData.append('menu_title', title);
        }
        formData.append('menu_image', file);
        formData.append('menu_style', $form.find('#frontend-menu-style').val());
        formData.append('update_mode', $form.find('#frontend-update-mode').val() || 'overwrite');
        
        // Submit via AJAX
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message || 'Menu processed successfully!', 'success');
                    
                    if (response.data.is_new) {
                        // Reset form and refresh for new menu
                        $form[0].reset();
                        $('#new-menu-title-group').hide();
                        $('#frontend-update-mode-group').hide();
                        $btnText.text('Update Menu');
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        // Update existing menu card
                        replaceRenderedMenuInstances(response.data.menu_id, response.data.html);
                        
                        // Reset form
                        $form[0].reset();
                        $('#frontend-menu-select').val('');
                        $('#new-menu-title-group').hide();
                        $('#frontend-update-mode-group').hide();
                    }
                } else {
                    showMessage(response.data || 'Upload failed. Please try again.', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please check your connection and try again.', 'error');
            },
            complete: function() {
                // Reset loading state
                $submitBtn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
                $progress.hide();
            }
        });
    }
    
    function handleMenuDelete(menuId, $card, nonce) {
        const $deleteBtn = $card.find('.delete-menu');
        const originalText = $deleteBtn.text();
        
        $deleteBtn.text('Deleting...').prop('disabled', true);
        
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_frontend_delete',
                nonce: nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (response.success) {
                    $('.menumatic-menu[data-menu-id="' + menuId + '"]').each(function() {
                        $(this).prev('style.menumatic-custom-style').remove();
                        $(this).remove();
                    });

                    const $pill = $('.menu-pill[data-menu-id="' + menuId + '"]');
                    $pill.remove();

                    $card.fadeOut(400, function() {
                        $(this).remove();
                        
                        // Check if no menus left
                        if ($('.menu-card').length === 0) {
                            $('.menu-panels').html('<p class="no-menus">No menus found. Upload your first menu above!</p>');
                            $('.menu-pill-list').remove();
                        } else if ($pill.hasClass('is-active')) {
                            const $nextPill = $('.menu-pill').first();
                            if ($nextPill.length) {
                                $nextPill.trigger('click');
                            }
                        }
                    });
                    showMessage('Menu deleted successfully.', 'success');
                } else {
                    showMessage(response.data || 'Failed to delete menu.', 'error');
                    $deleteBtn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
                $deleteBtn.text(originalText).prop('disabled', false);
            }
        });
    }
    
    function handleMenuRegenerate(menuId, $card, nonce) {
        const $regenerateBtn = $card.find('.regenerate-menu');
        const originalText = $regenerateBtn.text();
        
        $regenerateBtn.text('Regenerating...').prop('disabled', true);
        
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_frontend_regenerate',
                nonce: nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (response.success) {
                    replaceRenderedMenuInstances(menuId, response.data.html);
                    showMessage(response.data.message || 'Menu regenerated successfully!', 'success');
                } else {
                    showMessage(response.data || 'Failed to regenerate menu.', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $regenerateBtn.text(originalText).prop('disabled', false);
            }
        });
    }
    
    function showMenuPreview(menuId) {
        const $modal = $('#menumatic-preview-modal');
        const $content = $('#preview-content');
        
        // Show modal with loading
        $content.html('<p style="text-align: center; padding: 40px;">Loading preview...</p>');
        $modal.show();
        
        // Get the menu content from the card
        const $card = $('.menu-card[data-menu-id="' + menuId + '"]');
        const menuTitle = $card.find('h5').text();
        const menuContent = $card.find('.menu-card-preview').html();
        
        // Update modal
        $modal.find('.modal-header h4').text(menuTitle);
        $content.html(menuContent);
    }
    
    function showMessage(message, type) {
        const $manager = $('.menumatic-frontend-manager');
        const $existing = $manager.find('.frontend-message');
        
        // Remove existing messages
        $existing.remove();
        
        // Add new message
        const $message = $('<div class="frontend-message ' + type + '">' + message + '</div>');
        $manager.prepend($message);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $message.fadeOut();
        }, 5000);
        
        // Scroll to top
        $('html, body').animate({
            scrollTop: $manager.offset().top - 20
        }, 300);
    }
    
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback: Unable to copy', err);
            }
            document.body.removeChild(textArea);
        }
    }
    
    // Frontend Visual Editor Functions
    function initFrontendVisualEditor() {
        // Handle Edit Content button clicks
        $(document).on('click', '.edit-manually', function() {
            const menuId = $(this).data('menu-id');
            loadVisualEditor(menuId);
        });
        
        // Handle Cancel button clicks
        $(document).on('click', '.cancel-edit', function() {
            const menuId = $(this).closest('.menu-visual-editor').data('menu-id');
            hideVisualEditor(menuId);
        });
        
        // Handle Add Section button
        $(document).on('click', '.add-section-frontend', function() {
            const menuId = $(this).closest('.menu-visual-editor').data('menu-id');
            addMenuSection(menuId);
        });
        
        // Handle Add Item button
        $(document).on('click', '.add-item', function() {
            const $section = $(this).closest('.menu-section');
            const menuId = $(this).closest('.menu-visual-editor').data('menu-id');
            addMenuItem($section, menuId);
        });
        
        // Handle Remove Section button
        $(document).on('click', '.remove-section', function() {
            if (confirm('Remove this section?')) {
                $(this).closest('.menu-section').remove();
                markAsChanged();
            }
        });
        
        // Handle Remove Item button
        $(document).on('click', '.remove-item', function() {
            $(this).closest('.menu-item').remove();
            markAsChanged();
        });

        // Handle Add Price Option button (size/variant pricing)
        $(document).on('click', '.menu-visual-editor .add-price-option', function() {
            const rowHtml = `
                <div class="price-option-row">
                    <input type="text" class="price-option-label" placeholder="Size (e.g. 20oz)">
                    <input type="text" class="price-option-price" placeholder="Price (e.g. $9.00)">
                    <button type="button" class="remove-price-option" title="Remove price option">&times;</button>
                </div>
            `;
            $(this).closest('.item-price-options-editor').find('.price-options-list').append(rowHtml);
            markAsChanged();
        });

        // Handle Remove Price Option button
        $(document).on('click', '.menu-visual-editor .remove-price-option', function() {
            $(this).closest('.price-option-row').remove();
            markAsChanged();
        });

        // Track changes on price option inputs
        $(document).on('input', '.menu-visual-editor .price-option-label, .menu-visual-editor .price-option-price', function() {
            markAsChanged();
        });

        // Handle Toggle Additional Info button
        $(document).on('click', '.toggle-additional-info', function() {
            const $additionalInfo = $(this).closest('.menu-item').find('.item-additional-info');
            $additionalInfo.toggle();
            markAsChanged();
        });
        
        // Handle Add Other Content button
        $(document).on('click', '.add-other-content-frontend', function() {
            const menuId = $(this).closest('.menu-visual-editor').data('menu-id');
            addOtherContent(menuId);
        });
        
        // Handle Remove Other Content button
        $(document).on('click', '.remove-other-content', function() {
            $(this).closest('.other-content-item').remove();
            markAsChanged();
        });
        
        // Handle Save button
        $(document).on('click', '.save-menu-frontend', function() {
            const menuId = $(this).closest('.menu-visual-editor').data('menu-id');
            saveMenuChanges(menuId);
        });
        
        // Track changes on editable elements
        $(document).on('input blur', '.menu-visual-editor .editable, .menu-visual-editor .content-text, .menu-visual-editor .content-type, .menu-visual-editor .inline-content-text', function() {
            markAsChanged();
        });
        
        $(document).on('change', '.menu-visual-editor .dietary-checkboxes input', function() {
            markAsChanged();
        });
    }
    
    function loadVisualEditor(menuId) {
        const $card = $('.menu-card[data-menu-id="' + menuId + '"]');
        const $editor = $('#visual-editor-' + menuId);
        const $manager = $('.menumatic-frontend-manager');
        const nonce = $manager.data('nonce') || menumatic_ajax.nonce;

        if (!$card.hasClass('is-active')) {
            $('.menu-pill[data-menu-id="' + menuId + '"]').addClass('is-active');
            openMenuPanel(menuId);
        }
        
        // Show loading
        $card.find('.edit-manually').text('Loading...').prop('disabled', true);
        
        // Get menu data via AJAX
        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_get_menu_data',
                nonce: nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (response.success) {
                    populateVisualEditor(menuId, response.data.menu_data);
                    syncStyleEditor(menuId, response.data.style_settings || {});
                    $editor.stop(true, true).slideDown(220);
                    
                    // Scroll to editor
                    $('html, body').animate({
                        scrollTop: $editor.offset().top - 20
                    }, 500);
                } else {
                    showMessage(response.data || 'Failed to load menu data.', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $card.find('.edit-manually').text('Edit Content').prop('disabled', false);
            }
        });
    }
    
    function hideVisualEditor(menuId) {
        $('#visual-editor-' + menuId).stop(true, true).slideUp(180);
        hasUnsavedChanges = false;
    }
    
    function populateVisualEditor(menuId, menuData) {
        const $editor = $('#visual-menu-editor-' + menuId);
        const $otherContent = $('#other-content-section-' + menuId);
        
        // Clear existing content
        $editor.empty();
        $otherContent.empty();
        
        // Check if we have the new content structure with ordered items
        if (menuData.content && Array.isArray(menuData.content)) {
            // New structure with ordered content (sections and inline other content)
            menuData.content.forEach(function(content, contentIndex) {
                if (content.type === 'section') {
                    const sectionHtml = createSectionHtml(content, contentIndex);
                    $editor.append(sectionHtml);
                } else if (content.type === 'other_content') {
                    // Create inline other content
                    const inlineContentHtml = createInlineOtherContentHtml(content, contentIndex);
                    $editor.append(inlineContentHtml);
                }
            });
            
            // Populate sidebar other content
            if (menuData.other_content && menuData.other_content.length > 0) {
                menuData.other_content.forEach(function(content, contentIndex) {
                    const contentHtml = createOtherContentHtml(content, contentIndex);
                    $otherContent.append(contentHtml);
                });
            }
        } else {
            // Legacy structure - fall back to old behavior
            if (menuData.sections && menuData.sections.length > 0) {
                menuData.sections.forEach(function(section, sectionIndex) {
                    const sectionHtml = createSectionHtml(section, sectionIndex);
                    $editor.append(sectionHtml);
                });
            }
            
            // Populate other content
            if (menuData.other_content && menuData.other_content.length > 0) {
                menuData.other_content.forEach(function(content, contentIndex) {
                    const contentHtml = createOtherContentHtml(content, contentIndex);
                    $otherContent.append(contentHtml);
                });
            }
        }
        
        // Initialize drag and drop functionality
        initializeDragAndDrop(menuId);
    }
    
    function createSectionHtml(section, sectionIndex) {
        let itemsHtml = '';
        if (section.items && section.items.length > 0) {
            section.items.forEach(function(item, itemIndex) {
                itemsHtml += createItemHtml(item, itemIndex);
            });
        }
        
        return `
            <div class="menu-section" data-section-index="${sectionIndex}" draggable="true">
                <div class="section-drag-handle" title="Drag to reorder sections">
                    <span class="dashicons dashicons-menu-alt"></span>
                </div>
                <h2 class="editable section-title" contenteditable="true" data-field="title">${escapeHtml(section.title || '')}</h2>
                
                <div class="section-controls">
                    <button type="button" class="menumatic-btn menumatic-btn-small add-item">Add Item</button>
                    <button type="button" class="menumatic-btn menumatic-btn-small menumatic-btn-danger remove-section">Remove Section</button>
                </div>
                
                <div class="menu-grid">
                    ${itemsHtml}
                </div>
            </div>
        `;
    }
    
    function createItemHtml(item, itemIndex) {
        const dietaryIndicators = item.dietary_indicators || [];
        const indicators = ['vegetarian', 'vegan', 'gluten-free', 'spicy', 'organic', 'local', 'seasonal', 'chef-recommended'];
        
        let dietaryHtml = '';
        indicators.forEach(function(indicator) {
            const checked = dietaryIndicators.includes(indicator) ? 'checked' : '';
            const displayName = indicator.charAt(0).toUpperCase() + indicator.slice(1).replace('-', ' ');
            dietaryHtml += `
                <label class="dietary-checkbox">
                    <input type="checkbox" name="dietary[]" value="${indicator}" ${checked}>
                    <span>${displayName}</span>
                </label>
            `;
        });
        
        const additionalInfo = item.additional_info || '';
        const additionalInfoDisplay = additionalInfo ? 'block' : 'none';

        function escapeAttr(value) {
            return escapeHtml(value).replace(/"/g, '&quot;');
        }

        const priceOptions = Array.isArray(item.price_options) ? item.price_options : [];
        let priceOptionsHtml = '';
        priceOptions.forEach(function(po) {
            if (!po) return;
            priceOptionsHtml += `
                <div class="price-option-row">
                    <input type="text" class="price-option-label" placeholder="Size (e.g. 20oz)" value="${escapeAttr(po.label || '')}">
                    <input type="text" class="price-option-price" placeholder="Price (e.g. $9.00)" value="${escapeAttr(po.price || '')}">
                    <button type="button" class="remove-price-option" title="Remove price option">&times;</button>
                </div>
            `;
        });

        return `
            <div class="menu-item" data-item-index="${itemIndex}" draggable="true">
                <div class="item-controls">
                    <div class="item-drag-handle" title="Drag to reorder items">
                        <span class="dashicons dashicons-menu-alt"></span>
                    </div>
                    <button type="button" class="remove-item" title="Remove Item">&times;</button>
                </div>
                <div class="item-name editable" contenteditable="true" data-field="name" placeholder="Item name">${escapeHtml(item.name || '')}</div>
                <div class="item-price editable" contenteditable="true" data-field="price" placeholder="Price">${escapeHtml(item.price || '')}</div>
                <div class="item-price-options-editor">
                    <div class="price-options-list">${priceOptionsHtml}</div>
                    <button type="button" class="menumatic-btn menumatic-btn-small add-price-option">+ Add Price Option</button>
                    <p class="price-options-hint">Add size/variant prices (e.g. 20oz $9.00, 16oz $8.00). When set, these replace the single price above.</p>
                </div>
                <div class="item-description editable" contenteditable="true" data-field="description" placeholder="Description">${escapeHtml(item.description || '')}</div>
                <div class="item-additional-info editable" contenteditable="true" data-field="additional_info" placeholder="Additional info (optional)" style="display: ${additionalInfoDisplay};">${escapeHtml(additionalInfo)}</div>
                
                <div class="dietary-indicators">
                    <label>Dietary Indicators:</label>
                    <div class="dietary-checkboxes">
                        ${dietaryHtml}
                    </div>
                </div>
                
                <div class="item-actions">
                    <button type="button" class="menumatic-btn menumatic-btn-small toggle-additional-info">Toggle Additional Info</button>
                </div>
            </div>
        `;
    }
    
    function createOtherContentHtml(content, contentIndex) {
        const types = [
            {value: 'restaurant_info', label: 'Restaurant Info'},
            {value: 'hours', label: 'Hours'},
            {value: 'contact', label: 'Contact'},
            {value: 'special_offer', label: 'Special Offer'},
            {value: 'other', label: 'Other'}
        ];
        
        let optionsHtml = '';
        types.forEach(function(type) {
            const selected = content.type === type.value ? 'selected' : '';
            optionsHtml += `<option value="${type.value}" ${selected}>${type.label}</option>`;
        });
        
        return `
            <div class="other-content-item" data-content-index="${contentIndex}" draggable="true">
                <div class="content-drag-handle" title="Drag to reorder content">
                    <span class="dashicons dashicons-menu-alt"></span>
                </div>
                <select class="content-type" data-field="type">
                    ${optionsHtml}
                </select>
                <textarea class="content-text editable" data-field="content" placeholder="Content text">${escapeHtml(content.content || '')}</textarea>
                <button type="button" class="menumatic-btn menumatic-btn-small menumatic-btn-danger remove-other-content">Remove</button>
            </div>
        `;
    }
    
    function createInlineOtherContentHtml(content, contentIndex) {
        const contentType = content.content_type || 'other';
        const contentTypeLabel = getContentTypeLabel(contentType);
        
        return `
            <div class="inline-other-content" data-content-type="${escapeHtml(contentType)}" draggable="true">
                <div class="inline-content-header">
                    <div class="inline-content-drag-handle" title="Drag to reorder content">
                        <span class="dashicons dashicons-menu-alt"></span>
                    </div>
                    <span class="inline-content-label">${escapeHtml(contentTypeLabel)}</span>
                    <button type="button" class="remove-inline-content" title="Remove from menu area">&times;</button>
                </div>
                <div class="inline-content-text editable" contenteditable="true">${escapeHtml(content.content || '')}</div>
            </div>
        `;
    }
    
    function addMenuSection(menuId) {
        const $editor = $('#visual-menu-editor-' + menuId);
        const sectionIndex = $editor.find('.menu-section').length;
        const sectionHtml = createSectionHtml({title: 'New Section', items: []}, sectionIndex);
        $editor.append(sectionHtml);
        initializeDragAndDrop(menuId);
        markAsChanged();
    }
    
    function addMenuItem($section, menuId) {
        const $grid = $section.find('.menu-grid');
        const itemIndex = $grid.find('.menu-item').length;
        const itemHtml = createItemHtml({
            name: 'New Item',
            price: '$0.00',
            description: 'Description'
        }, itemIndex);
        $grid.append(itemHtml);
        markAsChanged();
    }
    
    function addOtherContent(menuId) {
        const $section = $('#other-content-section-' + menuId);
        const contentIndex = $section.find('.other-content-item').length;
        const contentHtml = createOtherContentHtml({
            type: 'restaurant_info',
            content: ''
        }, contentIndex);
        $section.append(contentHtml);
        markAsChanged();
    }

    function getStyleLibrary() {
        return menumatic_ajax.style_library || {};
    }

    function getDefaultLayout() {
        return {
            columns: 'auto',
            price_position: 'inline',
            text_layout: 'stacked'
        };
    }

    function getStyleDefinition(styleId) {
        const library = getStyleLibrary();

        if (styleId && library[styleId]) {
            return library[styleId];
        }

        if (library['preset-modern']) {
            return library['preset-modern'];
        }

        const styleIds = Object.keys(library);
        if (styleIds.length > 0) {
            return library[styleIds[0]];
        }

        return {
            id: 'preset-modern',
            name: 'Modern',
            description: '',
            preset_slug: 'modern',
            layout: getDefaultLayout(),
            custom_css: '',
            is_builtin: true
        };
    }

    function syncGlobalStyleSelection(styleId) {
        menumatic_ajax.default_style_id = styleId;
        $('.menu-style-select').val(styleId);
        $('#frontend-menu-style').val(styleId);
    }

    function persistGlobalStyle(styleId, nonce) {
        const normalizedStyleId = styleId || menumatic_ajax.default_style_id || 'preset-modern';

        if (normalizedStyleId === (menumatic_ajax.default_style_id || 'preset-modern')) {
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'menumatic_set_global_style',
                nonce: nonce,
                style_id: normalizedStyleId
            }
        }).done(function(response) {
            if (response && response.success) {
                syncGlobalStyleSelection(response.data.style_id);
            }
        });
    }

    function normalizeLayoutValue(field, value, fallbackValue) {
        if (MENU_LAYOUT_OPTIONS[field].indexOf(value) !== -1) {
            return value;
        }

        if (MENU_LAYOUT_OPTIONS[field].indexOf(fallbackValue) !== -1) {
            return fallbackValue;
        }

        return getDefaultLayout()[field];
    }

    function normalizeLayoutSettings(layout, styleId) {
        const styleDefinition = getStyleDefinition(styleId);
        const defaults = $.extend({}, getDefaultLayout(), styleDefinition.layout || {});
        const normalized = {};

        Object.keys(MENU_LAYOUT_OPTIONS).forEach(function(field) {
            normalized[field] = normalizeLayoutValue(field, layout && layout[field], defaults[field]);
        });

        return normalized;
    }

    function normalizeStyleSettings(styleSettings) {
        const normalized = styleSettings || {};
        const styleId = normalized.style_id || (normalized.style ? 'preset-' + normalized.style : 'preset-modern');

        return {
            style_id: styleId,
            layout: normalizeLayoutSettings(normalized.layout || {}, styleId),
            override_css: normalized.override_css || normalized.custom_css || ''
        };
    }

    function syncStyleEditor(menuId, styleSettings) {
        const normalizedSettings = normalizeStyleSettings(styleSettings);

        $('.menu-style-select[data-menu-id="' + menuId + '"]').val(normalizedSettings.style_id);
        $('.menu-custom-css[data-menu-id="' + menuId + '"]').val(normalizedSettings.override_css);

        Object.keys(normalizedSettings.layout).forEach(function(field) {
            $('.menu-layout-control[data-menu-id="' + menuId + '"][data-layout-field="' + field + '"]').val(normalizedSettings.layout[field]);
        });

        updateStylePresetDescription(menuId);
        applyStylePreview(menuId);
    }

    function updateStylePresetDescription(menuId) {
        const $editor = $('.menu-style-editor[data-menu-id="' + menuId + '"]');
        const settings = getMenuStyleSettings(menuId);
        const styleDefinition = getStyleDefinition(settings.style_id);
        const sourceNote = 'This is the full reusable stylesheet for the selected global style. Edit it from MenuMatic > Styles.';

        $editor.find('.style-preset-description').text(styleDefinition.description || '');
        $editor.find('.style-chip').text(styleDefinition.name || 'Modern');
        $editor.find('.menu-style-source-css').val((styleDefinition.custom_css || '').trim());
        $editor.find('.menu-style-source-note').text(sourceNote);
    }

    function hydrateStyleEditorFromDefinition(menuId, styleId) {
        const layout = normalizeLayoutSettings({}, styleId);

        Object.keys(layout).forEach(function(field) {
            $('.menu-layout-control[data-menu-id="' + menuId + '"][data-layout-field="' + field + '"]').val(layout[field]);
        });

        updateStylePresetDescription(menuId);
    }

    function getMenuStyleSettings(menuId) {
        const styleId = $('.menu-style-select[data-menu-id="' + menuId + '"]').val() || 'preset-modern';
        const layout = {};

        $('.menu-layout-control[data-menu-id="' + menuId + '"]').each(function() {
            layout[$(this).data('layout-field')] = $(this).val();
        });

        return {
            style_id: styleId,
            layout: normalizeLayoutSettings(layout, styleId),
            override_css: $('.menu-custom-css[data-menu-id="' + menuId + '"]').val() || ''
        };
    }

    function getCombinedMenuCss(styleSettings) {
        const styleDefinition = getStyleDefinition(styleSettings.style_id);

        return [styleDefinition.custom_css || '', styleSettings.override_css || '']
            .map(function(css) {
                return (css || '').trim();
            })
            .filter(function(css) {
                return css !== '';
            })
            .join('\n\n');
    }

    function applyMenuClasses($menu, styleSettings) {
        const styleDefinition = getStyleDefinition(styleSettings.style_id);
        const layout = normalizeLayoutSettings(styleSettings.layout || {}, styleDefinition.id);
        const remainingClasses = ($menu.attr('class') || '').split(/\s+/).filter(function(className) {
            return className &&
                className.indexOf('style-') !== 0 &&
                className.indexOf('layout-columns-') !== 0 &&
                className.indexOf('price-') !== 0 &&
                className.indexOf('text-') !== 0;
        });

        remainingClasses.push('style-' + styleDefinition.preset_slug);
        remainingClasses.push('layout-columns-' + layout.columns);
        remainingClasses.push('price-' + layout.price_position);
        remainingClasses.push('text-' + layout.text_layout);

        $menu.attr('class', remainingClasses.join(' ').trim());
        $menu.attr('data-style-id', styleDefinition.id);
        $menu.attr('data-style-slug', styleDefinition.preset_slug);
    }

    function applyMenuCss($menu, combinedCss) {
        const instanceId = $menu.attr('data-style-instance');
        let $styleTag = $menu.prev('style.menumatic-custom-style');

        if (!instanceId) {
            return;
        }

        if (!$styleTag.length && combinedCss) {
            $styleTag = $('<style class="menumatic-custom-style"></style>');
            $menu.before($styleTag);
        }

        if (!combinedCss) {
            $styleTag.remove();
            return;
        }

        let scopedCss = combinedCss;
        const scopeSelector = '.menumatic-menu[data-style-instance="' + instanceId + '"]';
        if (scopedCss.indexOf('{{scope}}') !== -1) {
            scopedCss = scopedCss.replace(/\{\{scope\}\}/g, scopeSelector);
        } else {
            scopedCss = scopeSelector + ' {\n' + scopedCss + '\n}';
        }

        $styleTag.text(scopedCss);
    }

    function applyMenuStyleToInstances(menuId, styleSettings) {
        const normalizedSettings = normalizeStyleSettings(styleSettings);
        const combinedCss = getCombinedMenuCss(normalizedSettings);

        $('.menumatic-menu[data-menu-id="' + menuId + '"]').each(function() {
            const $menu = $(this);
            applyMenuClasses($menu, normalizedSettings);
            applyMenuCss($menu, combinedCss);
        });
    }

    function applyStylePreview(menuId) {
        const settings = getMenuStyleSettings(menuId);
        updateStylePresetDescription(menuId);
        applyMenuStyleToInstances(menuId, settings);
    }

    function replaceRenderedMenuInstances(menuId, renderedHtml) {
        const $card = $('.menu-card[data-menu-id="' + menuId + '"]');
        const $wrapper = $('<div></div>').html(renderedHtml);
        const $newMenu = $wrapper.find('.menumatic-menu').first();
        const $newStyle = $wrapper.find('style.menumatic-custom-style').first();

        $card.find('.menu-card-preview').html(renderedHtml);

        $('.menumatic-menu[data-menu-id="' + menuId + '"]').filter(function() {
            return $(this).closest('.menu-card-preview').length === 0;
        }).each(function() {
            const $existingMenu = $(this);
            const $existingStyle = $existingMenu.prev('style.menumatic-custom-style');

            if ($newStyle.length) {
                if ($existingStyle.length) {
                    $existingStyle.replaceWith($newStyle.clone());
                } else {
                    $existingMenu.before($newStyle.clone());
                }
            } else {
                $existingStyle.remove();
            }

            $existingMenu.replaceWith($newMenu.clone());
        });

        if ($('#preview-content .menumatic-menu[data-menu-id="' + menuId + '"]').length) {
            $('#preview-content').html(renderedHtml);
        }
    }
    
    function saveMenuChanges(menuId) {
        const menuData = getMenuDataFromFrontendEditor(menuId);
        
        if (!menuData) {
            return;
        }
        
        if (!menuData.sections || menuData.sections.length === 0) {
            showMessage('Please add at least one menu section before saving.', 'error');
            return;
        }
        
        let jsonData;
        try {
            jsonData = JSON.stringify(menuData);
        } catch (error) {
            console.error('JSON stringify error:', error);
            showMessage('Error creating JSON data: ' + error.message, 'error');
            return;
        }
        
        const $saveBtn = $('.menu-visual-editor[data-menu-id="' + menuId + '"] .save-menu-frontend');
        const originalText = $saveBtn.text();
        const $manager = $('.menumatic-frontend-manager');
        const nonce = $manager.data('nonce') || menumatic_ajax.nonce;
        const requestData = {
            action: 'menumatic_regenerate_from_json',
            nonce: nonce,
            menu_id: menuId,
            json_data: jsonData,
            style_settings: JSON.stringify(getMenuStyleSettings(menuId))
        };
        
        $saveBtn.text('Saving & Updating...').prop('disabled', true);

        persistGlobalStyle(getMenuStyleSettings(menuId).style_id, nonce)
            .done(function(response) {
                if (response && response.success === false) {
                    showMessage(response.data || 'Failed to update global style.', 'error');
                    return;
                }

                $.ajax({
                    url: menumatic_ajax.ajax_url,
                    type: 'POST',
                    data: requestData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            hasUnsavedChanges = false;
                            showMessage('Menu updated successfully!', 'success');
                            
                            replaceRenderedMenuInstances(menuId, response.data.html);
                            
                            // Hide the editor
                            hideVisualEditor(menuId);
                        } else {
                            console.error('Server response error:', response);
                            showMessage('Error: ' + (response.data || 'Unknown error occurred'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error, xhr.responseText);
                        showMessage('Network error occurred. Please try again.', 'error');
                    },
                    complete: function() {
                        $saveBtn.text(originalText).prop('disabled', false);
                    }
                });
            })
            .fail(function() {
                showMessage('Network error occurred while updating the global style.', 'error');
                $saveBtn.text(originalText).prop('disabled', false);
            });
    }

    function saveMenuStyleOnly(menuId, $button) {
        const $manager = $('.menumatic-frontend-manager');
        const nonce = $manager.data('nonce') || menumatic_ajax.nonce;
        const originalText = $button.text();

        $button.text('Saving...').prop('disabled', true);

        $.ajax({
            url: menumatic_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'menumatic_get_menu_data',
                nonce: nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (!response.success) {
                    showMessage(response.data || 'Failed to load menu styles.', 'error');
                    $button.text(originalText).prop('disabled', false);
                    return;
                }

                persistGlobalStyle(getMenuStyleSettings(menuId).style_id, nonce)
                    .done(function(styleResponse) {
                        if (styleResponse && styleResponse.success === false) {
                            showMessage(styleResponse.data || 'Failed to update global style.', 'error');
                            $button.text(originalText).prop('disabled', false);
                            return;
                        }

                        $.ajax({
                            url: menumatic_ajax.ajax_url,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'menumatic_regenerate_from_json',
                                nonce: nonce,
                                menu_id: menuId,
                                json_data: JSON.stringify(response.data.menu_data),
                                style_settings: JSON.stringify(getMenuStyleSettings(menuId))
                            },
                            success: function(saveResponse) {
                                if (saveResponse.success) {
                                    replaceRenderedMenuInstances(menuId, saveResponse.data.html);
                                    showMessage('Menu style updated successfully!', 'success');
                                    hasUnsavedChanges = false;
                                } else {
                                    showMessage(saveResponse.data || 'Failed to save menu style.', 'error');
                                }
                            },
                            error: function() {
                                showMessage('Network error occurred while saving style changes.', 'error');
                            },
                            complete: function() {
                                $button.text(originalText).prop('disabled', false);
                            }
                        });
                    })
                    .fail(function() {
                        showMessage('Network error occurred while updating the global style.', 'error');
                        $button.text(originalText).prop('disabled', false);
                    });
            },
            error: function() {
                showMessage('Network error occurred while loading menu data.', 'error');
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    function getMenuDataFromFrontendEditor(menuId) {
        const $editor = $('#visual-menu-editor-' + menuId);
        const $otherSection = $('#other-content-section-' + menuId);
        
        const menuData = {
            content: [],        // Ordered content (sections + inline other content)
            other_content: []   // Sidebar other content
        };
        
        try {
            // Extract ordered content (sections and inline other content)
            $editor.children().each(function() {
                const $element = $(this);
                
                if ($element.hasClass('menu-section')) {
                    const sectionTitle = safeExtractText($element.find('.section-title'));
                    
                    if (!sectionTitle) return;
                    
                    const section = {
                        type: 'section',
                        title: sectionTitle,
                        items: []
                    };
                    
                    $element.find('.menu-item').each(function() {
                    const $item = $(this);
                    const itemName = safeExtractText($item.find('[data-field="name"]'));
                    
                    if (!itemName) return;
                    
                    const priceOptions = [];
                    $item.find('.price-option-row').each(function() {
                        const $row = $(this);
                        const label = ($row.find('.price-option-label').val() || '').trim();
                        const optPrice = ($row.find('.price-option-price').val() || '').trim();
                        if (label || optPrice) {
                            priceOptions.push({ label: label, price: optPrice });
                        }
                    });

                    const item = {
                        name: itemName,
                        price: priceOptions.length ? '' : (safeExtractText($item.find('[data-field="price"]')) || ''),
                        description: safeExtractText($item.find('[data-field="description"]')) || ''
                    };

                    if (priceOptions.length) {
                        item.price_options = priceOptions;
                    }

                    const additionalInfo = safeExtractText($item.find('[data-field="additional_info"]'));
                    if (additionalInfo) {
                        item.additional_info = additionalInfo;
                    }
                    
                    const dietaryIndicators = [];
                    $item.find('.dietary-checkboxes input:checked').each(function() {
                        const value = $(this).val();
                        if (value && value.trim()) {
                            dietaryIndicators.push(value.trim());
                        }
                    });
                    if (dietaryIndicators.length > 0) {
                        item.dietary_indicators = dietaryIndicators;
                    }
                    
                    section.items.push(item);
                    });
                    
                    if (section.items.length > 0) {
                        menuData.content.push(section);
                    }
                    
                } else if ($element.hasClass('inline-other-content')) {
                    // Handle inline other content
                    const contentType = $element.data('content-type');
                    const contentText = safeExtractText($element.find('.inline-content-text'));
                    
                    if (contentType && contentText) {
                        const content = {
                            type: 'other_content',
                            content_type: contentType,
                            content: contentText
                        };
                        menuData.content.push(content);
                    }
                }
            });
            
            // Extract sidebar other content
            $otherSection.find('.other-content-item').each(function() {
                const $item = $(this);
                const contentType = $item.find('.content-type').val();
                const contentText = $item.find('.content-text').val();
                
                if (contentType && contentText && contentText.trim()) {
                    menuData.other_content.push({
                        type: contentType,
                        content: contentText.trim()
                    });
                }
            });
            
            // Legacy support: also create sections array for backward compatibility
            menuData.sections = menuData.content.filter(item => item.type === 'section').map(section => ({
                title: section.title,
                items: section.items
            }));
            
            return menuData;
            
        } catch (error) {
            console.error('Error extracting menu data:', error);
            showMessage('Error extracting menu data: ' + error.message, 'error');
            return null;
        }
    }
    
    function safeExtractText($element) {
        if (!$element || $element.length === 0) return '';
        
        let text = $element.text().trim();
        text = text.replace(/\s+/g, ' ').trim();
        text = text.replace(/[\u0000-\u001F\u007F-\u009F]/g, '');
        text = text.replace(/"/g, '"').replace(/'/g, "'");
        text = text.replace(/\n/g, ' ').replace(/\r/g, ' ');
        
        return text;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    let hasUnsavedChanges = false;
    
    function markAsChanged() {
        hasUnsavedChanges = true;
    }
    
    // Initialize drag and drop functionality for frontend editor
    function initializeDragAndDrop(menuId) {
        // Section reordering
        initSectionDragAndDrop(menuId);
        
        // Item reordering within sections
        initItemDragAndDrop(menuId);
        
        // Other content reordering
        initOtherContentDragAndDrop(menuId);
    }
    
    function initSectionDragAndDrop(menuId) {
        let draggedSection = null;
        const editorSelector = '#visual-menu-editor-' + menuId;
        
        // Remove existing listeners for this menu
        $(document).off('dragstart.sections.' + menuId + ' dragover.sections.' + menuId + ' drop.sections.' + menuId + ' dragend.sections.' + menuId);
        
        $(document).on('dragstart.sections.' + menuId, editorSelector + ' .menu-section', function(e) {
            draggedSection = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
        });
        
        $(document).on('dragover.sections.' + menuId, editorSelector + ' .menu-section', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            
            if (this !== draggedSection) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).addClass('drag-over-top').removeClass('drag-over-bottom');
                } else {
                    $(this).addClass('drag-over-bottom').removeClass('drag-over-top');
                }
            }
        });
        
        $(document).on('dragleave.sections.' + menuId, editorSelector + ' .menu-section', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.sections.' + menuId, editorSelector + ' .menu-section', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over-top drag-over-bottom');
            
            if (this !== draggedSection) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).before(draggedSection);
                } else {
                    $(this).after(draggedSection);
                }
                markAsChanged();
            }
        });
        
        $(document).on('dragend.sections.' + menuId, editorSelector + ' .menu-section', function(e) {
            $(this).removeClass('dragging');
            $(editorSelector + ' .menu-section').removeClass('drag-over-top drag-over-bottom');
            draggedSection = null;
        });
    }
    
    function initItemDragAndDrop(menuId) {
        let draggedItem = null;
        const editorSelector = '#visual-menu-editor-' + menuId;
        
        // Remove existing listeners for this menu
        $(document).off('dragstart.items.' + menuId + ' dragover.items.' + menuId + ' drop.items.' + menuId + ' dragend.items.' + menuId);
        
        $(document).on('dragstart.items.' + menuId, editorSelector + ' .menu-item', function(e) {
            draggedItem = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
        });
        
        $(document).on('dragover.items.' + menuId, editorSelector + ' .menu-item', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            
            // Only allow dropping within the same section
            const draggedSection = $(draggedItem).closest('.menu-section')[0];
            const targetSection = $(this).closest('.menu-section')[0];
            
            if (this !== draggedItem && draggedSection === targetSection) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).addClass('drag-over-top').removeClass('drag-over-bottom');
                } else {
                    $(this).addClass('drag-over-bottom').removeClass('drag-over-top');
                }
            }
        });
        
        $(document).on('dragleave.items.' + menuId, editorSelector + ' .menu-item', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.items.' + menuId, editorSelector + ' .menu-item', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over-top drag-over-bottom');
            
            // Only allow dropping within the same section
            const draggedSection = $(draggedItem).closest('.menu-section')[0];
            const targetSection = $(this).closest('.menu-section')[0];
            
            if (this !== draggedItem && draggedSection === targetSection) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).before(draggedItem);
                } else {
                    $(this).after(draggedItem);
                }
                markAsChanged();
            }
        });
        
        $(document).on('dragend.items.' + menuId, editorSelector + ' .menu-item', function(e) {
            $(this).removeClass('dragging');
            $(editorSelector + ' .menu-item').removeClass('drag-over-top drag-over-bottom');
            draggedItem = null;
        });
    }
    
    function initOtherContentDragAndDrop(menuId) {
        let draggedContent = null;
        const contentSelector = '#other-content-section-' + menuId;
        const editorSelector = '#visual-menu-editor-' + menuId;
        
        // Remove existing listeners for this menu
        $(document).off('dragstart.content.' + menuId + ' dragover.content.' + menuId + ' drop.content.' + menuId + ' dragend.content.' + menuId + ' dragover.maindrop.' + menuId + ' drop.maindrop.' + menuId);
        
        $(document).on('dragstart.content.' + menuId, contentSelector + ' .other-content-item', function(e) {
            draggedContent = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
            e.originalEvent.dataTransfer.setData('text/content-type', 'other-content');
        });
        
        // Sidebar reordering (existing functionality)
        $(document).on('dragover.content.' + menuId, contentSelector + ' .other-content-item', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            
            if (this !== draggedContent) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).addClass('drag-over-top').removeClass('drag-over-bottom');
                } else {
                    $(this).addClass('drag-over-bottom').removeClass('drag-over-top');
                }
            }
        });
        
        $(document).on('dragleave.content.' + menuId, contentSelector + ' .other-content-item', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.content.' + menuId, contentSelector + ' .other-content-item', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over-top drag-over-bottom');
            
            if (this !== draggedContent) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).before(draggedContent);
                } else {
                    $(this).after(draggedContent);
                }
                markAsChanged();
            }
        });
        
        // Main editor drop zone for cross-container dragging
        $(document).on('dragover.maindrop.' + menuId, editorSelector, function(e) {
            e.preventDefault();
            
            // Only allow other content items
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'other-content') {
                e.originalEvent.dataTransfer.dropEffect = 'move';
                
                // Find the closest section to show drop indicator
                const sections = $(this).find('.menu-section');
                let closestSection = null;
                let minDistance = Infinity;
                let insertPosition = 'after';
                
                sections.each(function() {
                    const rect = this.getBoundingClientRect();
                    const sectionMidY = rect.top + rect.height / 2;
                    const distance = Math.abs(e.originalEvent.clientY - sectionMidY);
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        closestSection = this;
                        insertPosition = e.originalEvent.clientY < sectionMidY ? 'before' : 'after';
                    }
                });
                
                // Clear previous indicators
                sections.removeClass('content-drop-before content-drop-after');
                
                // Add indicator to closest section
                if (closestSection) {
                    $(closestSection).addClass('content-drop-' + insertPosition);
                }
            }
        });
        
        $(document).on('dragleave.maindrop.' + menuId, editorSelector, function(e) {
            // Only clear if we're actually leaving the main editor
            if (!$(this)[0].contains(e.relatedTarget)) {
                $(this).find('.menu-section').removeClass('content-drop-before content-drop-after');
            }
        });
        
        $(document).on('drop.maindrop.' + menuId, editorSelector, function(e) {
            e.preventDefault();
            
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'other-content' && draggedContent) {
                const sections = $(this).find('.menu-section');
                let closestSection = null;
                let minDistance = Infinity;
                let insertPosition = 'after';
                
                sections.each(function() {
                    const rect = this.getBoundingClientRect();
                    const sectionMidY = rect.top + rect.height / 2;
                    const distance = Math.abs(e.originalEvent.clientY - sectionMidY);
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        closestSection = this;
                        insertPosition = e.originalEvent.clientY < sectionMidY ? 'before' : 'after';
                    }
                });
                
                if (closestSection) {
                    // Create inline version of the other content
                    const $draggedContent = $(draggedContent);
                    const contentType = $draggedContent.find('.content-type').val();
                    const contentText = $draggedContent.find('.content-text').val();
                    
                    const inlineContentHtml = createInlineOtherContentHtml({
                        content_type: contentType,
                        content: contentText
                    }, 0);
                    
                    if (insertPosition === 'before') {
                        $(closestSection).before(inlineContentHtml);
                    } else {
                        $(closestSection).after(inlineContentHtml);
                    }
                    
                    // Remove from sidebar
                    $draggedContent.remove();
                    markAsChanged();
                }
                
                // Clear indicators
                sections.removeClass('content-drop-before content-drop-after');
            }
        });
        
        $(document).on('dragend.content.' + menuId, contentSelector + ' .other-content-item', function(e) {
            $(this).removeClass('dragging');
            $(contentSelector + ' .other-content-item').removeClass('drag-over-top drag-over-bottom');
            $(editorSelector + ' .menu-section').removeClass('content-drop-before content-drop-after');
            draggedContent = null;
        });
        
        // Handle removal of inline content (move back to sidebar)
        $(document).on('click', '.remove-inline-content', function() {
            const $inlineContent = $(this).closest('.inline-other-content');
            const contentType = $inlineContent.data('content-type');
            const contentText = $inlineContent.find('.inline-content-text').text();
            
            // Add back to sidebar
            const contentIndex = $(contentSelector).find('.other-content-item').length;
            const sidebarContentHtml = createOtherContentHtml({
                type: contentType,
                content: contentText
            }, contentIndex);
            
            $(contentSelector).append(sidebarContentHtml);
            $inlineContent.remove();
            markAsChanged();
        });
        
        // Make inline content draggable back to sidebar
        $(document).on('dragstart', editorSelector + ' .inline-other-content', function(e) {
            draggedContent = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/content-type', 'inline-content');
        });
        
        $(document).on('dragover', contentSelector, function(e) {
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'inline-content') {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });
        
        $(document).on('drop', contentSelector, function(e) {
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'inline-content' && draggedContent) {
                e.preventDefault();
                
                const $draggedContent = $(draggedContent);
                const contentType = $draggedContent.data('content-type');
                const contentText = $draggedContent.find('.inline-content-text').text();
                
                // Add to sidebar
                const contentIndex = $(this).find('.other-content-item').length;
                const sidebarContentHtml = createOtherContentHtml({
                    type: contentType,
                    content: contentText
                }, contentIndex);
                
                $(this).append(sidebarContentHtml);
                $draggedContent.remove();
                markAsChanged();
            }
        });
    }
    
    function getContentTypeLabel(type) {
        const labels = {
            'restaurant_info': 'Restaurant Info',
            'hours': 'Hours',
            'contact': 'Contact',
            'special_offer': 'Special Offer',
            'other': 'Other'
        };
        return labels[type] || 'Other';
    }
    
    // Initialize frontend visual editor
    initFrontendVisualEditor();
}); 
