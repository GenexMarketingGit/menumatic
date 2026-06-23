<?php
/**
 * Admin Settings Page
 * 
 * @package MenuMatic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && isset($_POST['menumatic_settings_nonce']) && wp_verify_nonce($_POST['menumatic_settings_nonce'], 'menumatic_settings')) {
    $settings = $this->save_settings($_POST);
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'menumatic') . '</p></div>';
} else {
    $settings = $this->get_settings();
}

$providers = $this->get_supported_ai_providers();
$openai_models = $this->get_supported_openai_models();
$openrouter_models = $this->get_supported_openrouter_models();
$style_library = $this->get_menu_style_library();
$current_provider = $settings['ai_provider'] ?? 'openai';
$current_openai_model = $settings['openai_model'] ?? 'gpt-5.4-mini';
$current_openrouter_model = $settings['openrouter_model'] ?? 'mistralai/ministral-8b-2512';
$openai_key_mask = $this->get_masked_secret_label($settings['openai_api_key'] ?? '');
$openrouter_key_mask = $this->get_masked_secret_label($settings['openrouter_api_key'] ?? '');

$file_types = array('jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp');
?>

<div class="wrap menumatic-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('menumatic_settings', 'menumatic_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ai_provider"><?php _e('AI Provider', 'menumatic'); ?></label>
                </th>
                <td>
                    <select id="ai_provider" name="ai_provider">
                        <?php foreach ($providers as $provider_value => $provider_label): ?>
                            <option value="<?php echo esc_attr($provider_value); ?>" <?php selected($current_provider, $provider_value); ?>>
                                <?php echo esc_html($provider_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Choose which provider handles menu image analysis and HTML extraction.', 'menumatic'); ?>
                    </p>
                </td>
            </tr>

            <tr class="menumatic-provider-row" data-provider="openai">
                <th scope="row">
                    <label for="openai_api_key"><?php _e('OpenAI API Key', 'menumatic'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="openai_api_key"
                           name="openai_api_key"
                           value=""
                           class="regular-text"
                           placeholder="sk-..."
                           autocomplete="new-password" />
                    <p class="description">
                        <?php printf(
                            __('Get your API key from <a href="%s" target="_blank" rel="noopener noreferrer">OpenAI Platform</a>. Leave blank to keep the currently saved key.', 'menumatic'),
                            'https://platform.openai.com/api-keys'
                        ); ?>
                    </p>
                    <?php if (!empty($openai_key_mask)): ?>
                        <p class="description" style="color: green;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html(sprintf(__('Saved key: %s', 'menumatic'), $openai_key_mask)); ?>
                        </p>
                        <label>
                            <input type="checkbox" name="clear_openai_api_key" value="1" />
                            <?php _e('Clear saved OpenAI key', 'menumatic'); ?>
                        </label>
                    <?php endif; ?>
                </td>
            </tr>

            <tr class="menumatic-provider-row" data-provider="openai">
                <th scope="row">
                    <label for="openai_model"><?php _e('OpenAI Model', 'menumatic'); ?></label>
                </th>
                <td>
                    <select id="openai_model" name="openai_model">
                        <?php foreach ($openai_models as $model_value => $model_label): ?>
                            <option value="<?php echo esc_attr($model_value); ?>" <?php selected($current_openai_model, $model_value); ?>>
                                <?php echo esc_html($model_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Used for connection tests and menu processing when OpenAI is the selected provider.', 'menumatic'); ?>
                    </p>
                </td>
            </tr>

            <tr class="menumatic-provider-row" data-provider="openrouter">
                <th scope="row">
                    <label for="openrouter_api_key"><?php _e('OpenRouter API Key', 'menumatic'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="openrouter_api_key"
                           name="openrouter_api_key"
                           value=""
                           class="regular-text"
                           placeholder="sk-or-v1-..."
                           autocomplete="new-password" />
                    <p class="description">
                        <?php printf(
                            __('Get your API key from <a href="%s" target="_blank" rel="noopener noreferrer">OpenRouter</a>. Leave blank to keep the currently saved key.', 'menumatic'),
                            'https://openrouter.ai/keys'
                        ); ?>
                    </p>
                    <?php if (!empty($openrouter_key_mask)): ?>
                        <p class="description" style="color: green;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html(sprintf(__('Saved key: %s', 'menumatic'), $openrouter_key_mask)); ?>
                        </p>
                        <label>
                            <input type="checkbox" name="clear_openrouter_api_key" value="1" />
                            <?php _e('Clear saved OpenRouter key', 'menumatic'); ?>
                        </label>
                    <?php endif; ?>
                </td>
            </tr>

            <tr class="menumatic-provider-row" data-provider="openrouter">
                <th scope="row">
                    <label for="openrouter_model"><?php _e('OpenRouter Model', 'menumatic'); ?></label>
                </th>
                <td>
                    <select id="openrouter_model" name="openrouter_model">
                        <?php foreach ($openrouter_models as $model_value => $model_label): ?>
                            <option value="<?php echo esc_attr($model_value); ?>" <?php selected($current_openrouter_model, $model_value); ?>>
                                <?php echo esc_html($model_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Used for connection tests and menu processing when OpenRouter is the selected provider.', 'menumatic'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- File Upload Settings -->
            <tr>
                <th scope="row">
                    <label for="default_menu_style_id"><?php _e('Default Menu Style', 'menumatic'); ?></label>
                </th>
                <td>
                    <select id="default_menu_style_id" name="default_menu_style_id">
                        <?php foreach ($style_library as $style_id => $style_definition): ?>
                            <option value="<?php echo esc_attr($style_id); ?>" <?php selected($settings['default_menu_style_id'] ?? 'preset-modern', $style_id); ?>>
                                <?php echo esc_html($style_definition['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Used as the starting style for new menus unless a different style is chosen during upload.', 'menumatic'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_file_size"><?php _e('Maximum File Size (MB)', 'menumatic'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_file_size" 
                           name="max_file_size" 
                           value="<?php echo esc_attr($settings['max_file_size'] ?? 5); ?>" 
                           min="1" 
                           max="50" 
                           class="small-text" />
                    <p class="description">
                        <?php _e('Maximum allowed file size for menu uploads. Server max: ', 'menumatic'); ?>
                        <?php echo size_format(wp_max_upload_size()); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Allowed File Types', 'menumatic'); ?>
                </th>
                <td>
                    <fieldset>
                        <?php foreach ($file_types as $type): ?>
                            <label>
                                <input type="checkbox" 
                                       name="allowed_file_types[]" 
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked(in_array($type, $settings['allowed_file_types'] ?? [])); ?> />
                                <?php echo strtoupper($type); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description">
                        <?php _e('Select which image file types are allowed for menu uploads.', 'menumatic'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Performance Settings -->
            <tr>
                <th scope="row">
                    <?php _e('Performance', 'menumatic'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="enable_caching" 
                                   value="1"
                                   <?php checked($settings['enable_caching'] ?? false); ?> />
                            <?php _e('Enable menu caching', 'menumatic'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Cache processed menus to improve loading times.', 'menumatic'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <!-- AI Prompt Template -->
            <tr>
                <th scope="row">
                    <label for="ai_prompt_template"><?php _e('AI Prompt Template', 'menumatic'); ?></label>
                </th>
                <td>
                    <textarea id="ai_prompt_template" 
                              name="ai_prompt_template" 
                              rows="8" 
                              cols="50" 
                              class="large-text"
                              placeholder="<?php esc_attr_e('Leave empty to use default prompt...', 'menumatic'); ?>"><?php echo esc_textarea($settings['ai_prompt_template'] ?? ''); ?></textarea>
                    <p class="description">
                        <?php _e('Custom prompt template for AI processing. Leave empty to use the default optimized prompt. Use {image} as placeholder for the uploaded image.', 'menumatic'); ?>
                    </p>
                    <details>
                        <summary><?php _e('Default Prompt Template', 'menumatic'); ?></summary>
                        <code style="display: block; white-space: pre-wrap; margin-top: 10px; padding: 10px; background: #f0f0f0;">
<?php echo esc_html('Please analyze this menu image and convert it into a clean, organized HTML structure. Format the output as a complete HTML menu with the following requirements:

1. Use semantic HTML5 structure
2. Include proper headings for menu sections
3. Format items with names, descriptions, and prices
4. Use CSS classes for styling (menu-section, menu-item, item-name, item-description, item-price)
5. Make it mobile-responsive
6. Include any special dietary indicators (vegetarian, vegan, gluten-free, etc.)
7. Preserve the original menu structure and groupings

Return only the HTML code without any explanations or markdown formatting.'); ?>
                        </code>
                    </details>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <!-- API Key Test Section -->
    <?php if ($this->is_ai_configured($settings)): ?>
    <div class="menumatic-api-test">
        <h2><?php _e('API Connection Test', 'menumatic'); ?></h2>
        <p><?php echo esc_html(sprintf(__('Test your %s connection:', 'menumatic'), $this->get_ai_provider_label($current_provider))); ?></p>
        <button type="button" id="test-api-connection" class="button button-secondary">
            <?php _e('Test Connection', 'menumatic'); ?>
        </button>
        <div id="api-test-result" style="margin-top: 10px;"></div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    function toggleProviderRows() {
        var selectedProvider = $('#ai_provider').val();
        $('.menumatic-provider-row').hide();
        $('.menumatic-provider-row[data-provider="' + selectedProvider + '"]').show();
    }

    $('#ai_provider').on('change', toggleProviderRows);
    toggleProviderRows();

    $('#test-api-connection').on('click', function() {
        var button = $(this);
        var result = $('#api-test-result');
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'menumatic')); ?>');
        result.html('');
        
        $.post(ajaxurl, {
            action: 'menumatic_test_api',
            nonce: '<?php echo wp_create_nonce('menumatic_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
            } else {
                result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'menumatic')); ?>');
        });
    });
});
</script> 
