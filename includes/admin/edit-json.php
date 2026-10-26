<?php
/**
 * Admin Visual Menu Editor Page
 */

if (!defined('ABSPATH')) exit;

$menu_json = $menu->menu_data ?? '{}';
$menu_title = esc_html($menu->title);

// Parse JSON data for visual editing
$menu_data = json_decode($menu_json, true);
if (!$menu_data || (!isset($menu_data['sections']) && !isset($menu_data['content']))) {
    $menu_data = [
        'sections' => [
            [
                'title' => 'Menu Section',
                'items' => [
                    [
                        'name' => 'Sample Item',
                        'price' => '$0.00',
                        'description' => 'Sample description'
                    ]
                ]
            ]
        ],
        'other_content' => []
    ];
}

// Handle new data structure with mixed content
$ordered_content = [];
$sidebar_content = [];

if (isset($menu_data['content']) && is_array($menu_data['content'])) {
    // New structure with ordered content
    $ordered_content = $menu_data['content'];
    $sidebar_content = $menu_data['other_content'] ?? [];
} else {
    // Legacy structure - convert to new format
    if (isset($menu_data['sections']) && is_array($menu_data['sections'])) {
        foreach ($menu_data['sections'] as $section) {
            $ordered_content[] = [
                'type' => 'section',
                'title' => $section['title'],
                'items' => $section['items'] ?? []
            ];
        }
    }
    $sidebar_content = $menu_data['other_content'] ?? [];
}
?>

<div class="wrap menumatic-visual-editor">
    <h1><?php printf(__('Edit Menu: %s', 'menumatic'), $menu_title); ?></h1>
    
    <div class="menumatic-visual-editor-container">
        <div class="visual-editor-header">
            <p class="description">
                <?php _e('Edit your menu visually. Click on any text to edit it. Changes will be saved to the menu structure.', 'menumatic'); ?>
            </p>
            <div class="editor-actions">
                <button type="button" class="button" id="add-section"><?php _e('Add Section', 'menumatic'); ?></button>
                <button type="button" class="button button-primary" id="save-menu"><?php _e('Save & Update Menu', 'menumatic'); ?></button>
            </div>
        </div>
        
        <div class="visual-editor-main">
            <div class="menu-editor-panel">
                <div id="visual-menu-editor" class="menu-container" data-drop-zone="main">
                    <?php foreach ($ordered_content as $content_index => $content): ?>
                        <?php if ($content['type'] === 'section'): ?>
                            <div class="menu-section" data-section-index="<?php echo $content_index; ?>" draggable="true">
                                <div class="section-drag-handle" title="<?php _e('Drag to reorder sections', 'menumatic'); ?>">
                                    <span class="dashicons dashicons-menu-alt"></span>
                                </div>
                                <h2 class="editable section-title" contenteditable="true" data-field="title"><?php echo esc_html($content['title'] ?? 'Menu Section'); ?></h2>
                                <p class="editable section-subtitle" contenteditable="true" data-field="subtitle" placeholder="<?php esc_attr_e('Section note (optional)', 'menumatic'); ?>"><?php echo esc_html($content['subtitle'] ?? ''); ?></p>
                                
                                <div class="section-controls">
                                    <button type="button" class="button button-small add-item"><?php _e('Add Item', 'menumatic'); ?></button>
                                    <button type="button" class="button button-small remove-section"><?php _e('Remove Section', 'menumatic'); ?></button>
                                </div>
                                
                                <div class="menu-grid">
                                    <?php if (isset($content['items']) && is_array($content['items'])): ?>
                                        <?php foreach ($content['items'] as $item_index => $item): ?>
                                            <div class="menu-item" data-item-index="<?php echo $item_index; ?>" draggable="true">
                                                <div class="item-controls">
                                                    <div class="item-drag-handle" title="<?php _e('Drag to reorder items', 'menumatic'); ?>">
                                                        <span class="dashicons dashicons-menu-alt"></span>
                                                    </div>
                                                    <button type="button" class="remove-item" title="<?php _e('Remove Item', 'menumatic'); ?>">&times;</button>
                                                </div>
                                                <!-- Item Image Upload -->
                                                <div class="item-image-section">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <div class="item-image-preview">
                                                            <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name'] ?? ''); ?>" class="item-image">
                                                            <button type="button" class="remove-item-image" title="<?php _e('Remove Image', 'menumatic'); ?>">&times;</button>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="item-image-upload <?php echo !empty($item['image']) ? 'has-image' : ''; ?>">
                                                        <input type="file" class="item-image-input" accept="image/*" style="display: none;">
                                                        <button type="button" class="button button-small upload-item-image">
                                                            <span class="dashicons dashicons-camera"></span>
                                                            <?php _e('Add Image', 'menumatic'); ?>
                                                        </button>
                                                        <div class="image-upload-progress" style="display: none;">
                                                            <div class="progress-bar"><div class="progress-fill"></div></div>
                                                            <span class="progress-text"><?php _e('Uploading...', 'menumatic'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="item-name editable" contenteditable="true" data-field="name" placeholder="<?php _e('Item name', 'menumatic'); ?>"><?php echo esc_html($item['name'] ?? ''); ?></div>
                                                <?php $item_badges = is_array($item['badges'] ?? null) ? implode(', ', $item['badges']) : ($item['badges'] ?? ''); ?>
                                                <div class="item-badges editable" contenteditable="true" data-field="badges" placeholder="<?php esc_attr_e('Badges, e.g. V, GFP (optional)', 'menumatic'); ?>"><?php echo esc_html($item_badges); ?></div>
                                                <div class="item-price editable" contenteditable="true" data-field="price" placeholder="<?php _e('Price', 'menumatic'); ?>"><?php echo esc_html($item['price'] ?? ''); ?></div>
                                                <?php $item_price_options = (!empty($item['price_options']) && is_array($item['price_options'])) ? $item['price_options'] : []; ?>
                                                <div class="item-price-options-editor">
                                                    <div class="price-options-list">
                                                        <?php foreach ($item_price_options as $po): if (!is_array($po)) continue; ?>
                                                            <div class="price-option-row">
                                                                <input type="text" class="price-option-label" placeholder="<?php esc_attr_e('Size (e.g. 20oz)', 'menumatic'); ?>" value="<?php echo esc_attr($po['label'] ?? ''); ?>">
                                                                <input type="text" class="price-option-price" placeholder="<?php esc_attr_e('Price (e.g. $9.00)', 'menumatic'); ?>" value="<?php echo esc_attr($po['price'] ?? ''); ?>">
                                                                <button type="button" class="remove-price-option" title="<?php esc_attr_e('Remove price option', 'menumatic'); ?>">&times;</button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="button" class="button button-small add-price-option"><?php _e('+ Add Price Option', 'menumatic'); ?></button>
                                                    <p class="price-options-hint description"><?php _e('Add size/variant prices (e.g. 20oz $9.00, 16oz $8.00). When set, these replace the single price above.', 'menumatic'); ?></p>
                                                </div>
                                                <div class="item-description editable" contenteditable="true" data-field="description" placeholder="<?php _e('Description', 'menumatic'); ?>"><?php echo esc_html($item['description'] ?? ''); ?></div>
                                                <?php if (!empty($item['additional_info'])): ?>
                                                    <div class="item-additional-info editable" contenteditable="true" data-field="additional_info" placeholder="<?php _e('Additional info', 'menumatic'); ?>"><?php echo esc_html($item['additional_info']); ?></div>
                                                <?php else: ?>
                                                    <div class="item-additional-info editable" contenteditable="true" data-field="additional_info" placeholder="<?php _e('Additional info (optional)', 'menumatic'); ?>" style="display: none;"></div>
                                                <?php endif; ?>
                                                
                                                <div class="dietary-indicators">
                                                    <label><?php _e('Dietary Indicators:', 'menumatic'); ?></label>
                                                    <div class="dietary-checkboxes">
                                                        <?php 
                                                        $indicators = ['vegetarian', 'vegan', 'gluten-free', 'spicy', 'organic', 'local', 'seasonal', 'chef-recommended'];
                                                        $current_indicators = $item['dietary_indicators'] ?? [];
                                                        foreach ($indicators as $indicator): 
                                                        ?>
                                                            <label class="dietary-checkbox">
                                                                <input type="checkbox" name="dietary[]" value="<?php echo esc_attr($indicator); ?>" 
                                                                       <?php checked(in_array($indicator, $current_indicators)); ?>>
                                                                <span><?php echo esc_html(ucfirst(str_replace('-', ' ', $indicator))); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="item-actions">
                                                    <button type="button" class="button button-small toggle-additional-info"><?php _e('Toggle Additional Info', 'menumatic'); ?></button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($content['type'] === 'other_content'): ?>
                            <div class="inline-other-content" data-content-type="<?php echo esc_attr($content['content_type']); ?>" draggable="true">
                                <div class="inline-content-header">
                                    <div class="inline-content-drag-handle" title="<?php _e('Drag to reorder content', 'menumatic'); ?>">
                                        <span class="dashicons dashicons-menu-alt"></span>
                                    </div>
                                    <span class="inline-content-label"><?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $content['content_type']))); ?></span>
                                    <button type="button" class="remove-inline-content" title="<?php _e('Remove from menu area', 'menumatic'); ?>">&times;</button>
                                </div>
                                <div class="inline-content-text editable" contenteditable="true"><?php echo esc_html($content['content']); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="editor-sidebar">
                <h3><?php _e('Other Content', 'menumatic'); ?></h3>
                <div id="other-content-section">
                    <?php if (!empty($sidebar_content)): ?>
                        <?php foreach ($sidebar_content as $content_index => $content): ?>
                            <div class="other-content-item" data-content-index="<?php echo $content_index; ?>" draggable="true">
                                <div class="content-drag-handle" title="<?php _e('Drag to reorder content', 'menumatic'); ?>">
                                    <span class="dashicons dashicons-menu-alt"></span>
                                </div>
                                <select class="content-type" data-field="type">
                                    <option value="restaurant_info" <?php selected($content['type'] ?? '', 'restaurant_info'); ?>><?php _e('Restaurant Info', 'menumatic'); ?></option>
                                    <option value="hours" <?php selected($content['type'] ?? '', 'hours'); ?>><?php _e('Hours', 'menumatic'); ?></option>
                                    <option value="contact" <?php selected($content['type'] ?? '', 'contact'); ?>><?php _e('Contact', 'menumatic'); ?></option>
                                    <option value="special_offer" <?php selected($content['type'] ?? '', 'special_offer'); ?>><?php _e('Special Offer', 'menumatic'); ?></option>
                                    <option value="other" <?php selected($content['type'] ?? '', 'other'); ?>><?php _e('Other', 'menumatic'); ?></option>
                                    <option value="footnote" <?php selected($content['type'] ?? '', 'footnote'); ?>><?php _e('Footnote (below menu)', 'menumatic'); ?></option>
                                </select>
                                <textarea class="content-text editable" data-field="content" placeholder="<?php _e('Content text', 'menumatic'); ?>"><?php echo esc_textarea($content['content'] ?? ''); ?></textarea>
                                <button type="button" class="button button-small remove-other-content"><?php _e('Remove', 'menumatic'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="add-other-content"><?php _e('Add Other Content', 'menumatic'); ?></button>
                
                <div class="editor-help">
                    <h4><?php _e('Editing Tips:', 'menumatic'); ?></h4>
                    <ul>
                        <li><?php _e('Click on any text to edit it directly', 'menumatic'); ?></li>
                        <li><?php _e('Use "Add Item" to add menu items to a section', 'menumatic'); ?></li>
                        <li><?php _e('Use "Add Section" to create new menu categories', 'menumatic'); ?></li>
                        <li><?php _e('Check dietary indicators as needed', 'menumatic'); ?></li>
                        <li><?php _e('Use "Footnote" in Other Content for notes shown under the menu', 'menumatic'); ?></li>
                        <li><?php _e('Save changes before leaving the page', 'menumatic'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
    </div>
    
    <div class="menumatic-editor-footer">
        <a href="<?php echo admin_url('admin.php?page=menumatic-manage'); ?>" class="button">
            <?php _e('Back to Manage Menus', 'menumatic'); ?>
        </a>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    const menuId = <?php echo intval($menu->id); ?>;
    let hasUnsavedChanges = false;
    
    // Convert visual editor to JSON
    function getMenuDataFromEditor() {
        const menuData = {
            content: [],        // Ordered content (sections + inline other content)
            other_content: []   // Sidebar other content
        };
        
        // Helper function to safely extract text and strip HTML
        function safeExtractText($element) {
            if (!$element || $element.length === 0) return '';
            
            // Get text content and strip any HTML tags
            let text = $element.text().trim();
            
            // Remove any extra whitespace and normalize
            text = text.replace(/\s+/g, ' ').trim();
            
            // Clean up any problematic characters that might break JSON
            text = text.replace(/[\u0000-\u001F\u007F-\u009F]/g, ''); // Remove control characters
            text = text.replace(/"/g, '"').replace(/'/g, "'"); // Normalize quotes
            text = text.replace(/\n/g, ' ').replace(/\r/g, ' '); // Remove line breaks
            
            return text;
        }
        
        // Helper function to safely get value from form elements
        function safeGetValue($element) {
            if (!$element || $element.length === 0) return '';
            let value = $element.val() ? $element.val().trim() : '';
            
            // Clean up any problematic characters that might break JSON
            value = value.replace(/[\u0000-\u001F\u007F-\u009F]/g, ''); // Remove control characters
            value = value.replace(/"/g, '"').replace(/'/g, "'"); // Normalize quotes
            
            return value;
        }
        
        try {
            // Extract ordered content (sections and inline other content)
            $('#visual-menu-editor > *').each(function() {
                const $element = $(this);
                
                if ($element.hasClass('menu-section')) {
                    const sectionTitle = safeExtractText($element.find('.section-title'));
                    
                    // Skip empty sections
                    if (!sectionTitle) return;
                    
                    const section = {
                        type: 'section',
                        title: sectionTitle,
                        items: []
                    };

                    const sectionSubtitle = safeExtractText($element.find('.section-subtitle'));
                    if (sectionSubtitle) {
                        section.subtitle = sectionSubtitle;
                    }
                
                    $element.find('.menu-item').each(function() {
                        const $item = $(this);
                        const itemName = safeExtractText($item.find('[data-field="name"]'));
                        
                        // Skip items without names
                        if (!itemName) return;
                        
                        // Collect multiple size/variant prices, if any.
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

                        // Additional info
                        const additionalInfo = safeExtractText($item.find('[data-field="additional_info"]'));
                        if (additionalInfo) {
                            item.additional_info = additionalInfo;
                        }
                        
                        // Badges (short labels such as V or GFP)
                        const badges = safeExtractText($item.find('[data-field="badges"]'));
                        if (badges) {
                            item.badges = badges;
                        }

                        // Item image
                        const $itemImage = $item.find('.item-image');
                        if ($itemImage.length > 0 && $itemImage.attr('src')) {
                            item.image = $itemImage.attr('src');
                        }
                        
                        // Dietary indicators
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
                    
                    // Only add sections that have items
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
            $('#other-content-section .other-content-item').each(function() {
                const $item = $(this);
                const contentType = safeGetValue($item.find('.content-type'));
                const contentText = safeGetValue($item.find('.content-text'));
                
                if (contentType && contentText) {
                    const content = {
                        type: contentType,
                        content: contentText
                    };
                    menuData.other_content.push(content);
                }
            });
            
            // Legacy support: also create sections array for backward compatibility
            menuData.sections = menuData.content.filter(item => item.type === 'section').map(section => ({
                title: section.title,
                items: section.items
            }));
            
            console.log('Generated menu data:', menuData);
            return menuData;
            
        } catch (error) {
            console.error('Error extracting menu data:', error);
            alert('Error extracting menu data: ' + error.message);
            return null;
        }
    }
    
    // Mark as changed
    function markAsChanged() {
        hasUnsavedChanges = true;
    }
    
    // Add new section
    $('#add-section').click(function() {
        const sectionIndex = $('#visual-menu-editor .menu-section').length;
        const sectionHtml = `
            <div class="menu-section" data-section-index="${sectionIndex}" draggable="true">
                <div class="section-drag-handle" title="<?php _e('Drag to reorder sections', 'menumatic'); ?>">
                    <span class="dashicons dashicons-menu-alt"></span>
                </div>
                <h2 class="editable section-title" contenteditable="true" data-field="title">New Section</h2>
                <p class="editable section-subtitle" contenteditable="true" data-field="subtitle" placeholder="<?php esc_attr_e('Section note (optional)', 'menumatic'); ?>"></p>
                
                <div class="section-controls">
                    <button type="button" class="button button-small add-item"><?php _e('Add Item', 'menumatic'); ?></button>
                    <button type="button" class="button button-small remove-section"><?php _e('Remove Section', 'menumatic'); ?></button>
                </div>
                
                <div class="menu-grid">
                </div>
            </div>
        `;
        $('#visual-menu-editor').append(sectionHtml);
        initializeDragAndDrop();
        markAsChanged();
    });
    
    // Add new item to section
    $(document).on('click', '.add-item', function() {
        const $section = $(this).closest('.menu-section');
        const $grid = $section.find('.menu-grid');
        const itemIndex = $grid.find('.menu-item').length;
        
        const itemHtml = `
            <div class="menu-item" data-item-index="${itemIndex}" draggable="true">
                <div class="item-controls">
                    <div class="item-drag-handle" title="<?php _e('Drag to reorder items', 'menumatic'); ?>">
                        <span class="dashicons dashicons-menu-alt"></span>
                    </div>
                    <button type="button" class="remove-item" title="<?php _e('Remove Item', 'menumatic'); ?>">&times;</button>
                </div>
                
                <!-- Item Image Upload -->
                <div class="item-image-section">
                    <div class="item-image-upload">
                        <input type="file" class="item-image-input" accept="image/*" style="display: none;">
                        <button type="button" class="button button-small upload-item-image">
                            <span class="dashicons dashicons-camera"></span>
                            <?php _e('Add Image', 'menumatic'); ?>
                        </button>
                        <div class="image-upload-progress" style="display: none;">
                            <div class="progress-bar"><div class="progress-fill"></div></div>
                            <span class="progress-text"><?php _e('Uploading...', 'menumatic'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="item-name editable" contenteditable="true" data-field="name" placeholder="<?php _e('Item name', 'menumatic'); ?>">New Item</div>
                <div class="item-badges editable" contenteditable="true" data-field="badges" placeholder="<?php esc_attr_e('Badges, e.g. V, GFP (optional)', 'menumatic'); ?>"></div>
                <div class="item-price editable" contenteditable="true" data-field="price" placeholder="<?php _e('Price', 'menumatic'); ?>">$0.00</div>
                <div class="item-price-options-editor">
                    <div class="price-options-list"></div>
                    <button type="button" class="button button-small add-price-option"><?php _e('+ Add Price Option', 'menumatic'); ?></button>
                    <p class="price-options-hint description"><?php _e('Add size/variant prices (e.g. 20oz $9.00, 16oz $8.00). When set, these replace the single price above.', 'menumatic'); ?></p>
                </div>
                <div class="item-description editable" contenteditable="true" data-field="description" placeholder="<?php _e('Description', 'menumatic'); ?>">Description</div>
                <div class="item-additional-info editable" contenteditable="true" data-field="additional_info" placeholder="<?php _e('Additional info (optional)', 'menumatic'); ?>" style="display: none;"></div>
                
                <div class="dietary-indicators">
                    <label><?php _e('Dietary Indicators:', 'menumatic'); ?></label>
                    <div class="dietary-checkboxes">
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="vegetarian"><span>Vegetarian</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="vegan"><span>Vegan</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="gluten-free"><span>Gluten Free</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="spicy"><span>Spicy</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="organic"><span>Organic</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="local"><span>Local</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="seasonal"><span>Seasonal</span></label>
                        <label class="dietary-checkbox"><input type="checkbox" name="dietary[]" value="chef-recommended"><span>Chef Recommended</span></label>
                    </div>
                </div>
                
                <div class="item-actions">
                    <button type="button" class="button button-small toggle-additional-info"><?php _e('Toggle Additional Info', 'menumatic'); ?></button>
                </div>
            </div>
        `;
        $grid.append(itemHtml);
        markAsChanged();
    });
    
    // Remove section
    $(document).on('click', '.remove-section', function() {
        if (confirm('<?php _e('Remove this section?', 'menumatic'); ?>')) {
            $(this).closest('.menu-section').remove();
            markAsChanged();
        }
    });
    
    // Remove item
    $(document).on('click', '.remove-item', function() {
        $(this).closest('.menu-item').remove();
        markAsChanged();
    });

    // Add price option row (size/variant pricing)
    $(document).on('click', '.add-price-option', function() {
        const rowHtml = `
            <div class="price-option-row">
                <input type="text" class="price-option-label" placeholder="<?php esc_attr_e('Size (e.g. 20oz)', 'menumatic'); ?>">
                <input type="text" class="price-option-price" placeholder="<?php esc_attr_e('Price (e.g. $9.00)', 'menumatic'); ?>">
                <button type="button" class="remove-price-option" title="<?php esc_attr_e('Remove price option', 'menumatic'); ?>">&times;</button>
            </div>
        `;
        $(this).closest('.item-price-options-editor').find('.price-options-list').append(rowHtml);
        markAsChanged();
    });

    // Remove price option row
    $(document).on('click', '.remove-price-option', function() {
        $(this).closest('.price-option-row').remove();
        markAsChanged();
    });

    // Track changes on price option inputs
    $(document).on('input', '.price-option-label, .price-option-price', function() {
        markAsChanged();
    });
    
    // Toggle additional info
    $(document).on('click', '.toggle-additional-info', function() {
        const $additionalInfo = $(this).closest('.menu-item').find('.item-additional-info');
        $additionalInfo.toggle();
        markAsChanged();
    });
    
    // Add other content
    $('#add-other-content').click(function() {
        const contentIndex = $('#other-content-section .other-content-item').length;
        const contentHtml = `
            <div class="other-content-item" data-content-index="${contentIndex}" draggable="true">
                <div class="content-drag-handle" title="<?php _e('Drag to reorder content', 'menumatic'); ?>">
                    <span class="dashicons dashicons-menu-alt"></span>
                </div>
                <select class="content-type" data-field="type">
                    <option value="restaurant_info"><?php _e('Restaurant Info', 'menumatic'); ?></option>
                    <option value="hours"><?php _e('Hours', 'menumatic'); ?></option>
                    <option value="contact"><?php _e('Contact', 'menumatic'); ?></option>
                    <option value="special_offer"><?php _e('Special Offer', 'menumatic'); ?></option>
                    <option value="other"><?php _e('Other', 'menumatic'); ?></option>
                    <option value="footnote"><?php _e('Footnote (below menu)', 'menumatic'); ?></option>
                </select>
                <textarea class="content-text editable" data-field="content" placeholder="<?php _e('Content text', 'menumatic'); ?>"></textarea>
                <button type="button" class="button button-small remove-other-content"><?php _e('Remove', 'menumatic'); ?></button>
            </div>
        `;
        $('#other-content-section').append(contentHtml);
        markAsChanged();
    });
    
    // Remove other content
    $(document).on('click', '.remove-other-content', function() {
        $(this).closest('.other-content-item').remove();
        markAsChanged();
    });
    
    // Image upload functionality
    $(document).on('click', '.upload-item-image', function() {
        const $button = $(this);
        const $menuItem = $button.closest('.menu-item');
        const $fileInput = $menuItem.find('.item-image-input');
        
        $fileInput.trigger('click');
    });
    
    $(document).on('change', '.item-image-input', function() {
        const $input = $(this);
        const $menuItem = $input.closest('.menu-item');
        const $uploadSection = $menuItem.find('.item-image-upload');
        const $progress = $menuItem.find('.image-upload-progress');
        const $progressFill = $progress.find('.progress-fill');
        const file = this.files[0];
        
        if (!file) return;
        
        // Validate file type
        if (!file.type.match('image.*')) {
            alert('<?php _e('Please select an image file.', 'menumatic'); ?>');
            return;
        }
        
        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            alert('<?php _e('Image file is too large. Please select a file smaller than 5MB.', 'menumatic'); ?>');
            return;
        }
        
        // Show progress
        $uploadSection.hide();
        $progress.show();
        
        // Create FormData
        const formData = new FormData();
        formData.append('action', 'menumatic_upload_item_image');
        formData.append('nonce', '<?php echo wp_create_nonce('menumatic_item_image_nonce'); ?>');
        formData.append('menu_id', menuId);
        formData.append('item_image', file);
        
        // Upload via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        $progressFill.css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // Add image preview
                    const imageHtml = `
                        <div class="item-image-preview">
                            <img src="${response.data.url}" alt="" class="item-image">
                            <button type="button" class="remove-item-image" title="<?php _e('Remove Image', 'menumatic'); ?>">&times;</button>
                        </div>
                    `;
                    
                    // Remove existing preview if any
                    $menuItem.find('.item-image-preview').remove();
                    
                    // Add new preview
                    $menuItem.find('.item-image-section').prepend(imageHtml);
                    $uploadSection.addClass('has-image');
                    
                    markAsChanged();
                } else {
                    alert('<?php _e('Image upload failed:', 'menumatic'); ?> ' + (response.data || '<?php _e('Unknown error', 'menumatic'); ?>'));
                }
            },
            error: function() {
                alert('<?php _e('Image upload failed. Please try again.', 'menumatic'); ?>');
            },
            complete: function() {
                $progress.hide();
                $uploadSection.show();
                $input.val(''); // Clear file input
            }
        });
    });
    
    // Remove item image
    $(document).on('click', '.remove-item-image', function() {
        if (confirm('<?php _e('Remove this image?', 'menumatic'); ?>')) {
            const $menuItem = $(this).closest('.menu-item');
            $menuItem.find('.item-image-preview').remove();
            $menuItem.find('.item-image-upload').removeClass('has-image');
            markAsChanged();
        }
    });
    
    // Track changes on editable elements
    $(document).on('input blur', '.editable, .content-text, .content-type', function() {
        markAsChanged();
    });
    
    $(document).on('change', '.dietary-checkboxes input', function() {
        markAsChanged();
    });
    
    // Initialize drag and drop functionality
    function initializeDragAndDrop() {
        // Section reordering
        initSectionDragAndDrop();
        
        // Item reordering within sections
        initItemDragAndDrop();
        
        // Other content reordering
        initOtherContentDragAndDrop();
    }
    
    function initSectionDragAndDrop() {
        let draggedSection = null;
        
        // Event listeners for sections
        $(document).off('dragstart.sections dragover.sections drop.sections dragend.sections');
        
        $(document).on('dragstart.sections', '.menu-section', function(e) {
            draggedSection = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
        });
        
        $(document).on('dragover.sections', '.menu-section', function(e) {
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
        
        $(document).on('dragleave.sections', '.menu-section', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.sections', '.menu-section', function(e) {
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
        
        $(document).on('dragend.sections', '.menu-section', function(e) {
            $(this).removeClass('dragging');
            $('.menu-section').removeClass('drag-over-top drag-over-bottom');
            draggedSection = null;
        });
    }
    
    function initItemDragAndDrop() {
        let draggedItem = null;
        
        // Event listeners for items
        $(document).off('dragstart.items dragover.items drop.items dragend.items');
        
        $(document).on('dragstart.items', '.menu-item', function(e) {
            draggedItem = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
        });
        
        $(document).on('dragover.items', '.menu-item', function(e) {
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
        
        $(document).on('dragleave.items', '.menu-item', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.items', '.menu-item', function(e) {
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
        
        $(document).on('dragend.items', '.menu-item', function(e) {
            $(this).removeClass('dragging');
            $('.menu-item').removeClass('drag-over-top drag-over-bottom');
            draggedItem = null;
        });
    }
    
    function initOtherContentDragAndDrop() {
        let draggedContent = null;
        
        // Event listeners for other content
        $(document).off('dragstart.content dragover.content drop.content dragend.content dragover.maindrop drop.maindrop');
        
        $(document).on('dragstart.content', '.other-content-item', function(e) {
            draggedContent = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
            e.originalEvent.dataTransfer.setData('text/content-type', 'other-content');
        });
        
        // Sidebar reordering (existing functionality)
        $(document).on('dragover.content', '.other-content-item', function(e) {
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
        
        $(document).on('dragleave.content', '.other-content-item', function(e) {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });
        
        $(document).on('drop.content', '.other-content-item', function(e) {
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
        $(document).on('dragover.maindrop', '#visual-menu-editor', function(e) {
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
        
        $(document).on('dragleave.maindrop', '#visual-menu-editor', function(e) {
            // Only clear if we're actually leaving the main editor
            if (!$(this)[0].contains(e.relatedTarget)) {
                $(this).find('.menu-section').removeClass('content-drop-before content-drop-after');
            }
        });
        
        $(document).on('drop.maindrop', '#visual-menu-editor', function(e) {
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
                    
                    const inlineContentHtml = `
                        <div class="inline-other-content" data-content-type="${contentType}" draggable="true">
                            <div class="content-drag-handle" title="Drag to reorder content">
                                <span class="dashicons dashicons-menu-alt"></span>
                            </div>
                            <div class="inline-content-header">
                                <strong>${getContentTypeLabel(contentType)}</strong>
                                <button type="button" class="button button-small remove-inline-content">Remove</button>
                            </div>
                            <div class="inline-content-text editable" contenteditable="true" data-field="content">${contentText}</div>
                        </div>
                    `;
                    
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
        
        $(document).on('dragend.content', '.other-content-item', function(e) {
            $(this).removeClass('dragging');
            $('.other-content-item').removeClass('drag-over-top drag-over-bottom');
            $('#visual-menu-editor .menu-section').removeClass('content-drop-before content-drop-after');
            draggedContent = null;
        });
        
        // Handle removal of inline content (move back to sidebar)
        $(document).on('click', '.remove-inline-content', function() {
            const $inlineContent = $(this).closest('.inline-other-content');
            const contentType = $inlineContent.data('content-type');
            const contentText = $inlineContent.find('.inline-content-text').text();
            
            // Add back to sidebar
            const contentIndex = $('#other-content-section .other-content-item').length;
            const sidebarContentHtml = `
                <div class="other-content-item" data-content-index="${contentIndex}" draggable="true">
                    <div class="content-drag-handle" title="Drag to reorder content">
                        <span class="dashicons dashicons-menu-alt"></span>
                    </div>
                    <select class="content-type" data-field="type">
                        <option value="restaurant_info" ${contentType === 'restaurant_info' ? 'selected' : ''}>Restaurant Info</option>
                        <option value="hours" ${contentType === 'hours' ? 'selected' : ''}>Hours</option>
                        <option value="contact" ${contentType === 'contact' ? 'selected' : ''}>Contact</option>
                        <option value="special_offer" ${contentType === 'special_offer' ? 'selected' : ''}>Special Offer</option>
                        <option value="other" ${contentType === 'other' ? 'selected' : ''}>Other</option>
                        <option value="footnote" ${contentType === 'footnote' ? 'selected' : ''}>Footnote (below menu)</option>
                    </select>
                    <textarea class="content-text editable" data-field="content" placeholder="Content text">${contentText}</textarea>
                    <button type="button" class="button button-small remove-other-content">Remove</button>
                </div>
            `;
            
            $('#other-content-section').append(sidebarContentHtml);
            $inlineContent.remove();
            markAsChanged();
        });
        
        // Make inline content draggable back to sidebar
        $(document).on('dragstart', '.inline-other-content', function(e) {
            draggedContent = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/content-type', 'inline-content');
        });
        
        $(document).on('dragover', '#other-content-section', function(e) {
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'inline-content') {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });
        
        $(document).on('drop', '#other-content-section', function(e) {
            if (e.originalEvent.dataTransfer.getData('text/content-type') === 'inline-content' && draggedContent) {
                e.preventDefault();
                
                const $draggedContent = $(draggedContent);
                const contentType = $draggedContent.data('content-type');
                const contentText = $draggedContent.find('.inline-content-text').text();
                
                // Add to sidebar
                const contentIndex = $(this).find('.other-content-item').length;
                const sidebarContentHtml = `
                    <div class="other-content-item" data-content-index="${contentIndex}" draggable="true">
                        <div class="content-drag-handle" title="Drag to reorder content">
                            <span class="dashicons dashicons-menu-alt"></span>
                        </div>
                        <select class="content-type" data-field="type">
                            <option value="restaurant_info" ${contentType === 'restaurant_info' ? 'selected' : ''}>Restaurant Info</option>
                            <option value="hours" ${contentType === 'hours' ? 'selected' : ''}>Hours</option>
                            <option value="contact" ${contentType === 'contact' ? 'selected' : ''}>Contact</option>
                            <option value="special_offer" ${contentType === 'special_offer' ? 'selected' : ''}>Special Offer</option>
                            <option value="other" ${contentType === 'other' ? 'selected' : ''}>Other</option>
                        <option value="footnote" ${contentType === 'footnote' ? 'selected' : ''}>Footnote (below menu)</option>
                        </select>
                        <textarea class="content-text editable" data-field="content" placeholder="Content text">${contentText}</textarea>
                        <button type="button" class="button button-small remove-other-content">Remove</button>
                    </div>
                `;
                
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
    
    // Initialize drag and drop on page load
    initializeDragAndDrop();
    
    // Save changes
    $('#save-menu').click(function() {
        const menuData = getMenuDataFromEditor();
        
        // Check if data extraction was successful
        if (!menuData) {
            return; // Error already shown by getMenuDataFromEditor
        }
        
        // Validate that we have at least one section
        if (!menuData.sections || menuData.sections.length === 0) {
            alert('<?php _e('Please add at least one menu section before saving.', 'menumatic'); ?>');
            return;
        }
        
        let jsonData;
        try {
            // First validate the menuData structure
            if (typeof menuData !== 'object' || menuData === null) {
                throw new Error('Menu data is not a valid object');
            }
            
            // Try to stringify with pretty formatting for debugging
            jsonData = JSON.stringify(menuData, null, 2);
            console.log('JSON to save (formatted):', jsonData);
            
            // Test that it can be parsed back
            const testParse = JSON.parse(jsonData);
            console.log('JSON parse test successful:', testParse);
            
            // Use compact JSON for transmission
            jsonData = JSON.stringify(menuData);
            
        } catch (error) {
            console.error('JSON stringify error:', error);
            console.error('Menu data that failed:', menuData);
            alert('<?php _e('Error creating JSON data: ', 'menumatic'); ?>' + error.message);
            return;
        }
        
        $(this).prop('disabled', true).text('<?php _e('Saving & Updating...', 'menumatic'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'menumatic_regenerate_from_json',
                nonce: '<?php echo wp_create_nonce('menumatic_nonce'); ?>',
                menu_id: menuId,
                json_data: jsonData
            },
            dataType: 'json',
            success: function(response) {
            $('#save-menu').prop('disabled', false).text('<?php _e('Save & Update Menu', 'menumatic'); ?>');
            
            if (response.success) {
                hasUnsavedChanges = false;
                alert('<?php _e('Changes saved and menu updated successfully!', 'menumatic'); ?>');
            } else {
                console.error('Server response:', response);
                alert('<?php _e('Error: ', 'menumatic'); ?>' + response.data);
            }
            },
            error: function(xhr, status, error) {
                $('#save-menu').prop('disabled', false).text('<?php _e('Save & Update Menu', 'menumatic'); ?>');
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('<?php _e('Network error occurred. Please try again.', 'menumatic'); ?>');
            }
        });
    });
    

    
    // Warn about unsaved changes
    $(window).on('beforeunload', function() {
        if (hasUnsavedChanges) {
            return '<?php _e('You have unsaved changes. Are you sure you want to leave?', 'menumatic'); ?>';
        }
    });
});
</script>

<style>
.menumatic-json-editor {
    max-width: none;
}

.menumatic-json-editor-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.json-editor-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.editor-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.json-editor-main {
    display: flex;
    min-height: 600px;
}

.json-editor-panel {
    flex: 1;
    padding: 20px;
    border-right: 1px solid #eee;
}

.json-editor-wrapper {
    position: relative;
}

#json-editor {
    width: 100%;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
    line-height: 1.5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    resize: vertical;
    min-height: 400px;
}

.json-status {
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-valid {
    color: #46b450;
}

.status-invalid {
    color: #dc3232;
    font-weight: bold;
}

.preview-panel {
    flex: 0 0 350px;
    padding: 20px;
    background: #f9f9f9;
    overflow-y: auto;
}

.structure-tree .section-item {
    margin-bottom: 15px;
    padding: 10px;
    background: #fff;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.structure-tree ul {
    margin: 5px 0 0 20px;
    list-style: disc;
}

.structure-tree li {
    margin: 2px 0;
    font-size: 13px;
}

.other-item {
    margin-bottom: 10px;
    padding: 8px;
    background: #fff;
    border-radius: 4px;
    border-left: 4px solid #00a32a;
    font-size: 13px;
}

#current-menu-preview {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #fff;
    font-size: 14px;
}

.json-editor-help {
    padding: 20px;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

.help-content h4 {
    margin-top: 20px;
    margin-bottom: 10px;
}

.help-content pre {
    background: #2c3e50;
    color: #ecf0f1;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.4;
}

.help-content ul {
    margin-left: 20px;
}

.help-content li {
    margin: 5px 0;
}

.help-content code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

.menumatic-editor-footer {
    padding: 20px;
    text-align: center;
    border-top: 1px solid #eee;
}

/* Price Options Editor */
.item-price-options-editor {
    margin: 6px 0 10px;
}

.price-options-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.price-option-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.price-option-row .price-option-label {
    flex: 1 1 45%;
}

.price-option-row .price-option-price {
    flex: 1 1 35%;
}

.price-option-row input[type="text"] {
    font-size: 13px;
    padding: 4px 6px;
}

.price-option-row .remove-price-option {
    flex: 0 0 auto;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #b32d2e;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    width: 26px;
    height: 26px;
}

.price-option-row .remove-price-option:hover {
    background: #b32d2e;
    border-color: #b32d2e;
    color: #fff;
}

.item-price-options-editor .add-price-option {
    margin-top: 6px;
}

.price-options-hint {
    margin: 6px 0 0;
    font-size: 11px;
    color: #777;
}

/* Drag and Drop Styles */
.section-drag-handle,
.item-drag-handle,
.content-drag-handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: grab;
    margin-right: 8px;
    color: #666;
    transition: all 0.2s ease;
}

.section-drag-handle:hover,
.item-drag-handle:hover,
.content-drag-handle:hover {
    background: #007cba;
    color: white;
    border-color: #005a87;
}

.section-drag-handle:active,
.item-drag-handle:active,
.content-drag-handle:active {
    cursor: grabbing;
}

.section-drag-handle {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;
}

/* Drag feedback styles */
.dragging {
    opacity: 0.6;
    transform: rotate(2deg);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    z-index: 1000;
    position: relative;
}

.drag-over-top {
    border-top: 3px solid #007cba !important;
}

.drag-over-bottom {
    border-bottom: 3px solid #007cba !important;
}

.menu-section.dragging {
    border-color: #007cba;
    background: rgba(0, 124, 186, 0.1);
}

.menu-item.dragging {
    border-color: #007cba;
    background: rgba(0, 124, 186, 0.1);
}

.other-content-item.dragging {
    border-color: #007cba;
    background: rgba(0, 124, 186, 0.1);
}

/* Inline Other Content Styles */
.inline-other-content {
    margin: 20px 0;
    padding: 20px;
    border: 2px solid #e67e22;
    border-radius: 8px;
    background: #fdf2e9;
    position: relative;
}

.inline-content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e67e22;
}

.inline-content-header strong {
    color: #d35400;
    font-size: 16px;
}

.inline-content-text {
    min-height: 50px;
    padding: 10px;
    border: 1px solid #e67e22;
    border-radius: 4px;
    background: white;
    line-height: 1.5;
}

.inline-content-text:hover,
.inline-content-text:focus {
    border-color: #d35400;
    outline: none;
}

/* Drop zone indicators */
.content-drop-before {
    border-top: 4px solid #e67e22 !important;
    box-shadow: 0 -4px 8px rgba(230, 126, 34, 0.3) !important;
}

.content-drop-after {
    border-bottom: 4px solid #e67e22 !important;
    box-shadow: 0 4px 8px rgba(230, 126, 34, 0.3) !important;
}

.inline-other-content.dragging {
    border-color: #e67e22;
    background: rgba(230, 126, 34, 0.1);
}

@media (max-width: 1200px) {
    .json-editor-main {
        flex-direction: column;
    }
    
    .json-editor-panel {
        border-right: none;
        border-bottom: 1px solid #eee;
    }
    
    .preview-panel {
        flex: none;
    }
}

@media (max-width: 768px) {
    .json-editor-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .editor-actions {
        justify-content: center;
    }
}
</style> 