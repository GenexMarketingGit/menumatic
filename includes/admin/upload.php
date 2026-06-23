<?php
/**
 * Admin Upload Page
 * 
 * @package MenuMatic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get settings
$settings = $this->get_settings();
$default_style_id = $settings['default_menu_style_id'] ?? 'preset-modern';
$provider_config = $this->get_ai_provider_config($settings);
$api_key_configured = $this->is_ai_configured($settings);
$max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 5;
$allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : array('jpg', 'jpeg', 'png', 'webp');
$style_library = $this->get_menu_style_library();
?>

<div class="wrap menumatic-upload">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!$api_key_configured): ?>
        <div class="notice notice-error">
            <p>
                <?php echo esc_html(sprintf(__('%s API key is required to process menus.', 'menumatic'), $provider_config['provider_label'])); ?>
                <a href="<?php echo admin_url('admin.php?page=menumatic-settings'); ?>" class="button-secondary">
                    <?php _e('Configure Settings', 'menumatic'); ?>
                </a>
            </p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="menumatic-upload-container">
        <!-- Upload Form -->
        <div class="menumatic-upload-form">
            <form id="menumatic-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('menumatic_upload', 'menumatic_upload_nonce'); ?>
                
                <div class="upload-section">
                    <h2><?php _e('Upload Menu Image', 'menumatic'); ?></h2>
                    <p class="description">
                        <?php printf(
                            __('Upload a clear photo of your menu. Supported formats: %s. Max size: %dMB', 'menumatic'),
                            strtoupper(implode(', ', $allowed_types)),
                            $max_file_size
                        ); ?>
                    </p>
                    
                    <!-- Drag & Drop Upload Area -->
                    <div class="menumatic-dropzone" id="menumatic-dropzone">
                        <div class="dropzone-content">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <h3><?php _e('Drop your menu image here', 'menumatic'); ?></h3>
                            <p><?php _e('or click to select a file', 'menumatic'); ?></p>
                            <input type="file" 
                                   id="menu_image" 
                                   name="menu_image" 
                                   accept="<?php echo esc_attr(implode(',', array_map(function($type) { return 'image/' . $type; }, $allowed_types))); ?>" 
                                   style="display: none;" />
                            <br><br>
                            <button type="button" class="button button-secondary" id="select-file-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Select File', 'menumatic'); ?>
                            </button>
                        </div>
                        <div class="dropzone-preview" id="image-preview" style="display: none;">
                            <img id="preview-image" src="" alt="Preview" />
                            <button type="button" class="remove-image" id="remove-image">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="menu-details-section">
                    <h3><?php _e('Menu Details', 'menumatic'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="menu_title"><?php _e('Menu Title', 'menumatic'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="menu_title" 
                                       name="menu_title" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('e.g., Main Menu, Lunch Menu, Dinner Menu', 'menumatic'); ?>" 
                                       required />
                                <p class="description">
                                    <?php _e('Give your menu a descriptive title.', 'menumatic'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="menu_style"><?php _e('Style', 'menumatic'); ?></label>
                            </th>
                            <td>
                                <select id="menu_style" name="menu_style">
                                    <?php foreach ($style_library as $style_id => $style_definition): ?>
                                        <option value="<?php echo esc_attr($style_id); ?>" <?php selected($default_style_id, $style_id); ?>>
                                            <?php echo esc_html($style_definition['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Choose the starting visual treatment for this menu. You can refine it later with custom CSS in the editor.', 'menumatic'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php _e('Processing Options', 'menumatic'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="extract_allergens" value="1" checked />
                                        <?php _e('Extract allergen information', 'menumatic'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="detect_dietary" value="1" checked />
                                        <?php _e('Detect dietary indicators (vegan, vegetarian, etc.)', 'menumatic'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="preserve_formatting" value="1" checked />
                                        <?php _e('Preserve original menu structure', 'menumatic'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="submit-section">
                    <button type="submit" class="button button-primary button-large" id="process-menu-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Process Menu with AI', 'menumatic'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Processing Status -->
        <div id="processing-status" class="menumatic-processing-status" style="display: none;">
            <div class="processing-content">
                <div class="processing-spinner">
                    <div class="spinner is-active"></div>
                </div>
                <h3 id="processing-title"><?php _e('Processing your menu...', 'menumatic'); ?></h3>
                <p id="processing-description"><?php _e('This may take a few moments while our AI analyzes your menu image.', 'menumatic'); ?></p>
                <div class="processing-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <span class="progress-text" id="progress-text">0%</span>
                </div>
            </div>
        </div>
        
        <!-- Results Section -->
        <div id="processing-results" class="menumatic-results" style="display: none;">
            <h2><?php _e('Processing Complete!', 'menumatic'); ?></h2>
            <div class="results-content">
                <div class="menu-preview" id="menu-preview">
                    <!-- AI-generated menu will be displayed here -->
                </div>
                <div class="results-actions">
                    <button type="button" class="button button-primary" id="save-menu-btn">
                        <?php _e('Save Menu', 'menumatic'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="edit-menu-btn">
                        <?php _e('Edit Menu', 'menumatic'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="try-again-btn">
                        <?php _e('Try Again', 'menumatic'); ?>
                    </button>
                </div>
                <div class="shortcode-info">
                    <h4><?php _e('Display your menu:', 'menumatic'); ?></h4>
                    <code id="menu-shortcode">[menumatic_display name=""]</code>
                    <button type="button" class="button button-small" id="copy-shortcode">
                        <?php _e('Copy Shortcode', 'menumatic'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Tips -->
    <div class="menumatic-upload-tips">
        <h3><?php _e('Tips for Best Results', 'menumatic'); ?></h3>
        <ul>
            <li><?php _e('Ensure the menu text is clearly visible and well-lit', 'menumatic'); ?></li>
            <li><?php _e('Take the photo straight-on to avoid distortion', 'menumatic'); ?></li>
            <li><?php _e('Make sure all menu sections are included in the image', 'menumatic'); ?></li>
            <li><?php _e('Use high resolution images for better text recognition', 'menumatic'); ?></li>
            <li><?php _e('Avoid shadows or glare that might obscure text', 'menumatic'); ?></li>
            <li><strong><?php _e('Large/Complex Menus:', 'menumatic'); ?></strong> <?php _e('For very large menus, our system automatically uses advanced chunking technology to process multiple sections separately and then combines them with a secondary validation check to ensure nothing is missed', 'menumatic'); ?></li>
            <li><strong><?php _e('Memory Optimization:', 'menumatic'); ?></strong> <?php _e('For extremely large images (50+ megapixels), the system automatically optimizes memory usage and may use alternative processing methods. If you encounter memory errors, try reducing the image size or resolution', 'menumatic'); ?></li>
        </ul>
    </div>
</div> 
