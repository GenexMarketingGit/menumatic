<?php
/**
 * Admin Manage Menus Page
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$menu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'delete' && $menu_id && wp_verify_nonce($_GET['nonce'], 'delete_menu_' . $menu_id)) {
    $wpdb->delete($table_name, array('id' => $menu_id), array('%d'));
    echo '<div class="notice notice-success"><p>' . __('Menu deleted successfully.', 'menumatic') . '</p></div>';
}

// Get menus
$menus = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC");
?>

<div class="wrap menumatic-manage">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=menumatic-upload'); ?>" class="button button-primary">
                <?php _e('Add New Menu', 'menumatic'); ?>
            </a>
        </div>
    </div>
    
    <?php if (empty($menus)): ?>
        <div class="menumatic-empty-state">
            <h2><?php _e('No menus found', 'menumatic'); ?></h2>
            <p><?php _e('Upload your first menu to get started!', 'menumatic'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=menumatic-upload'); ?>" class="button button-primary">
                <?php _e('Upload Menu', 'menumatic'); ?>
            </a>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'menumatic'); ?></th>
                    <th><?php _e('Created', 'menumatic'); ?></th>
                    <th><?php _e('Shortcode', 'menumatic'); ?></th>
                    <th><?php _e('Actions', 'menumatic'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $menu): ?>
                    <?php $menu_shortcode = sprintf('[menumatic_display name="%s"]', str_replace(array('\\', '"'), array('\\\\', '\\"'), $menu->title)); ?>
                    <tr>
                        <td><strong><?php echo esc_html($menu->title); ?></strong></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($menu->created_at)); ?></td>
                        <td>
                            <code><?php echo esc_html($menu_shortcode); ?></code>
                            <button class="button-link copy-shortcode" data-shortcode="<?php echo esc_attr($menu_shortcode); ?>">
                                <?php _e('Copy', 'menumatic'); ?>
                            </button>
                        </td>
                        <td>
                            <a href="#" class="button button-small preview-menu" data-id="<?php echo $menu->id; ?>">
                                <?php _e('Preview', 'menumatic'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=menumatic-edit-json&menu_id=' . $menu->id); ?>" class="button button-small">
                                <?php _e('Edit Manually', 'menumatic'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=menumatic-manage&action=delete&id=' . $menu->id . '&nonce=' . wp_create_nonce('delete_menu_' . $menu->id)); ?>" 
                               class="button button-small button-link-delete" 
                               onclick="return confirm('<?php esc_attr_e('Are you sure?', 'menumatic'); ?>')">
                                <?php _e('Delete', 'menumatic'); ?>
                            </a>
                        </td>
                    </tr>
                    <tr class="edit-menu-form-row" id="edit-menu-row-<?php echo $menu->id; ?>" style="display: none;">
                        <td colspan="4">
                            <form class="edit-menu-form" data-id="<?php echo $menu->id; ?>" enctype="multipart/form-data">
                                <h4><?php printf(esc_html__('Editing "%s"', 'menumatic'), esc_html($menu->title)); ?></h4>
                                <div class="form-group">
                                    <label for="edit_menu_title_<?php echo $menu->id; ?>"><?php _e('Menu Title', 'menumatic'); ?></label>
                                    <input type="text" id="edit_menu_title_<?php echo $menu->id; ?>" name="edit_menu_title" value="<?php echo esc_attr($menu->title); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_menu_image_<?php echo $menu->id; ?>"><?php _e('Upload New Menu Image (Optional)', 'menumatic'); ?></label>
                                    <input type="file" id="edit_menu_image_<?php echo $menu->id; ?>" name="edit_menu_image" accept="image/jpeg,image/png,image/webp">
                                    <p class="description"><?php _e('If you upload a new image, the menu content will be re-generated by the AI.', 'menumatic'); ?></p>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="button button-primary"><?php _e('Update Menu', 'menumatic'); ?></button>
                                    <button type="button" class="button cancel-edit" data-id="<?php echo $menu->id; ?>"><?php _e('Cancel', 'menumatic'); ?></button>
                                    <span class="spinner"></span>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Preview Modal -->
<div id="menu-preview-modal" class="menumatic-modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="menu-preview-content"></div>
    </div>
</div> 
