<?php
/**
 * Admin Dashboard Page
 * 
 * @package MenuMatic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get menu statistics
global $wpdb;
$table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
$total_menus = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
$recent_menus = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");

// Check if the selected AI provider is configured
$settings = $this->get_settings();
$provider_config = $this->get_ai_provider_config($settings);
$api_key_configured = $this->is_ai_configured($settings);
?>

<div class="wrap menumatic-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (get_transient('menumatic_activation_notice')): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('MenuMatic activated successfully! Please configure your AI provider in settings to get started.', 'menumatic'); ?></p>
        </div>
        <?php delete_transient('menumatic_activation_notice'); ?>
    <?php endif; ?>
    
    <?php if (!$api_key_configured): ?>
        <div class="notice notice-warning">
            <p>
                <?php echo esc_html(sprintf(__('%s API key not configured.', 'menumatic'), $provider_config['provider_label'])); ?>
                <a href="<?php echo admin_url('admin.php?page=menumatic-settings'); ?>" class="button-secondary">
                    <?php _e('Configure Settings', 'menumatic'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="menumatic-dashboard-widgets">
        <!-- Statistics Cards -->
        <div class="menumatic-stats-cards">
            <div class="menumatic-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-food"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_menus); ?></h3>
                    <p><?php _e('Total Menus', 'menumatic'); ?></p>
                </div>
            </div>
            
            <div class="menumatic-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-upload"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $api_key_configured ? __('Ready', 'menumatic') : __('Setup Required', 'menumatic'); ?></h3>
                    <p><?php _e('API Status', 'menumatic'); ?></p>
                </div>
            </div>
            
            <div class="menumatic-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-smartphone"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Mobile Ready', 'menumatic'); ?></h3>
                    <p><?php _e('Responsive Design', 'menumatic'); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="menumatic-quick-actions">
            <h2><?php _e('Quick Actions', 'menumatic'); ?></h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=menumatic-upload'); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Upload New Menu', 'menumatic'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=menumatic-manage'); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('Manage Menus', 'menumatic'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=menumatic-settings'); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'menumatic'); ?>
                </a>
            </div>
        </div>

        <!-- Recent Menus -->
        <?php if (!empty($recent_menus)): ?>
        <div class="menumatic-recent-menus">
            <h2><?php _e('Recent Menus', 'menumatic'); ?></h2>
            <div class="recent-menus-list">
                <?php foreach ($recent_menus as $menu): ?>
                    <div class="recent-menu-item">
                        <div class="menu-title">
                            <strong><?php echo esc_html($menu->title); ?></strong>
                        </div>
                        <div class="menu-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($menu->created_at)); ?>
                        </div>
                        <div class="menu-actions">
                            <a href="<?php echo admin_url('admin.php?page=menumatic-manage&action=edit&id=' . $menu->id); ?>" class="button button-small">
                                <?php _e('Edit', 'menumatic'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Getting Started -->
        <div class="menumatic-getting-started">
            <h2><?php _e('Getting Started', 'menumatic'); ?></h2>
            <div class="getting-started-steps">
                <div class="step <?php echo $api_key_configured ? 'completed' : 'pending'; ?>">
                    <span class="step-number">1</span>
                    <div class="step-content">
                        <h4><?php _e('Configure AI Provider', 'menumatic'); ?></h4>
                        <p><?php _e('Choose an AI provider and add its API key in settings to enable menu processing.', 'menumatic'); ?></p>
                        <?php if (!$api_key_configured): ?>
                            <a href="<?php echo admin_url('admin.php?page=menumatic-settings'); ?>" class="button button-secondary">
                                <?php _e('Add API Key', 'menumatic'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="step <?php echo $total_menus > 0 ? 'completed' : 'pending'; ?>">
                    <span class="step-number">2</span>
                    <div class="step-content">
                        <h4><?php _e('Upload Your First Menu', 'menumatic'); ?></h4>
                        <p><?php _e('Take a photo of a menu and let AI transform it into beautiful HTML.', 'menumatic'); ?></p>
                        <?php if ($api_key_configured): ?>
                            <a href="<?php echo admin_url('admin.php?page=menumatic-upload'); ?>" class="button button-primary">
                                <?php _e('Upload Menu', 'menumatic'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="step pending">
                    <span class="step-number">3</span>
                    <div class="step-content">
                        <h4><?php _e('Display Menu', 'menumatic'); ?></h4>
                        <p><?php _e('Use the [menumatic_display name="Menu Name"] shortcode to display your menu on any page or post.', 'menumatic'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 
