<?php
/**
 * Admin Styles Page
 *
 * @package MenuMatic
 */

if (!defined('ABSPATH')) {
    exit;
}

$notice_message = '';
$notice_type = 'success';
$editing_style_id = sanitize_key($_GET['edit_style'] ?? '');
$settings = $this->get_settings();
$current_default_style_id = $settings['default_menu_style_id'] ?? 'preset-modern';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['menumatic_style_nonce']) &&
    wp_verify_nonce($_POST['menumatic_style_nonce'], 'menumatic_style_action')
) {
    $action = sanitize_key($_POST['menumatic_style_action'] ?? '');

    if ($action === 'save_style') {
        $saved_style = $this->upsert_custom_menu_style(array(
            'id' => sanitize_key($_POST['style_id'] ?? ''),
            'name' => sanitize_text_field($_POST['style_name'] ?? ''),
            'description' => sanitize_text_field($_POST['style_description'] ?? ''),
            'preset_slug' => sanitize_key($_POST['preset_slug'] ?? 'modern'),
            'layout' => $_POST['layout'] ?? array(),
            'custom_css' => trim((string) wp_unslash($_POST['custom_css'] ?? '')),
        ));

        $editing_style_id = $saved_style['id'];
        $notice_message = __('Style saved successfully.', 'menumatic');
    } elseif ($action === 'set_default_style') {
        $selected_style_id = $this->normalize_menu_style_id($_POST['style_id'] ?? $current_default_style_id);
        $stored_settings = get_option('menumatic_settings', array());
        if (!is_array($stored_settings)) {
            $stored_settings = array();
        }

        $stored_settings['default_menu_style_id'] = $selected_style_id;
        $stored_settings['default_menu_style'] = $this->get_menu_style_definition($selected_style_id)['preset_slug'];
        update_option('menumatic_settings', $stored_settings);

        $settings = $this->get_settings();
        $current_default_style_id = $settings['default_menu_style_id'] ?? 'preset-modern';
        $notice_message = __('Global default style updated.', 'menumatic');
    } elseif ($action === 'delete_style') {
        $style_id = sanitize_key($_POST['style_id'] ?? '');
        $custom_styles = $this->get_custom_menu_styles();

        if ($style_id && isset($custom_styles[$style_id])) {
            $this->delete_custom_menu_style($style_id);
            if ($current_default_style_id === $style_id) {
                $stored_settings = get_option('menumatic_settings', array());
                if (!is_array($stored_settings)) {
                    $stored_settings = array();
                }
                $stored_settings['default_menu_style_id'] = 'preset-modern';
                $stored_settings['default_menu_style'] = 'modern';
                update_option('menumatic_settings', $stored_settings);
                $settings = $this->get_settings();
                $current_default_style_id = $settings['default_menu_style_id'] ?? 'preset-modern';
            }
            if ($editing_style_id === $style_id) {
                $editing_style_id = '';
            }
            $notice_message = __('Style deleted successfully.', 'menumatic');
        } else {
            $notice_type = 'error';
            $notice_message = __('That style could not be deleted.', 'menumatic');
        }
    }
}

$style_presets = $this->get_menu_style_presets();
$preset_css_templates = $this->get_builtin_menu_style_css_templates();
$layout_labels = $this->get_menu_layout_option_labels();
$builtin_styles = $this->get_builtin_menu_styles();
$custom_styles = $this->get_custom_menu_styles();
$style_library = $this->get_menu_style_library();
$custom_only_styles = array_filter($custom_styles, function($style) {
    return empty($style['is_builtin']);
});
$new_style_defaults = array(
    'id' => '',
    'name' => '',
    'description' => '',
    'preset_slug' => 'modern',
    'layout' => $this->get_default_menu_layout_options(),
    'custom_css' => $this->get_builtin_menu_style_css_template('modern'),
    'is_builtin' => false,
);
$editing_style = $new_style_defaults;

if ($editing_style_id && isset($style_library[$editing_style_id])) {
    $editing_style = $style_library[$editing_style_id];
}
?>

<div class="wrap menumatic-styles-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p class="description">
        <?php _e('Create reusable menu styles with layout defaults and CSS. Menus can still add their own per-menu CSS overrides on top of these styles.', 'menumatic'); ?>
    </p>
    <p>
        <strong><?php _e('Global default style:', 'menumatic'); ?></strong>
        <?php echo esc_html($style_library[$current_default_style_id]['name'] ?? __('Unknown', 'menumatic')); ?>
    </p>

    <?php if ($notice_message): ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible">
            <p><?php echo esc_html($notice_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="menumatic-style-layout">
        <div class="menumatic-style-sidebar">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Built-in Styles', 'menumatic'); ?></h2>
                </div>
                <div class="inside">
                    <div class="menumatic-style-card-grid">
                        <?php foreach ($builtin_styles as $style): ?>
                            <?php $is_overridden = isset($custom_styles[$style['id']]); ?>
                            <?php $display_style = $style_library[$style['id']] ?? $style; ?>
                            <article class="menumatic-style-card is-builtin">
                                <div class="menumatic-style-card-head">
                                    <h3><?php echo esc_html($display_style['name']); ?></h3>
                                    <div class="menumatic-style-card-badges">
                                        <span class="menumatic-style-badge"><?php echo $is_overridden ? esc_html__('Edited Preset', 'menumatic') : esc_html__('Built-in', 'menumatic'); ?></span>
                                        <?php if ($current_default_style_id === $style['id']): ?>
                                            <span class="menumatic-style-badge is-default"><?php _e('Global Default', 'menumatic'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p><?php echo esc_html($display_style['description']); ?></p>
                                <div class="menumatic-style-meta">
                                    <span><?php echo esc_html($layout_labels['columns'][$display_style['layout']['columns']] ?? ''); ?></span>
                                    <span><?php echo esc_html($layout_labels['price_position'][$display_style['layout']['price_position']] ?? ''); ?></span>
                                    <span><?php echo esc_html($layout_labels['text_layout'][$display_style['layout']['text_layout']] ?? ''); ?></span>
                                </div>
                                <div class="menumatic-style-card-actions">
                                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=menumatic-styles&edit_style=' . $style['id'])); ?>">
                                        <?php _e('Edit', 'menumatic'); ?>
                                    </a>
                                    <?php if ($current_default_style_id !== $style['id']): ?>
                                        <form method="post" action="">
                                            <?php wp_nonce_field('menumatic_style_action', 'menumatic_style_nonce'); ?>
                                            <input type="hidden" name="menumatic_style_action" value="set_default_style" />
                                            <input type="hidden" name="style_id" value="<?php echo esc_attr($style['id']); ?>" />
                                            <button type="submit" class="button button-secondary">
                                                <?php _e('Set Global', 'menumatic'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($is_overridden): ?>
                                        <form method="post" action="">
                                            <?php wp_nonce_field('menumatic_style_action', 'menumatic_style_nonce'); ?>
                                            <input type="hidden" name="menumatic_style_action" value="delete_style" />
                                            <input type="hidden" name="style_id" value="<?php echo esc_attr($style['id']); ?>" />
                                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Reset this preset back to its built-in version?', 'menumatic')); ?>');">
                                                <?php _e('Reset', 'menumatic'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Custom Styles', 'menumatic'); ?></h2>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=menumatic-styles')); ?>">
                        <?php _e('Add New', 'menumatic'); ?>
                    </a>
                </div>
                <div class="inside">
                    <?php if (empty($custom_only_styles)): ?>
                        <p><?php _e('No custom styles yet. Create one from the editor on the right.', 'menumatic'); ?></p>
                    <?php else: ?>
                        <div class="menumatic-style-card-grid">
                            <?php foreach ($custom_only_styles as $style): ?>
                                <article class="menumatic-style-card <?php echo $editing_style_id === $style['id'] ? 'is-active' : ''; ?>">
                                    <div class="menumatic-style-card-head">
                                        <h3><?php echo esc_html($style['name']); ?></h3>
                                        <div class="menumatic-style-card-badges">
                                            <span class="menumatic-style-badge"><?php echo esc_html($style_presets[$style['preset_slug']]['label'] ?? ucfirst($style['preset_slug'])); ?></span>
                                            <?php if ($current_default_style_id === $style['id']): ?>
                                                <span class="menumatic-style-badge is-default"><?php _e('Global Default', 'menumatic'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p><?php echo esc_html($style['description'] ?: __('No description yet.', 'menumatic')); ?></p>
                                    <div class="menumatic-style-meta">
                                        <span><?php echo esc_html($layout_labels['columns'][$style['layout']['columns']] ?? ''); ?></span>
                                        <span><?php echo esc_html($layout_labels['price_position'][$style['layout']['price_position']] ?? ''); ?></span>
                                        <span><?php echo esc_html($layout_labels['text_layout'][$style['layout']['text_layout']] ?? ''); ?></span>
                                    </div>
                                    <div class="menumatic-style-card-actions">
                                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=menumatic-styles&edit_style=' . $style['id'])); ?>">
                                            <?php _e('Edit', 'menumatic'); ?>
                                        </a>
                                        <?php if ($current_default_style_id !== $style['id']): ?>
                                            <form method="post" action="">
                                                <?php wp_nonce_field('menumatic_style_action', 'menumatic_style_nonce'); ?>
                                                <input type="hidden" name="menumatic_style_action" value="set_default_style" />
                                                <input type="hidden" name="style_id" value="<?php echo esc_attr($style['id']); ?>" />
                                                <button type="submit" class="button button-secondary">
                                                    <?php _e('Set Global', 'menumatic'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="">
                                            <?php wp_nonce_field('menumatic_style_action', 'menumatic_style_nonce'); ?>
                                            <input type="hidden" name="menumatic_style_action" value="delete_style" />
                                            <input type="hidden" name="style_id" value="<?php echo esc_attr($style['id']); ?>" />
                                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this style?', 'menumatic')); ?>');">
                                                <?php _e('Delete', 'menumatic'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="menumatic-style-editor-column">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php echo $editing_style['id'] ? esc_html__('Edit Style', 'menumatic') : esc_html__('Create Style', 'menumatic'); ?></h2>
                </div>
                <div class="inside">
                    <?php if (!empty($editing_style['is_builtin'])): ?>
                        <p class="description"><?php _e('Saving changes here updates this built-in preset everywhere it is selected. Use Reset on the preset card to go back to the bundled version.', 'menumatic'); ?></p>
                    <?php endif; ?>
                    <form method="post" action="" class="menumatic-style-form">
                        <?php wp_nonce_field('menumatic_style_action', 'menumatic_style_nonce'); ?>
                        <input type="hidden" name="menumatic_style_action" value="save_style" />
                        <input type="hidden" name="style_id" value="<?php echo esc_attr($editing_style['id']); ?>" />

                        <div class="menumatic-style-tabs">
                            <button type="button" class="menumatic-style-tab is-active" data-tab="options"><?php _e('Options', 'menumatic'); ?></button>
                            <button type="button" class="menumatic-style-tab" data-tab="css"><?php _e('CSS', 'menumatic'); ?></button>
                        </div>

                        <div class="menumatic-style-panel is-active" data-panel="options">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="style_name"><?php _e('Style Name', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="style_name" name="style_name" class="regular-text" value="<?php echo esc_attr($editing_style['name']); ?>" required />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="style_description"><?php _e('Description', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="style_description" name="style_description" class="regular-text" value="<?php echo esc_attr($editing_style['description']); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="preset_slug"><?php _e('Base Preset', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <select id="preset_slug" name="preset_slug">
                                            <?php foreach ($style_presets as $preset_slug => $preset): ?>
                                                <?php if ($preset_slug === 'custom') continue; ?>
                                                <option value="<?php echo esc_attr($preset_slug); ?>" <?php selected($editing_style['preset_slug'], $preset_slug); ?>>
                                                    <?php echo esc_html($preset['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php _e('This supplies the base visual palette and typography before your custom CSS runs.', 'menumatic'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="layout_columns"><?php _e('Columns', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <select id="layout_columns" name="layout[columns]">
                                            <?php foreach ($layout_labels['columns'] as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing_style['layout']['columns'], $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="layout_price_position"><?php _e('Price Position', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <select id="layout_price_position" name="layout[price_position]">
                                            <?php foreach ($layout_labels['price_position'] as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing_style['layout']['price_position'], $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="layout_text_layout"><?php _e('Name / Description Layout', 'menumatic'); ?></label>
                                    </th>
                                    <td>
                                        <select id="layout_text_layout" name="layout[text_layout]">
                                            <?php foreach ($layout_labels['text_layout'] as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing_style['layout']['text_layout'], $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="menumatic-style-panel" data-panel="css">
                            <p class="description"><?php _e('Edit the full reusable stylesheet for this global menu style here. Use `{{scope}}` to target the menu wrapper directly, including borders, radii, typography, spacing, and any preset-specific rules.', 'menumatic'); ?></p>
                            <textarea name="custom_css" rows="24" class="large-text code"><?php echo esc_textarea($editing_style['custom_css']); ?></textarea>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Save Style', 'menumatic'); ?></button>
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=menumatic-styles')); ?>">
                                <?php _e('Create New Style', 'menumatic'); ?>
                            </a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.menumatic-style-tab');
    var styleForm = document.querySelector('.menumatic-style-form');
    var presetSelect = document.getElementById('preset_slug');
    var cssField = styleForm ? styleForm.querySelector('textarea[name="custom_css"]') : null;
    var cssTemplates = <?php echo wp_json_encode($preset_css_templates); ?>;
    var lastPresetValue = presetSelect ? presetSelect.value : 'modern';

    function normalizeCssValue(value) {
        return String(value || '').replace(/\r\n/g, '\n').trim();
    }

    function getPresetTemplate(slug) {
        return cssTemplates[slug] || cssTemplates.modern || '';
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var container = tab.closest('.menumatic-style-form');
            var target = tab.getAttribute('data-tab');

            container.querySelectorAll('.menumatic-style-tab').forEach(function(button) {
                button.classList.remove('is-active');
            });

            container.querySelectorAll('.menumatic-style-panel').forEach(function(panel) {
                panel.classList.remove('is-active');
            });

            tab.classList.add('is-active');
            container.querySelector('.menumatic-style-panel[data-panel="' + target + '"]').classList.add('is-active');
        });
    });

    if (presetSelect && cssField) {
        if (normalizeCssValue(cssField.value) === '') {
            cssField.value = getPresetTemplate(presetSelect.value);
        }

        presetSelect.addEventListener('change', function() {
            var currentValue = normalizeCssValue(cssField.value);
            var previousTemplate = normalizeCssValue(getPresetTemplate(lastPresetValue));
            var matchesBuiltinTemplate = Object.keys(cssTemplates).some(function(key) {
                return normalizeCssValue(cssTemplates[key]) === currentValue;
            });

            if (currentValue === '' || currentValue === previousTemplate || matchesBuiltinTemplate) {
                cssField.value = getPresetTemplate(presetSelect.value);
            }

            lastPresetValue = presetSelect.value;
        });
    }
});
</script>
