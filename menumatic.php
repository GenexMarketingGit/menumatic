<?php
/**
 * Plugin Name: MenuMatic
 * Plugin URI: https://github.com/lancesmith/menumatic
 * Description: Transform menu photos into beautiful, responsive HTML menus using OpenAI GPT-5.4 vision AI.
 * Version: 1.0.0
 * Author: Lance Smith
 * Author URI: https://lancesmith.cc
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: menumatic
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * 
 * @package MenuMatic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MENUMATIC_VERSION', '1.0.0');
define('MENUMATIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MENUMATIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MENUMATIC_PLUGIN_FILE', __FILE__);
define('MENUMATIC_TABLE_NAME', 'menumatic_menus');

/**
 * Main MenuMatic Plugin Class
 */
class MenuMatic {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'maybe_migrate_settings'), 5);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'register_blocks'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_menumatic_upload_image', array($this, 'handle_image_upload'));
        add_action('wp_ajax_menumatic_process_menu', array($this, 'process_menu_with_ai'));
        add_action('wp_ajax_menumatic_test_api', array($this, 'test_api_connection'));
        
        // Frontend AJAX actions
        add_action('wp_ajax_menumatic_frontend_upload', array($this, 'frontend_upload_menu'));
        add_action('wp_ajax_nopriv_menumatic_frontend_upload', array($this, 'frontend_upload_menu'));
        add_action('wp_ajax_menumatic_frontend_delete', array($this, 'frontend_delete_menu'));
        add_action('wp_ajax_nopriv_menumatic_frontend_delete', array($this, 'frontend_delete_menu'));
        add_action('wp_ajax_menumatic_frontend_regenerate', array($this, 'frontend_regenerate_menu'));
        add_action('wp_ajax_nopriv_menumatic_frontend_regenerate', array($this, 'frontend_regenerate_menu'));
        add_action('wp_ajax_menumatic_preview_menu', array($this, 'preview_menu'));
        add_action('wp_ajax_menumatic_update_menu', array($this, 'update_menu'));
        
        // JSON editor AJAX handlers
        add_action('wp_ajax_menumatic_update_json', array($this, 'update_menu_json'));
        add_action('wp_ajax_menumatic_regenerate_from_json', array($this, 'regenerate_from_json'));
        add_action('wp_ajax_menumatic_get_menu_data', array($this, 'get_menu_data'));
        add_action('wp_ajax_menumatic_upload_item_image', array($this, 'upload_item_image'));
        add_action('wp_ajax_menumatic_set_global_style', array($this, 'set_global_style'));
        
        add_shortcode('menumatic_display', array($this, 'display_menu_shortcode'));
        add_shortcode('menumatic_manage', array($this, 'frontend_manage_shortcode'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Include required files
        $this->include_files();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('menumatic', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->migrate_legacy_data();
        $this->create_tables();
        $this->create_upload_directory();
        
        // Add default options
        add_option('menumatic_settings', $this->get_default_settings());
        
        // Set activation flag
        set_transient('menumatic_activation_notice', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files
        $this->cleanup_temp_files();
        
        // Clear any scheduled events
        wp_clear_scheduled_hook('menumatic_cleanup_old_files');
    }
    
    /**
     * Migrate data from legacy plugin naming.
     */
    private function migrate_legacy_data() {
        global $wpdb;

        $legacy_slug = 'menu' . 'bro';
        $old_table_name = $wpdb->prefix . $legacy_slug . '_menus';
        $new_table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        
        // Check if old table exists and new table doesn't
        $old_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table_name)) === $old_table_name;
        $new_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $new_table_name)) === $new_table_name;
        
        if ($old_table_exists && !$new_table_exists) {
            // Rename the legacy table to the current table name.
            $wpdb->query($wpdb->prepare("RENAME TABLE %s TO %s", $old_table_name, $new_table_name));
            
            // Log the migration
            error_log('MenuMatic: Successfully migrated table from ' . $old_table_name . ' to ' . $new_table_name);
        }
        
        // Migrate settings option
        $old_settings = get_option($legacy_slug . '_settings');
        if ($old_settings && !get_option('menumatic_settings')) {
            add_option('menumatic_settings', $old_settings);
            // Don't delete old settings yet in case of rollback needs
            error_log('MenuMatic: Migrated settings from legacy option to menumatic_settings');
        }
        
        // Migrate upload directory
        $upload_dir = wp_upload_dir();
        $old_dir = $upload_dir['basedir'] . '/' . $legacy_slug;
        $new_dir = $upload_dir['basedir'] . '/menumatic';
        
        if (is_dir($old_dir) && !is_dir($new_dir)) {
            if (rename($old_dir, $new_dir)) {
                error_log('MenuMatic: Successfully migrated upload directory from legacy path to menumatic');
            }
        }
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            original_image_path varchar(500) NOT NULL,
            processed_html longtext NOT NULL,
            menu_data longtext,
            style_settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            user_id bigint(20) UNSIGNED,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create upload directory
     */
    private function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $menumatic_dir = $upload_dir['basedir'] . '/menumatic';
        
        if (!file_exists($menumatic_dir)) {
            wp_mkdir_p($menumatic_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($menumatic_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('MenuMatic', 'menumatic'),
            __('MenuMatic', 'menumatic'),
            'manage_options',
            'menumatic',
            array($this, 'admin_page'),
            'dashicons-food',
            30
        );
        
        add_submenu_page(
            'menumatic',
            __('Upload Menu', 'menumatic'),
            __('Upload Menu', 'menumatic'),
            'manage_options',
            'menumatic-upload',
            array($this, 'upload_page')
        );
        
        add_submenu_page(
            'menumatic',
            __('Manage Menus', 'menumatic'),
            __('Manage Menus', 'menumatic'),
            'manage_options',
            'menumatic-manage',
            array($this, 'manage_page')
        );
        
        add_submenu_page(
            'menumatic',
            __('Styles', 'menumatic'),
            __('Styles', 'menumatic'),
            'manage_options',
            'menumatic-styles',
            array($this, 'styles_page')
        );

        add_submenu_page(
            'menumatic',
            __('Settings', 'menumatic'),
            __('Settings', 'menumatic'),
            'manage_options',
            'menumatic-settings',
            array($this, 'settings_page')
        );
        
        // Hidden page for JSON editor (accessed via menu manage page)
        add_submenu_page(
            null, // Hidden from menu
            __('Edit Menu JSON', 'menumatic'),
            __('Edit Menu JSON', 'menumatic'),
            'manage_options',
            'menumatic-edit-json',
            array($this, 'edit_json_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (empty($hook) || strpos($hook, 'menumatic') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        // Add a dynamic version number to bust cache during development
        $version = MENUMATIC_VERSION . '.' . time();
        wp_enqueue_script('menumatic-admin', MENUMATIC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $version, true);
        
        wp_enqueue_style('menumatic-admin', MENUMATIC_PLUGIN_URL . 'assets/css/admin.css', array(), MENUMATIC_VERSION);
        
        wp_localize_script('menumatic-admin', 'menumatic_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('menumatic_nonce'),
            'style_library' => $this->get_menu_style_library(),
            'strings' => array(
                'uploading' => __('Uploading...', 'menumatic'),
                'processing' => __('Processing with AI...', 'menumatic'),
                'error' => __('An error occurred', 'menumatic'),
                'success' => __('Menu processed successfully!', 'menumatic'),
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('menumatic-frontend', MENUMATIC_PLUGIN_URL . 'assets/css/frontend.css', array(), MENUMATIC_VERSION);
        wp_enqueue_script('menumatic-frontend', MENUMATIC_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), MENUMATIC_VERSION, true);
        
        // Localize script for AJAX calls
        wp_localize_script('menumatic-frontend', 'menumatic_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('menumatic_frontend_nonce'),
            'style_library' => $this->get_menu_style_library(),
            'default_style_id' => $this->get_settings()['default_menu_style_id'] ?? 'preset-modern',
            'strings' => array(
                'uploading' => __('Uploading...', 'menumatic'),
                'processing' => __('Processing with AI...', 'menumatic'),
                'error' => __('An error occurred', 'menumatic'),
                'success' => __('Menu processed successfully!', 'menumatic'),
                'confirm_delete' => __('Are you sure you want to delete this menu? This action cannot be undone.', 'menumatic')
            )
        ));
    }
    
    /**
     * Admin dashboard page
     */
    public function admin_page() {
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }
    
    /**
     * Upload page
     */
    public function upload_page() {
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/upload.php';
    }
    
    /**
     * Manage menus page
     */
    public function manage_page() {
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/manage.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/settings.php';
    }

    /**
     * Styles page.
     */
    public function styles_page() {
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/styles.php';
    }
    
    /**
     * JSON Editor page
     */
    public function edit_json_page() {
        $menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;
        if (!$menu_id) {
            wp_die(__('Invalid menu ID', 'menumatic'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        
        if (!$menu) {
            wp_die(__('Menu not found', 'menumatic'));
        }
        
        include_once MENUMATIC_PLUGIN_DIR . 'includes/admin/edit-json.php';
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once MENUMATIC_PLUGIN_DIR . 'includes/class-openai-api.php';
    }

    /**
     * Get default plugin settings.
     */
    public function get_default_settings() {
        return array(
            'ai_provider' => 'openai',
            'openai_api_key_encrypted' => '',
            'openrouter_api_key_encrypted' => '',
            'openai_model' => 'gpt-5.4-mini',
            'openrouter_model' => 'mistralai/ministral-8b-2512',
            'max_file_size' => 5,
            'allowed_file_types' => array('jpg', 'jpeg', 'png', 'webp'),
            'default_menu_style_id' => 'preset-modern',
            'default_menu_style' => 'modern',
            'enable_caching' => true,
            'ai_prompt_template' => ''
        );
    }

    /**
     * Supported AI providers.
     */
    public function get_supported_ai_providers() {
        return array(
            'openai' => __('OpenAI', 'menumatic'),
            'openrouter' => __('OpenRouter', 'menumatic'),
        );
    }

    /**
     * Supported OpenAI models.
     */
    public function get_supported_openai_models() {
        return array(
            'gpt-5.4-mini' => __('GPT-5.4 mini (Recommended)', 'menumatic'),
            'gpt-5.4-nano' => __('GPT-5.4 nano (Fastest, lowest cost)', 'menumatic'),
        );
    }

    /**
     * Supported OpenRouter models.
     */
    public function get_supported_openrouter_models() {
        return array(
            'mistralai/ministral-8b-2512' => __('Mistral Ministral 8B 2512', 'menumatic'),
            'bytedance-seed/seed-1.6-flash' => __('ByteDance Seed 1.6 Flash', 'menumatic'),
            'google/gemini-3-flash-preview' => __('Google Gemini 3 Flash Preview', 'menumatic'),
        );
    }

    /**
     * Menu style presets.
     */
    public function get_menu_style_presets() {
        return array(
            'inherit-theme' => array(
                'label' => __('Inherit From Theme', 'menumatic'),
                'description' => __('Use the active theme typography, colors, spacing, and container styling as much as possible.', 'menumatic'),
            ),
            'modern' => array(
                'label' => __('Modern', 'menumatic'),
                'description' => __('Clean editorial layout with balanced contrast.', 'menumatic'),
            ),
            'minimal' => array(
                'label' => __('Minimal', 'menumatic'),
                'description' => __('Quiet, airy layout that leans on theme typography.', 'menumatic'),
            ),
            'editorial' => array(
                'label' => __('Editorial', 'menumatic'),
                'description' => __('Magazine-style menu with pronounced headings.', 'menumatic'),
            ),
            'bistro' => array(
                'label' => __('Bistro', 'menumatic'),
                'description' => __('Warm cafe palette with inviting cards.', 'menumatic'),
            ),
            'cafe' => array(
                'label' => __('Cafe', 'menumatic'),
                'description' => __('Soft daytime tones and approachable spacing.', 'menumatic'),
            ),
            'coastal' => array(
                'label' => __('Coastal', 'menumatic'),
                'description' => __('Bright sea-glass look for fresh, casual menus.', 'menumatic'),
            ),
            'orchard' => array(
                'label' => __('Orchard', 'menumatic'),
                'description' => __('Natural greens and earthy accents.', 'menumatic'),
            ),
            'luxe' => array(
                'label' => __('Luxe', 'menumatic'),
                'description' => __('Dark, refined treatment for premium dining.', 'menumatic'),
            ),
            'paper' => array(
                'label' => __('Paper', 'menumatic'),
                'description' => __('Printed-menu feel with subtle paper texture.', 'menumatic'),
            ),
            'night-market' => array(
                'label' => __('Night Market', 'menumatic'),
                'description' => __('High-energy contrast with punchier highlights.', 'menumatic'),
            ),
            'custom' => array(
                'label' => __('Custom', 'menumatic'),
                'description' => __('Use your own CSS overrides on top of the base layout.', 'menumatic'),
            ),
        );
    }

    /**
     * Default layout options for menus.
     */
    public function get_default_menu_layout_options() {
        return array(
            'columns' => 'auto',
            'price_position' => 'inline',
            'text_layout' => 'stacked',
        );
    }

    /**
     * Available layout option labels.
     */
    public function get_menu_layout_option_labels() {
        return array(
            'columns' => array(
                'auto' => __('Automatic Columns', 'menumatic'),
                '1' => __('Single Column', 'menumatic'),
                '2' => __('Two Columns', 'menumatic'),
                '3' => __('Three Columns', 'menumatic'),
            ),
            'price_position' => array(
                'inline' => __('Under Name', 'menumatic'),
                'top-right' => __('Top Right', 'menumatic'),
                'below-description' => __('Below Description', 'menumatic'),
            ),
            'text_layout' => array(
                'stacked' => __('Stacked', 'menumatic'),
                'split' => __('Split Row', 'menumatic'),
                'compact' => __('Compact', 'menumatic'),
            ),
        );
    }

    /**
     * Normalize layout options.
     */
    public function normalize_menu_layout_options($layout = null) {
        $defaults = $this->get_default_menu_layout_options();
        $labels = $this->get_menu_layout_option_labels();

        if (!is_array($layout)) {
            $layout = array();
        }

        $normalized = array();
        foreach ($defaults as $key => $default_value) {
            $value = isset($layout[$key]) ? sanitize_key($layout[$key]) : $default_value;
            if (!isset($labels[$key][$value])) {
                $value = $default_value;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Built-in style definitions.
     */
    public function get_builtin_menu_styles() {
        $presets = $this->get_menu_style_presets();
        $css_templates = $this->get_builtin_menu_style_css_templates();
        $builtin_styles = array();

        foreach ($presets as $slug => $preset) {
            if ($slug === 'custom') {
                continue;
            }

            $builtin_styles['preset-' . $slug] = array(
                'id' => 'preset-' . $slug,
                'name' => $preset['label'],
                'description' => $preset['description'],
                'preset_slug' => $slug,
                'layout' => $this->get_default_menu_layout_options(),
                'custom_css' => $css_templates[$slug] ?? '',
                'is_builtin' => true,
            );
        }

        return $builtin_styles;
    }

    /**
     * Built-in editable CSS templates for style presets.
     */
    public function get_builtin_menu_style_css_templates() {
        return array(
            'inherit-theme' => $this->build_builtin_menu_style_template(array(
                'font_body' => 'inherit',
                'font_heading' => 'inherit',
                'bg' => 'transparent',
                'surface' => 'transparent',
                'card' => 'transparent',
                'border' => 'transparent',
                'border_strong' => 'currentColor',
                'text' => 'inherit',
                'muted' => 'inherit',
                'accent' => 'currentColor',
                'accent_soft' => 'transparent',
                'shadow' => 'none',
                'radius_xl' => '0px',
                'radius_lg' => '0px',
                'radius_md' => '0px',
                'radius_sm' => '0px',
                'container_background' => 'transparent',
                'item_background' => 'transparent',
                'extras' => array(
                    '{{scope}} {',
                    '    padding: 0;',
                    '}',
                    '',
                    '{{scope}}::before {',
                    '    display: none;',
                    '}',
                    '',
                    '{{scope}} .menu-container {',
                    '    padding: 0;',
                    '    backdrop-filter: none;',
                    '}',
                    '',
                    '{{scope}} .menu-item {',
                    '    box-shadow: none;',
                    '}',
                    '',
                    '{{scope}} .item-name,',
                    '{{scope}} .menu-section-title,',
                    '{{scope}} .menu-other-title,',
                    '{{scope}} .item-price,',
                    '{{scope}} .item-description,',
                    '{{scope}} .item-additional-info,',
                    '{{scope}} .menu-other-content,',
                    '{{scope}} .other-content-type {',
                    '    color: inherit;',
                    '    font-family: inherit;',
                    '}',
                ),
            )),
            'modern' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Avenir Next", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Cormorant Garamond", Georgia, "Times New Roman", serif',
                'bg' => 'linear-gradient(180deg, rgba(255, 251, 244, 0.98), rgba(255, 255, 255, 0.98))',
                'surface' => 'rgba(255, 255, 255, 0.9)',
                'card' => 'rgba(255, 255, 255, 0.92)',
                'border' => 'rgba(105, 84, 63, 0.16)',
                'border_strong' => 'rgba(105, 84, 63, 0.28)',
                'text' => '#1f1c19',
                'muted' => 'rgba(31, 28, 25, 0.74)',
                'accent' => '#bc6c25',
                'accent_soft' => 'rgba(188, 108, 37, 0.12)',
            )),
            'minimal' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Helvetica Neue", Arial, sans-serif',
                'bg' => '#ffffff',
                'surface' => '#ffffff',
                'card' => '#ffffff',
                'border' => 'rgba(0, 0, 0, 0.08)',
                'border_strong' => 'rgba(0, 0, 0, 0.16)',
                'text' => '#141414',
                'muted' => 'rgba(20, 20, 20, 0.65)',
                'accent' => '#111111',
                'accent_soft' => 'rgba(17, 17, 17, 0.06)',
                'radius_xl' => '18px',
                'radius_lg' => '18px',
                'shadow' => '0 18px 48px rgba(0, 0, 0, 0.06)',
                'extras' => array(
                    '{{scope}} .menu-section-title,',
                    '{{scope}} .menu-other-title {',
                    '    letter-spacing: -0.04em;',
                    '}',
                ),
            )),
            'editorial' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Libre Franklin", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Baskerville", "Times New Roman", serif',
                'bg' => 'linear-gradient(180deg, #f9f3e8, #fffdf8)',
                'surface' => 'rgba(255, 252, 246, 0.92)',
                'card' => 'rgba(255, 255, 255, 0.82)',
                'border' => 'rgba(84, 54, 40, 0.18)',
                'border_strong' => 'rgba(84, 54, 40, 0.28)',
                'text' => '#2e2119',
                'muted' => 'rgba(46, 33, 25, 0.72)',
                'accent' => '#8d4f2b',
                'accent_soft' => 'rgba(141, 79, 43, 0.12)',
                'extras' => array(
                    '{{scope}} .menu-section-title,',
                    '{{scope}} .menu-other-title {',
                    '    text-transform: uppercase;',
                    '    letter-spacing: 0.14em;',
                    '    font-size: clamp(1.35rem, 1vw + 1rem, 1.9rem);',
                    '}',
                ),
            )),
            'bistro' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Source Sans Pro", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Playfair Display", Georgia, serif',
                'bg' => 'linear-gradient(180deg, #fff6ec, #fffdf8)',
                'surface' => 'rgba(255, 248, 239, 0.9)',
                'card' => 'rgba(255, 255, 255, 0.78)',
                'border' => 'rgba(124, 67, 32, 0.16)',
                'border_strong' => 'rgba(124, 67, 32, 0.28)',
                'text' => '#342319',
                'muted' => 'rgba(52, 35, 25, 0.72)',
                'accent' => '#c05d1d',
                'accent_soft' => 'rgba(192, 93, 29, 0.12)',
                'radius_lg' => '28px 14px 28px 14px',
            )),
            'cafe' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Lato", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Merriweather", Georgia, serif',
                'bg' => 'linear-gradient(180deg, #f6f0e9, #fefaf4)',
                'surface' => 'rgba(255, 251, 246, 0.92)',
                'card' => 'rgba(255, 255, 255, 0.84)',
                'border' => 'rgba(110, 92, 73, 0.18)',
                'border_strong' => 'rgba(110, 92, 73, 0.26)',
                'text' => '#33281e',
                'muted' => 'rgba(51, 40, 30, 0.7)',
                'accent' => '#6f4e37',
                'accent_soft' => 'rgba(111, 78, 55, 0.12)',
                'container_background' => 'radial-gradient(circle at top right, rgba(255, 255, 255, 0.45), transparent 32%), linear-gradient(180deg, var(--mm-surface), var(--mm-bg))',
            )),
            'coastal' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Avenir Next", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Cormorant Garamond", Georgia, serif',
                'bg' => 'linear-gradient(180deg, #eff8fb, #f8fcff)',
                'surface' => 'rgba(245, 251, 255, 0.92)',
                'card' => 'rgba(255, 255, 255, 0.84)',
                'border' => 'rgba(54, 115, 140, 0.16)',
                'border_strong' => 'rgba(54, 115, 140, 0.28)',
                'text' => '#173340',
                'muted' => 'rgba(23, 51, 64, 0.72)',
                'accent' => '#0f8b8d',
                'accent_soft' => 'rgba(15, 139, 141, 0.14)',
                'item_background' => 'linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(243, 251, 255, 0.96))',
            )),
            'orchard' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Nunito Sans", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Cormorant Garamond", Georgia, serif',
                'bg' => 'linear-gradient(180deg, #f6fbf1, #fcfff8)',
                'surface' => 'rgba(249, 255, 245, 0.92)',
                'card' => 'rgba(255, 255, 255, 0.84)',
                'border' => 'rgba(69, 104, 64, 0.16)',
                'border_strong' => 'rgba(69, 104, 64, 0.28)',
                'text' => '#1d3019',
                'muted' => 'rgba(29, 48, 25, 0.72)',
                'accent' => '#588157',
                'accent_soft' => 'rgba(88, 129, 87, 0.12)',
            )),
            'luxe' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Montserrat", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Didot", "Times New Roman", serif',
                'bg' => 'linear-gradient(180deg, #171414, #221d1d)',
                'surface' => 'rgba(22, 18, 18, 0.94)',
                'card' => 'rgba(32, 27, 27, 0.92)',
                'border' => 'rgba(255, 214, 163, 0.16)',
                'border_strong' => 'rgba(255, 214, 163, 0.28)',
                'text' => '#f5eee4',
                'muted' => 'rgba(245, 238, 228, 0.76)',
                'accent' => '#e9b872',
                'accent_soft' => 'rgba(233, 184, 114, 0.12)',
                'shadow' => '0 32px 70px rgba(0, 0, 0, 0.35)',
                'extras' => array(
                    '{{scope}}::before {',
                    '    background:',
                    '        radial-gradient(circle at top left, rgba(233, 184, 114, 0.08), transparent 28%),',
                    '        linear-gradient(180deg, rgba(255, 255, 255, 0.03), transparent 40%);',
                    '}',
                ),
            )),
            'paper' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Libre Baskerville", Georgia, serif',
                'font_heading' => '"Palatino", "Times New Roman", serif',
                'bg' => '#f8f1e2',
                'surface' => '#fcf7ee',
                'card' => 'rgba(255, 252, 246, 0.9)',
                'border' => 'rgba(115, 92, 63, 0.22)',
                'border_strong' => 'rgba(115, 92, 63, 0.34)',
                'text' => '#2f271f',
                'muted' => 'rgba(47, 39, 31, 0.72)',
                'accent' => '#8c5a30',
                'accent_soft' => 'rgba(140, 90, 48, 0.1)',
                'shadow' => '0 18px 40px rgba(74, 57, 33, 0.12)',
                'container_background' => 'repeating-linear-gradient(180deg, rgba(125, 99, 66, 0.028), rgba(125, 99, 66, 0.028) 1px, transparent 1px, transparent 24px), linear-gradient(180deg, var(--mm-surface), var(--mm-bg))',
            )),
            'night-market' => $this->build_builtin_menu_style_template(array(
                'font_body' => '"Barlow", "Helvetica Neue", Arial, sans-serif',
                'font_heading' => '"Trebuchet MS", "Helvetica Neue", Arial, sans-serif',
                'bg' => 'linear-gradient(180deg, #111827, #161b28)',
                'surface' => 'rgba(17, 24, 39, 0.96)',
                'card' => 'rgba(24, 32, 49, 0.94)',
                'border' => 'rgba(111, 201, 255, 0.16)',
                'border_strong' => 'rgba(249, 115, 22, 0.28)',
                'text' => '#eef6ff',
                'muted' => 'rgba(238, 246, 255, 0.72)',
                'accent' => '#fb923c',
                'accent_soft' => 'rgba(251, 146, 60, 0.14)',
                'shadow' => '0 28px 60px rgba(0, 0, 0, 0.32)',
                'extras' => array(
                    '{{scope}} .menu-section-title,',
                    '{{scope}} .menu-other-title {',
                    '    color: #8edcff;',
                    '}',
                ),
            )),
        );
    }

    /**
     * Get the built-in CSS template for a preset slug.
     */
    public function get_builtin_menu_style_css_template($preset_slug = 'modern') {
        $templates = $this->get_builtin_menu_style_css_templates();
        $preset_slug = $this->normalize_menu_style_slug($preset_slug);

        return $templates[$preset_slug] ?? ($templates['modern'] ?? '');
    }

    /**
     * Detect whether CSS matches a built-in preset template exactly.
     */
    public function detect_builtin_menu_style_css_template_slug($custom_css = '') {
        $custom_css = trim((string) $custom_css);
        if ($custom_css === '') {
            return '';
        }

        foreach ($this->get_builtin_menu_style_css_templates() as $preset_slug => $template_css) {
            if (trim((string) $template_css) === $custom_css) {
                return $preset_slug;
            }
        }

        return '';
    }

    /**
     * Build a complete editable CSS template for a preset.
     */
    private function build_builtin_menu_style_template($config) {
        $config = wp_parse_args($config, array(
            'font_body' => '"Helvetica Neue", Arial, sans-serif',
            'font_heading' => 'Georgia, "Times New Roman", serif',
            'bg' => '#ffffff',
            'surface' => 'rgba(255, 255, 255, 0.92)',
            'card' => 'rgba(255, 255, 255, 0.9)',
            'border' => 'rgba(24, 31, 37, 0.12)',
            'border_strong' => 'rgba(24, 31, 37, 0.2)',
            'text' => '#182025',
            'muted' => 'rgba(24, 32, 37, 0.72)',
            'accent' => '#bc6c25',
            'accent_soft' => 'rgba(188, 108, 37, 0.12)',
            'shadow' => '0 30px 80px rgba(19, 27, 33, 0.12)',
            'radius_xl' => '32px',
            'radius_lg' => '24px',
            'radius_md' => '18px',
            'radius_sm' => '12px',
            'container_background' => 'linear-gradient(180deg, var(--mm-surface), var(--mm-bg))',
            'item_background' => 'var(--mm-card)',
            'extras' => array(),
        ));

        $lines = array(
            '/* Typography, palette, and shape tokens */',
            '{{scope}} {',
            '    --mm-font-body: ' . $config['font_body'] . ';',
            '    --mm-font-heading: ' . $config['font_heading'] . ';',
            '    --mm-bg: ' . $config['bg'] . ';',
            '    --mm-surface: ' . $config['surface'] . ';',
            '    --mm-card: ' . $config['card'] . ';',
            '    --mm-border: ' . $config['border'] . ';',
            '    --mm-border-strong: ' . $config['border_strong'] . ';',
            '    --mm-text: ' . $config['text'] . ';',
            '    --mm-muted: ' . $config['muted'] . ';',
            '    --mm-accent: ' . $config['accent'] . ';',
            '    --mm-accent-soft: ' . $config['accent_soft'] . ';',
            '    --mm-shadow: ' . $config['shadow'] . ';',
            '    --mm-radius-xl: ' . $config['radius_xl'] . ';',
            '    --mm-radius-lg: ' . $config['radius_lg'] . ';',
            '    --mm-radius-md: ' . $config['radius_md'] . ';',
            '    --mm-radius-sm: ' . $config['radius_sm'] . ';',
            '    font-family: ' . $config['font_body'] . ';',
            '    color: ' . $config['text'] . ';',
            '}',
            '',
            '/* Decorative wrapper glow */',
            '{{scope}}::before {',
            '    border-radius: calc(' . $config['radius_xl'] . ' + 6px);',
            '    background:',
            '        radial-gradient(circle at top left, rgba(255, 255, 255, 0.72), transparent 34%),',
            '        linear-gradient(180deg, rgba(255, 255, 255, 0.1), transparent 38%);',
            '}',
            '',
            '/* Main menu panel */',
            '{{scope}} .menu-container {',
            '    font-family: ' . $config['font_body'] . ';',
            '    color: ' . $config['text'] . ';',
            '    border: 1px solid ' . $config['border'] . ';',
            '    border-radius: ' . $config['radius_xl'] . ';',
            '    box-shadow: ' . $config['shadow'] . ';',
            '    background: ' . $config['container_background'] . ';',
            '}',
            '',
            '/* Menu item grid */',
            '{{scope}} .menu-grid {',
            '    gap: 1.15rem;',
            '}',
            '',
            '/* Menu cards */',
            '{{scope}} .menu-item {',
            '    border: 1px solid ' . $config['border'] . ';',
            '    border-radius: ' . $config['radius_lg'] . ';',
            '    background: ' . $config['item_background'] . ';',
            '    box-shadow: 0 12px 30px rgba(15, 23, 31, 0.08);',
            '}',
            '',
            '{{scope}} .menu-item:hover,',
            '{{scope}} .menu-item:focus-within {',
            '    border-color: ' . $config['border_strong'] . ';',
            '    box-shadow: 0 18px 40px rgba(15, 23, 31, 0.12);',
            '}',
            '',
            '{{scope}} .item-image {',
            '    height: 210px;',
            '    object-fit: cover;',
            '}',
            '',
            '{{scope}} .menu-item-content {',
            '    padding: 1.15rem 1.2rem 1.25rem;',
            '}',
            '',
            '/* Headings */',
            '{{scope}} .item-name,',
            '{{scope}} .menu-section-title,',
            '{{scope}} .menu-other-title {',
            '    font-family: ' . $config['font_heading'] . ';',
            '    color: ' . $config['text'] . ';',
            '}',
            '',
            '{{scope}} .item-name {',
            '    font-size: clamp(1.1rem, 1vw + 0.95rem, 1.4rem);',
            '    line-height: 1.2;',
            '}',
            '',
            '{{scope}} .menu-section-title,',
            '{{scope}} .menu-other-title {',
            '    font-size: clamp(1.6rem, 1.8vw + 1rem, 2.4rem);',
            '    line-height: 1.05;',
            '}',
            '',
            '/* Item copy */',
            '{{scope}} .item-price {',
            '    color: ' . $config['accent'] . ';',
            '    font-weight: 700;',
            '    letter-spacing: 0.02em;',
            '}',
            '',
            '{{scope}} .item-description,',
            '{{scope}} .item-additional-info {',
            '    color: ' . $config['muted'] . ';',
            '    font-size: 0.96rem;',
            '}',
            '',
            '{{scope}} .item-additional-info {',
            '    font-style: italic;',
            '}',
            '',
            '/* Pills, separators, and supporting text */',
            '{{scope}} .dietary-indicator {',
            '    color: ' . $config['accent'] . ';',
            '}',
            '',
            '{{scope}} .dietary-indicator::before {',
            '    border: 1px solid currentColor;',
            '    border-radius: 999px;',
            '    background: ' . $config['accent_soft'] . ';',
            '}',
            '',
            '{{scope}} .menu-separator {',
            '    border-top: 1px solid ' . $config['border'] . ';',
            '}',
            '',
            '{{scope}} .menu-other-content {',
            '    color: ' . $config['muted'] . ';',
            '}',
            '',
            '{{scope}} .other-content-type {',
            '    color: ' . $config['accent'] . ';',
            '}',
        );

        if (!empty($config['extras']) && is_array($config['extras'])) {
            $lines[] = '';
            $lines = array_merge($lines, $config['extras']);
        }

        return implode("\n", $lines);
    }

    /**
     * Whether a style id maps to a built-in preset.
     */
    public function is_builtin_menu_style_id($style_id) {
        $style_id = sanitize_key($style_id);
        if ($style_id === '') {
            return false;
        }

        $builtin_styles = $this->get_builtin_menu_styles();
        return isset($builtin_styles[$style_id]);
    }

    /**
     * Normalize a style id, including legacy preset slugs.
     */
    public function normalize_menu_style_id($style_id = '', $fallback_style_id = 'preset-modern') {
        $style_id = is_string($style_id) ? sanitize_key($style_id) : '';
        $fallback_style_id = is_string($fallback_style_id) ? sanitize_key($fallback_style_id) : 'preset-modern';

        if ($fallback_style_id === '') {
            $fallback_style_id = 'preset-modern';
        }

        if ($style_id === '') {
            return $fallback_style_id;
        }

        if (strpos($style_id, 'preset-') === 0 || strpos($style_id, 'custom-') === 0) {
            $library = $this->get_menu_style_library();
            if (isset($library[$style_id])) {
                return $style_id;
            }
        }

        return 'preset-' . $this->normalize_menu_style_slug($style_id);
    }

    /**
     * Normalize a style definition for library storage.
     */
    public function normalize_menu_style_definition($style = null, $existing_id = '') {
        $style = is_array($style) ? $style : array();
        $id = isset($style['id']) ? sanitize_key($style['id']) : sanitize_key($existing_id);
        $is_builtin = $this->is_builtin_menu_style_id($id);
        $builtin_style = $is_builtin ? $this->get_builtin_menu_styles()[$id] : null;

        if ($id === '') {
            $id = 'custom-' . wp_generate_password(8, false, false);
        }

        $name = trim(sanitize_text_field($style['name'] ?? ($builtin_style['name'] ?? '')));
        if ($name === '') {
            $name = __('Custom Style', 'menumatic');
        }

        $description = trim(sanitize_text_field($style['description'] ?? ($builtin_style['description'] ?? '')));
        $preset_slug = $this->normalize_menu_style_slug($style['preset_slug'] ?? ($builtin_style['preset_slug'] ?? 'modern'));
        $layout = $this->normalize_menu_layout_options(array_merge(
            $builtin_style['layout'] ?? array(),
            $style['layout'] ?? array()
        ));
        $custom_css = trim((string) ($style['custom_css'] ?? ''));
        if (!$is_builtin) {
            $matched_template_slug = $this->detect_builtin_menu_style_css_template_slug($custom_css);
            if ($custom_css === '' || ($matched_template_slug !== '' && $matched_template_slug !== $preset_slug)) {
                $custom_css = trim($this->get_builtin_menu_style_css_template($preset_slug));
            }
        }

        return array(
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'preset_slug' => $preset_slug,
            'layout' => $layout,
            'custom_css' => $custom_css,
            'is_builtin' => $is_builtin,
        );
    }

    /**
     * Custom style definitions stored in options.
     */
    public function get_custom_menu_styles() {
        $stored_styles = get_option('menumatic_custom_styles', array());
        if (!is_array($stored_styles)) {
            $stored_styles = array();
        }

        $normalized = array();
        foreach ($stored_styles as $style_id => $style) {
            $style_definition = $this->normalize_menu_style_definition($style, $style_id);
            $normalized[$style_definition['id']] = $style_definition;
        }

        return $normalized;
    }

    /**
     * Persist custom styles.
     */
    public function save_custom_menu_styles($styles) {
        $styles = is_array($styles) ? $styles : array();
        update_option('menumatic_custom_styles', $styles);
    }

    /**
     * Full style library combining built-in and custom styles.
     */
    public function get_menu_style_library() {
        return array_merge($this->get_builtin_menu_styles(), $this->get_custom_menu_styles());
    }

    /**
     * Resolve a style definition by id.
     */
    public function get_menu_style_definition($style_id = '') {
        $style_id = $this->normalize_menu_style_id($style_id);
        $library = $this->get_menu_style_library();

        if ($style_id && isset($library[$style_id])) {
            return $library[$style_id];
        }

        return $library['preset-modern'];
    }

    /**
     * Save or update a custom style definition.
     */
    public function upsert_custom_menu_style($style) {
        $custom_styles = $this->get_custom_menu_styles();
        $normalized_style = $this->normalize_menu_style_definition($style, $style['id'] ?? '');
        $custom_styles[$normalized_style['id']] = $normalized_style;
        $this->save_custom_menu_styles($custom_styles);

        return $normalized_style;
    }

    /**
     * Delete a custom style definition.
     */
    public function delete_custom_menu_style($style_id) {
        $style_id = sanitize_key($style_id);
        $custom_styles = $this->get_custom_menu_styles();
        if (isset($custom_styles[$style_id])) {
            unset($custom_styles[$style_id]);
            $this->save_custom_menu_styles($custom_styles);
        }
    }

    /**
     * Normalize a style slug, including legacy aliases.
     */
    public function normalize_menu_style_slug($style) {
        $style = is_string($style) ? sanitize_key($style) : '';
        $style_aliases = array(
            'classic' => 'paper',
            'elegant' => 'luxe',
        );
        if (isset($style_aliases[$style])) {
            $style = $style_aliases[$style];
        }

        $presets = $this->get_menu_style_presets();
        if (!isset($presets[$style])) {
            $style = 'modern';
        }

        return $style;
    }

    /**
     * Normalize saved style settings into a predictable structure.
     */
    public function normalize_menu_style_settings($style_settings = null, $fallback_style = '') {
        if (is_string($style_settings) && $style_settings !== '') {
            $decoded_settings = json_decode($style_settings, true);
            if (is_array($decoded_settings)) {
                $style_settings = $decoded_settings;
            }
        }

        if (!is_array($style_settings)) {
            $style_settings = array(
                'style_id' => '',
            );
        }

        $fallback_style = $fallback_style ? $fallback_style : ($this->get_settings()['default_menu_style_id'] ?? 'preset-modern');
        $fallback_style = $this->normalize_menu_style_id($fallback_style);
        $legacy_style = $style_settings['style'] ?? $fallback_style;
        $style_id = isset($style_settings['style_id']) ? sanitize_key($style_settings['style_id']) : '';
        if ($style_id === '') {
            $style_id = $this->normalize_menu_style_id($legacy_style, $fallback_style);
        } else {
            $style_id = $this->normalize_menu_style_id($style_id, $fallback_style);
        }

        $override_css = isset($style_settings['override_css'])
            ? trim((string) $style_settings['override_css'])
            : trim((string) ($style_settings['custom_css'] ?? ''));
        $layout = $this->normalize_menu_layout_options($style_settings['layout'] ?? array());

        return array(
            'style_id' => $style_id,
            'layout' => $layout,
            'override_css' => $override_css,
        );
    }

    /**
     * Encode style settings for database storage.
     */
    public function encode_menu_style_settings($style_settings = null, $fallback_style = '') {
        $normalized = $this->normalize_menu_style_settings($style_settings, $fallback_style);
        $encoded = wp_json_encode($normalized);

        return $encoded !== false ? $encoded : wp_json_encode(array(
            'style_id' => 'preset-modern',
            'layout' => $this->get_default_menu_layout_options(),
            'override_css' => '',
        ));
    }

    /**
     * Resolve effective style data for rendering.
     */
    public function resolve_menu_render_style($style_settings = null, $fallback_style = '') {
        $normalized_settings = $this->normalize_menu_style_settings($style_settings, $fallback_style);
        $global_style_id = $this->get_settings()['default_menu_style_id'] ?? 'preset-modern';
        $style_definition = $this->get_menu_style_definition($global_style_id);

        return array(
            'style_id' => $style_definition['id'],
            'name' => $style_definition['name'],
            'description' => $style_definition['description'],
            'preset_slug' => $style_definition['preset_slug'],
            'layout' => $this->normalize_menu_layout_options(array_merge(
                $style_definition['layout'] ?? array(),
                $normalized_settings['layout'] ?? array()
            )),
            'custom_css' => trim(implode("\n\n", array_filter(array(
                trim((string) ($style_definition['custom_css'] ?? '')),
                trim((string) ($normalized_settings['override_css'] ?? '')),
            )))),
            'is_builtin' => !empty($style_definition['is_builtin']),
        );
    }

    /**
     * Get normalized plugin settings with decrypted runtime secrets.
     */
    public function get_settings() {
        $stored_settings = get_option('menumatic_settings', array());
        if (!is_array($stored_settings)) {
            $stored_settings = array();
        }

        $settings = array_merge($this->get_default_settings(), $stored_settings);
        $supported_providers = $this->get_supported_ai_providers();
        $supported_openai_models = $this->get_supported_openai_models();
        $supported_openrouter_models = $this->get_supported_openrouter_models();

        if (!isset($supported_providers[$settings['ai_provider']])) {
            $settings['ai_provider'] = 'openai';
        }

        if (!isset($supported_openai_models[$settings['openai_model']])) {
            $settings['openai_model'] = 'gpt-5.4-mini';
        }

        if (!isset($supported_openrouter_models[$settings['openrouter_model']])) {
            $settings['openrouter_model'] = 'mistralai/ministral-8b-2512';
        }

        $settings['default_menu_style_id'] = $this->normalize_menu_style_id(
            $settings['default_menu_style_id'] ?? ($settings['default_menu_style'] ?? 'preset-modern')
        );
        $settings['default_menu_style'] = $this->get_menu_style_definition($settings['default_menu_style_id'])['preset_slug'];

        $settings['openai_api_key'] = $this->get_secret_from_settings($stored_settings, 'openai_api_key');
        $settings['openrouter_api_key'] = $this->get_secret_from_settings($stored_settings, 'openrouter_api_key');

        return $settings;
    }

    /**
     * Migrate legacy plaintext settings into encrypted secret fields.
     */
    public function maybe_migrate_settings() {
        $stored_settings = get_option('menumatic_settings', array());
        if (!is_array($stored_settings)) {
            return;
        }

        $updated = false;
        $defaults = $this->get_default_settings();

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $stored_settings)) {
                $stored_settings[$key] = $value;
                $updated = true;
            }
        }

        if (!empty($stored_settings['openai_api_key']) && empty($stored_settings['openai_api_key_encrypted'])) {
            $stored_settings['openai_api_key_encrypted'] = $this->encrypt_secret($stored_settings['openai_api_key']);
            unset($stored_settings['openai_api_key']);
            $updated = true;
        } else if (isset($stored_settings['openai_api_key'])) {
            unset($stored_settings['openai_api_key']);
            $updated = true;
        }

        if (!empty($stored_settings['openrouter_api_key']) && empty($stored_settings['openrouter_api_key_encrypted'])) {
            $stored_settings['openrouter_api_key_encrypted'] = $this->encrypt_secret($stored_settings['openrouter_api_key']);
            unset($stored_settings['openrouter_api_key']);
            $updated = true;
        } else if (isset($stored_settings['openrouter_api_key'])) {
            unset($stored_settings['openrouter_api_key']);
            $updated = true;
        }

        if ($updated) {
            update_option('menumatic_settings', $stored_settings);
        }
    }

    /**
     * Save sanitized plugin settings.
     */
    public function save_settings($input) {
        $existing_settings = get_option('menumatic_settings', array());
        if (!is_array($existing_settings)) {
            $existing_settings = array();
        }

        $settings = array_merge($this->get_default_settings(), $existing_settings);
        $supported_providers = $this->get_supported_ai_providers();
        $supported_openai_models = $this->get_supported_openai_models();
        $supported_openrouter_models = $this->get_supported_openrouter_models();

        $selected_provider = sanitize_text_field($input['ai_provider'] ?? 'openai');
        if (!isset($supported_providers[$selected_provider])) {
            $selected_provider = 'openai';
        }

        $selected_openai_model = sanitize_text_field($input['openai_model'] ?? 'gpt-5.4-mini');
        if (!isset($supported_openai_models[$selected_openai_model])) {
            $selected_openai_model = 'gpt-5.4-mini';
        }

        $selected_openrouter_model = sanitize_text_field($input['openrouter_model'] ?? 'mistralai/ministral-8b-2512');
        if (!isset($supported_openrouter_models[$selected_openrouter_model])) {
            $selected_openrouter_model = 'mistralai/ministral-8b-2512';
        }

        $settings['ai_provider'] = $selected_provider;
        $settings['openai_model'] = $selected_openai_model;
        $settings['openrouter_model'] = $selected_openrouter_model;
        $settings['openai_api_key_encrypted'] = $this->resolve_secret_value(
            $input['openai_api_key'] ?? '',
            !empty($input['clear_openai_api_key']),
            $settings['openai_api_key_encrypted'] ?? ''
        );
        $settings['openrouter_api_key_encrypted'] = $this->resolve_secret_value(
            $input['openrouter_api_key'] ?? '',
            !empty($input['clear_openrouter_api_key']),
            $settings['openrouter_api_key_encrypted'] ?? ''
        );
        $settings['max_file_size'] = intval($input['max_file_size'] ?? 5);
        $settings['allowed_file_types'] = array_map('sanitize_text_field', $input['allowed_file_types'] ?? array());
        $settings['default_menu_style_id'] = $this->normalize_menu_style_id(
            $input['default_menu_style_id'] ?? ($input['default_menu_style'] ?? 'preset-modern')
        );
        $settings['default_menu_style'] = $this->get_menu_style_definition($settings['default_menu_style_id'])['preset_slug'];
        $settings['enable_caching'] = isset($input['enable_caching']);
        $settings['ai_prompt_template'] = wp_kses_post($input['ai_prompt_template'] ?? '');

        unset($settings['openai_api_key'], $settings['openrouter_api_key']);

        update_option('menumatic_settings', $settings);

        return $this->get_settings();
    }

    /**
     * Get the active provider configuration used by the AI client.
     */
    public function get_ai_provider_config($settings = null) {
        if (!is_array($settings)) {
            $settings = $this->get_settings();
        }

        $provider = $settings['ai_provider'] ?? 'openai';
        $api_key = '';
        $model = '';
        $api_url = '';

        if ($provider === 'openrouter') {
            $api_key = $settings['openrouter_api_key'] ?? '';
            $model = $settings['openrouter_model'] ?? 'mistralai/ministral-8b-2512';
            $api_url = 'https://openrouter.ai/api/v1/chat/completions';
        } else {
            $provider = 'openai';
            $api_key = $settings['openai_api_key'] ?? '';
            $model = $settings['openai_model'] ?? 'gpt-5.4-mini';
            $api_url = 'https://api.openai.com/v1/chat/completions';
        }

        return array(
            'provider' => $provider,
            'provider_label' => $this->get_ai_provider_label($provider),
            'api_key' => $api_key,
            'model' => $model,
            'api_url' => $api_url,
        );
    }

    /**
     * Check whether the selected AI provider is configured.
     */
    public function is_ai_configured($settings = null) {
        $config = $this->get_ai_provider_config($settings);

        return !empty($config['api_key']);
    }

    /**
     * Get a provider label for UI messages.
     */
    public function get_ai_provider_label($provider = null) {
        $providers = $this->get_supported_ai_providers();
        $provider = $provider ?: 'openai';

        return $providers[$provider] ?? __('OpenAI', 'menumatic');
    }

    /**
     * Get a masked label for a stored secret.
     */
    public function get_masked_secret_label($secret) {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }

        if (strlen($secret) <= 8) {
            return str_repeat('*', strlen($secret));
        }

        return substr($secret, 0, 4) . str_repeat('*', max(strlen($secret) - 8, 4)) . substr($secret, -4);
    }

    /**
     * Resolve a secret field from submitted settings.
     */
    private function resolve_secret_value($submitted_value, $clear_secret, $existing_value) {
        if ($clear_secret) {
            return '';
        }

        $submitted_value = trim((string) $submitted_value);
        if ($submitted_value === '') {
            return $existing_value;
        }

        return $this->encrypt_secret($submitted_value);
    }

    /**
     * Get a secret value from stored settings, decrypting if needed.
     */
    private function get_secret_from_settings($settings, $base_key) {
        $encrypted_key = $base_key . '_encrypted';
        if (!empty($settings[$encrypted_key])) {
            return $this->decrypt_secret($settings[$encrypted_key]);
        }

        return isset($settings[$base_key]) ? (string) $settings[$base_key] : '';
    }

    /**
     * Encrypt a secret for option storage.
     */
    private function encrypt_secret($secret) {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
            return $secret;
        }

        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if (!$iv_length) {
            return $secret;
        }

        try {
            $iv = random_bytes($iv_length);
        } catch (Exception $e) {
            return $secret;
        }

        $encrypted = openssl_encrypt($secret, $cipher, $this->get_encryption_key(), OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return $secret;
        }

        return 'enc::' . base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a stored secret.
     */
    private function decrypt_secret($secret) {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }

        if (strpos($secret, 'enc::') !== 0) {
            return $secret;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $decoded = base64_decode(substr($secret, 5), true);
        if ($decoded === false) {
            return '';
        }

        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if (!$iv_length || strlen($decoded) <= $iv_length) {
            return '';
        }

        $iv = substr($decoded, 0, $iv_length);
        $payload = substr($decoded, $iv_length);
        $decrypted = openssl_decrypt($payload, $cipher, $this->get_encryption_key(), OPENSSL_RAW_DATA, $iv);

        return $decrypted === false ? '' : $decrypted;
    }

    /**
     * Build a stable encryption key from WordPress salts.
     */
    private function get_encryption_key() {
        $key_material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . wp_salt('nonce');

        return hash('sha256', $key_material, true);
    }

    /**
     * Register Gutenberg blocks for shortcode equivalents.
     */
    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'menumatic-blocks',
            MENUMATIC_PLUGIN_URL . 'assets/js/blocks.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render'),
            MENUMATIC_VERSION,
            true
        );

        wp_localize_script('menumatic-blocks', 'menumatic_blocks_data', array(
            'menus' => $this->get_menu_block_options(),
        ));

        register_block_type('menumatic/menu-display', array(
            'editor_script' => 'menumatic-blocks',
            'render_callback' => array($this, 'render_menu_display_block'),
            'attributes' => array(
                'name' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'id' => array(
                    'type' => 'number',
                    'default' => 0,
                ),
            ),
        ));

        register_block_type('menumatic/menu-manager', array(
            'editor_script' => 'menumatic-blocks',
            'render_callback' => array($this, 'render_menu_manager_block'),
            'attributes' => array(
                'allowUpload' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
                'allowDelete' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
                'maxMenus' => array(
                    'type' => 'number',
                    'default' => 10,
                ),
            ),
        ));
    }
    
    /**
     * Handle image upload via AJAX
     */
    public function handle_image_upload() {
        error_log('MenuMatic: handle_image_upload() called');
        error_log('MenuMatic: $_POST data: ' . print_r($_POST, true));
        error_log('MenuMatic: $_FILES data: ' . print_r($_FILES, true));
        
        check_ajax_referer('menumatic_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('MenuMatic: Insufficient permissions for user');
            wp_send_json_error(__('Insufficient permissions', 'menumatic'));
        }
        
        if (!isset($_FILES['menu_image'])) {
            error_log('MenuMatic: No menu_image file in $_FILES');
            wp_send_json_error(__('No file uploaded', 'menumatic'));
        }
        
        $file = $_FILES['menu_image'];
        $settings = $this->get_settings();
        
        // Validate file
        $allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : array('jpg', 'jpeg', 'png', 'webp');
        $max_size = isset($settings['max_file_size']) ? $settings['max_file_size'] * 1024 * 1024 : 5 * 1024 * 1024;
        
        if (empty($file['name'])) {
            wp_send_json_error(__('Invalid file name', 'menumatic'));
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error(__('Invalid file type', 'menumatic'));
        }
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('File size too large', 'menumatic'));
        }
        
        // Upload file
        $upload_dir = wp_upload_dir();
        $menumatic_dir = $upload_dir['basedir'] . '/menumatic';
        
        if (!file_exists($menumatic_dir)) {
            wp_mkdir_p($menumatic_dir);
        }
        
        $filename = uniqid() . '_' . sanitize_file_name($file['name']);
        $file_path = $menumatic_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_success(array(
                'file_path' => $file_path,
                'filename' => $filename,
                'upload_id' => uniqid()
            ));
        } else {
            wp_send_json_error(__('File upload failed', 'menumatic'));
        }
    }
    
    /**
     * Process menu with AI
     */
    public function process_menu_with_ai() {
        // Aggressively suppress and control errors and output
        $old_error_reporting = error_reporting(0);
        ini_set('display_errors', 0);
        $old_error_handler = set_error_handler(function(){});

        ob_start();

        try {
            // All processing is inside this try block
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_nonce')) {
                error_log('MenuMatic: Nonce verification failed. Received nonce: ' . ($_POST['nonce'] ?? 'Not set'));
                throw new Exception(__('Security check failed. Please refresh the page and try again.', 'menumatic'));
            }
            error_log('MenuMatic: Nonce verification passed.');

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'menumatic'));
            }

            $file_path = sanitize_text_field($_POST['file_path'] ?? '');
            if (empty($file_path) || !file_exists($file_path) || !is_readable($file_path)) {
                throw new Exception(__('A valid file was not provided or is unreadable.', 'menumatic'));
            }

            // Check for stray output right before calling the API processor
            $stray_output = ob_get_clean();
            if (!empty($stray_output)) {
                throw new Exception('Stray server output detected before AI processing, which indicates a PHP error. Output: ' . esc_html(substr($stray_output, 0, 300)));
            }
            ob_start(); // Start a fresh buffer for the AI call

            // Process with the configured AI provider
            $openai = new MenuMatic_OpenAI_API();
            $options = [
                'extract_allergens' => isset($_POST['extract_allergens']),
                'detect_dietary'    => isset($_POST['detect_dietary']),
                'preserve_formatting' => isset($_POST['preserve_formatting'])
            ];
            $result = $openai->process_menu_image($file_path, $options);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Save to database
            global $wpdb;
            $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
            $menu_title = sanitize_text_field($_POST['menu_title'] ?? 'New Menu');
            $menu_style_settings = $this->normalize_menu_style_settings(array(
                'style_id' => $this->normalize_menu_style_id($_POST['menu_style'] ?? ($this->get_settings()['default_menu_style_id'] ?? 'preset-modern')),
                'layout' => $this->get_default_menu_layout_options(),
                'override_css' => '',
            ));

            $inserted = $wpdb->insert($table_name, [
                'title'               => $menu_title,
                'original_image_path' => $file_path,
                'processed_html'      => $result['html'],
                'menu_data'           => $result['raw_json'],
                'style_settings'      => $this->encode_menu_style_settings($menu_style_settings),
                'user_id'             => get_current_user_id(),
                'status'              => 'active'
            ]);

            if ($inserted === false) {
                throw new Exception(__('Failed to save menu to database.', 'menumatic') . ' ' . $wpdb->last_error);
            }

            $menu_id = $wpdb->insert_id;
            @unlink($file_path);

            // Prepare response data
            $response_data = [
                'menu_id' => $menu_id,
                'html'    => $this->wrap_menu_html($result['html'], $menu_style_settings, $menu_id),
                'title'   => $menu_title
            ];

            // Add chunking information if available
            if (isset($result['chunking_info'])) {
                $response_data['chunking_info'] = $result['chunking_info'];
            }

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            error_log('MenuMatic: Exception caught in process_menu_with_ai: ' . $error_message);
            // Ensure all buffers are cleaned on error
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            wp_send_json_error($error_message);

        } finally {
            // Restore original error handling
            error_reporting($old_error_reporting);
            ini_set('display_errors', 1);
            set_error_handler($old_error_handler);
        }
    }
    
    /**
     * Preview menu
     */
    public function preview_menu() {
        check_ajax_referer('menumatic_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        
        if (!$menu) {
            wp_send_json_error(__('Menu not found', 'menumatic'));
        }
        
        $style_settings = $this->normalize_menu_style_settings($menu->style_settings ?: '{}');
        $html = $this->wrap_menu_html($menu->processed_html ?: '', $style_settings, $menu_id);
        
        wp_send_json_success($html);
    }
    
    /**
     * Display menu shortcode
     */
    public function display_menu_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => ''
        ), $atts);

        $menu = null;
        if (!empty($atts['name'])) {
            $menu = $this->get_menu_by_name(sanitize_text_field($atts['name']));
        } elseif (!empty($atts['id'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
            $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND status = 'active'", intval($atts['id'])));
        }

        if (!$menu) {
            return '<p>' . __('Menu not found', 'menumatic') . '</p>';
        }
        
        $style_settings = $this->normalize_menu_style_settings($menu->style_settings ?: '{}');

        return $this->wrap_menu_html($menu->processed_html ?: '', $style_settings, intval($menu->id));
    }

    /**
     * Render callback for the Menu Display block.
     */
    public function render_menu_display_block($attributes) {
        $atts = array(
            'id' => intval($attributes['id'] ?? 0),
            'name' => sanitize_text_field($attributes['name'] ?? ''),
        );

        return $this->display_menu_shortcode($atts);
    }
    
    /**
     * Frontend menu management shortcode
     */
    public function frontend_manage_shortcode($atts) {
        $atts = shortcode_atts(array(
            'allow_upload' => 'true',
            'allow_delete' => 'true',
            'max_menus' => 10
        ), $atts);
        
        // Check if API is configured
        $settings = $this->get_settings();

        if (!$this->is_ai_configured($settings)) {
            return '<div class="menumatic-frontend-error"><p>' . __('MenuMatic is not configured. Please contact the site administrator.', 'menumatic') . '</p></div>';
        }
        
        // Get existing menus
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $menus = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC LIMIT " . intval($atts['max_menus']));
        $style_library = $this->get_menu_style_library();
        $layout_labels = $this->get_menu_layout_option_labels();
        
        $nonce = wp_create_nonce('menumatic_frontend_nonce');
        
        ob_start();
        ?>
        <div class="menumatic-frontend-manager" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="menumatic-frontend-header">
                <h3><?php _e('Menu Manager', 'menumatic'); ?></h3>
                <p><?php _e('Upload menu images to create responsive HTML menus, or manage existing ones.', 'menumatic'); ?></p>
            </div>
            
            <?php if ($atts['allow_upload'] === 'true'): ?>
            <div class="menumatic-frontend-upload">
                <h4><?php _e('Update Menu', 'menumatic'); ?></h4>
                <form id="menumatic-frontend-upload-form" enctype="multipart/form-data">
                    <div class="upload-group">
                        <label for="frontend-menu-select"><?php _e('Select Menu to Update:', 'menumatic'); ?></label>
                        <select id="frontend-menu-select" name="menu_id" required>
                            <option value=""><?php _e('Choose a menu...', 'menumatic'); ?></option>
                            <?php foreach ($menus as $menu): ?>
                                <option value="<?php echo $menu->id; ?>" data-style-id="<?php echo esc_attr($settings['default_menu_style_id'] ?? 'preset-modern'); ?>">
                                    <?php echo esc_html($menu->title); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new"><?php _e('Create New Menu', 'menumatic'); ?></option>
                        </select>
                    </div>
                    
                    <div class="upload-group" id="new-menu-title-group" style="display: none;">
                        <label for="frontend-menu-title"><?php _e('New Menu Title:', 'menumatic'); ?></label>
                        <input type="text" id="frontend-menu-title" name="menu_title" placeholder="<?php esc_attr_e('e.g., Dinner Menu', 'menumatic'); ?>">
                    </div>
                    
                    <div class="upload-group">
                        <label for="frontend-menu-image"><?php _e('Menu Image:', 'menumatic'); ?></label>
                        <input type="file" id="frontend-menu-image" name="menu_image" accept="image/*" required>
                        <small><?php _e('Upload a clear photo of your menu (JPG, PNG, max 10MB)', 'menumatic'); ?></small>
                    </div>

                    <div class="upload-group">
                        <label for="frontend-menu-style"><?php _e('Style:', 'menumatic'); ?></label>
                        <select id="frontend-menu-style" name="menu_style" data-default-style-id="<?php echo esc_attr($settings['default_menu_style_id'] ?? 'preset-modern'); ?>">
                            <?php foreach ($style_library as $style_id => $style_definition): ?>
                                <option value="<?php echo esc_attr($style_id); ?>" <?php selected($settings['default_menu_style_id'] ?? 'preset-modern', $style_id); ?>>
                                    <?php echo esc_html($style_definition['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php _e('Choose a starting style. You can fine-tune it later in the menu editor.', 'menumatic'); ?></small>
                    </div>

                    <div class="upload-group" id="frontend-update-mode-group" style="display: none;">
                        <label for="frontend-update-mode"><?php _e('Update Mode:', 'menumatic'); ?></label>
                        <select id="frontend-update-mode" name="update_mode">
                            <option value="overwrite"><?php _e('Overwrite Menu', 'menumatic'); ?></option>
                            <option value="add"><?php _e('Add To Menu', 'menumatic'); ?></option>
                        </select>
                        <small><?php _e('Overwrite rebuilds the menu from the new upload while preserving image-backed matched items. Add To Menu appends new items and updates prices on matching items.', 'menumatic'); ?></small>
                    </div>
                    
                    <button type="submit" class="menumatic-btn menumatic-btn-primary">
                        <span class="btn-text"><?php _e('Update Menu', 'menumatic'); ?></span>
                        <span class="btn-loading" style="display: none;"><?php _e('Processing...', 'menumatic'); ?></span>
                    </button>
                </form>
                
                <div id="frontend-upload-progress" class="upload-progress" style="display: none;">
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <p class="progress-text"><?php _e('Processing your menu...', 'menumatic'); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="menumatic-frontend-menus">
                <h4><?php _e('Existing Menus', 'menumatic'); ?></h4>
                
                <?php if (empty($menus)): ?>
                    <p class="no-menus"><?php _e('No menus found. Upload your first menu above!', 'menumatic'); ?></p>
                <?php else: ?>
                    <div class="menu-pill-list" role="tablist" aria-label="<?php esc_attr_e('Existing menus', 'menumatic'); ?>">
                        <?php foreach ($menus as $menu): ?>
                            <button type="button" class="menu-pill" data-menu-id="<?php echo $menu->id; ?>" data-menu-name="<?php echo esc_attr($menu->title); ?>">
                                <?php echo esc_html($menu->title); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="menu-panels">
                        <?php foreach ($menus as $menu): ?>
                            <?php
                            $style_settings = $this->normalize_menu_style_settings($menu->style_settings ?: '{}', $settings['default_menu_style_id'] ?? 'preset-modern');
                            $resolved_style = $this->resolve_menu_render_style($style_settings, $settings['default_menu_style_id'] ?? 'preset-modern');
                            $selected_style_definition = $this->get_menu_style_definition($resolved_style['style_id']);
                            ?>
                            <section class="menu-card" data-menu-id="<?php echo $menu->id; ?>" style="display: none;">
                                <div class="menu-card-header">
                                    <div>
                                        <p class="menu-eyebrow"><?php _e('Selected Menu', 'menumatic'); ?></p>
                                        <h5><?php echo esc_html($menu->title); ?></h5>
                                        <span class="menu-date"><?php echo date_i18n('M j, Y', strtotime($menu->created_at ?: 'now')); ?></span>
                                    </div>
                                    <div class="menu-card-actions">
                                        <button class="menumatic-btn menumatic-btn-small preview-menu" data-menu-id="<?php echo $menu->id; ?>">
                                            <?php _e('Preview', 'menumatic'); ?>
                                        </button>
                                        
                                        <button class="menumatic-btn menumatic-btn-small edit-manually" data-menu-id="<?php echo $menu->id; ?>">
                                            <?php _e('Edit Content', 'menumatic'); ?>
                                        </button>
                                        
                                        <button class="menumatic-btn menumatic-btn-small regenerate-menu" data-menu-id="<?php echo $menu->id; ?>">
                                            <?php _e('Regenerate', 'menumatic'); ?>
                                        </button>
                                        
                                        <?php if ($atts['allow_delete'] === 'true'): ?>
                                        <button class="menumatic-btn menumatic-btn-small menumatic-btn-danger delete-menu" data-menu-id="<?php echo $menu->id; ?>">
                                            <?php _e('Delete', 'menumatic'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="menu-card-preview">
                                    <?php echo $this->wrap_menu_html($menu->processed_html ?: '', $style_settings, $menu->id); ?>
                                </div>

                                <div class="menu-style-editor" data-menu-id="<?php echo $menu->id; ?>">
                                    <div class="menu-style-editor-header">
                                        <div>
                                            <h6><?php _e('Styles', 'menumatic'); ?></h6>
                                        <p><?php _e('Pick a saved style, tune the layout live, then use the CSS tab for reusable style CSS and menu-specific overrides.', 'menumatic'); ?></p>
                                        </div>
                                        <div class="menu-style-editor-actions">
                                            <span class="style-chip"><?php echo esc_html($resolved_style['name'] ?? __('Custom', 'menumatic')); ?></span>
                                            <button type="button" class="menumatic-btn menumatic-btn-small save-style-only" data-menu-id="<?php echo $menu->id; ?>">
                                                <?php _e('Save Style', 'menumatic'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="menu-style-tabs" data-menu-id="<?php echo $menu->id; ?>">
                                        <button type="button" class="menu-style-tab is-active" data-menu-id="<?php echo $menu->id; ?>" data-tab="options"><?php _e('Options', 'menumatic'); ?></button>
                                        <button type="button" class="menu-style-tab" data-menu-id="<?php echo $menu->id; ?>" data-tab="css"><?php _e('CSS', 'menumatic'); ?></button>
                                    </div>

                                    <div class="menu-style-panel is-active" data-menu-id="<?php echo $menu->id; ?>" data-panel="options">
                                        <div class="menu-style-grid">
                                            <div class="upload-group">
                                                <label for="menu-style-select-<?php echo $menu->id; ?>"><?php _e('Style', 'menumatic'); ?></label>
                                                <select id="menu-style-select-<?php echo $menu->id; ?>" class="menu-style-select" data-menu-id="<?php echo $menu->id; ?>">
                                                    <?php foreach ($style_library as $style_id => $style_definition): ?>
                                                        <option value="<?php echo esc_attr($style_id); ?>" <?php selected($resolved_style['style_id'], $style_id); ?> data-description="<?php echo esc_attr($style_definition['description']); ?>">
                                                            <?php echo esc_html($style_definition['name']); ?>
                                                            <?php if (!empty($style_definition['is_builtin'])): ?>
                                                                <?php echo esc_html__(' (Built-in)', 'menumatic'); ?>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="style-preset-description"><?php echo esc_html($resolved_style['description'] ?? ''); ?></small>
                                            </div>

                                            <div class="upload-group">
                                                <label for="menu-columns-<?php echo $menu->id; ?>"><?php _e('Columns', 'menumatic'); ?></label>
                                                <select id="menu-columns-<?php echo $menu->id; ?>" class="menu-layout-control" data-menu-id="<?php echo $menu->id; ?>" data-layout-field="columns">
                                                    <?php foreach ($layout_labels['columns'] as $option_value => $option_label): ?>
                                                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($resolved_style['layout']['columns'], $option_value); ?>>
                                                            <?php echo esc_html($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="upload-group">
                                                <label for="menu-price-position-<?php echo $menu->id; ?>"><?php _e('Price Position', 'menumatic'); ?></label>
                                                <select id="menu-price-position-<?php echo $menu->id; ?>" class="menu-layout-control" data-menu-id="<?php echo $menu->id; ?>" data-layout-field="price_position">
                                                    <?php foreach ($layout_labels['price_position'] as $option_value => $option_label): ?>
                                                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($resolved_style['layout']['price_position'], $option_value); ?>>
                                                            <?php echo esc_html($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="upload-group">
                                                <label for="menu-text-layout-<?php echo $menu->id; ?>"><?php _e('Name / Description Layout', 'menumatic'); ?></label>
                                                <select id="menu-text-layout-<?php echo $menu->id; ?>" class="menu-layout-control" data-menu-id="<?php echo $menu->id; ?>" data-layout-field="text_layout">
                                                    <?php foreach ($layout_labels['text_layout'] as $option_value => $option_label): ?>
                                                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($resolved_style['layout']['text_layout'], $option_value); ?>>
                                                            <?php echo esc_html($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="menu-style-panel" data-menu-id="<?php echo $menu->id; ?>" data-panel="css">
                                        <div class="upload-group">
                                            <label for="menu-style-source-css-<?php echo $menu->id; ?>"><?php _e('Selected Global Style CSS', 'menumatic'); ?></label>
                                            <textarea id="menu-style-source-css-<?php echo $menu->id; ?>" class="menu-style-source-css" data-menu-id="<?php echo $menu->id; ?>" rows="18" readonly><?php echo esc_textarea($selected_style_definition['custom_css'] ?? ''); ?></textarea>
                                            <small class="menu-style-source-note"><?php _e('This shows the full reusable stylesheet for the selected global style. Edit it from MenuMatic > Styles.', 'menumatic'); ?></small>
                                        </div>

                                        <div class="upload-group">
                                            <label for="menu-custom-css-<?php echo $menu->id; ?>"><?php _e('Menu Override CSS', 'menumatic'); ?></label>
                                            <textarea id="menu-custom-css-<?php echo $menu->id; ?>" class="menu-custom-css" data-menu-id="<?php echo $menu->id; ?>" rows="12" placeholder="<?php esc_attr_e('{{scope}} .menu-item { border-radius: 24px; }', 'menumatic'); ?>"><?php echo esc_textarea($style_settings['override_css']); ?></textarea>
                                            <small><?php _e('This CSS is scoped to this menu only. Create or edit reusable global styles from MenuMatic > Styles.', 'menumatic'); ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="menu-visual-editor" id="visual-editor-<?php echo $menu->id; ?>" style="display: none;" data-menu-id="<?php echo $menu->id; ?>">
                                    <div class="visual-editor-header">
                                        <h5><?php _e('Edit Menu:', 'menumatic'); ?> <?php echo esc_html($menu->title); ?></h5>
                                        <div class="editor-actions">
                                            <button type="button" class="menumatic-btn menumatic-btn-small add-section-frontend"><?php _e('Add Section', 'menumatic'); ?></button>
                                            <button type="button" class="menumatic-btn menumatic-btn-primary save-menu-frontend"><?php _e('Save & Update Menu', 'menumatic'); ?></button>
                                            <button type="button" class="menumatic-btn menumatic-btn-secondary cancel-edit"><?php _e('Cancel', 'menumatic'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <div class="visual-editor-main">
                                        <div class="menu-editor-panel">
                                                                        <div class="visual-menu-editor menu-container" id="visual-menu-editor-<?php echo $menu->id; ?>" data-drop-zone="main">
                                <!-- Menu sections will be populated by JavaScript -->
                            </div>
                                        </div>
                                        
                                        <div class="editor-sidebar">
                                            <h6><?php _e('Other Content', 'menumatic'); ?></h6>
                                            <div class="other-content-section" id="other-content-section-<?php echo $menu->id; ?>">
                                                <!-- Other content will be populated by JavaScript -->
                                            </div>
                                            <button type="button" class="menumatic-btn menumatic-btn-small add-other-content-frontend"><?php _e('Add Other Content', 'menumatic'); ?></button>
                                            
                                            <div class="editor-help">
                                                <h6><?php _e('Editing Tips:', 'menumatic'); ?></h6>
                                                <ul>
                                                    <li><?php _e('Click on any text to edit it directly', 'menumatic'); ?></li>
                                                    <li><?php _e('Use "Add Item" to add menu items to a section', 'menumatic'); ?></li>
                                                    <li><?php _e('Use "Add Section" to create new menu categories', 'menumatic'); ?></li>
                                                    <li><?php _e('Check dietary indicators as needed', 'menumatic'); ?></li>
                                                    <li><?php _e('Save changes to update the menu display', 'menumatic'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Modal for preview -->
            <div id="menumatic-preview-modal" class="menumatic-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h4><?php _e('Menu Preview', 'menumatic'); ?></h4>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="preview-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render callback for the Menu Manager block.
     */
    public function render_menu_manager_block($attributes) {
        $atts = array(
            'allow_upload' => !empty($attributes['allowUpload']) ? 'true' : 'false',
            'allow_delete' => !empty($attributes['allowDelete']) ? 'true' : 'false',
            'max_menus' => max(1, intval($attributes['maxMenus'] ?? 10)),
        );

        return $this->frontend_manage_shortcode($atts);
    }

    /**
     * Update the global default style selection via AJAX.
     */
    public function set_global_style() {
        $nonce_field = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $is_valid_admin = wp_verify_nonce($nonce_field, 'menumatic_nonce');
        $is_valid_frontend = wp_verify_nonce($nonce_field, 'menumatic_frontend_nonce');

        if (!$is_valid_admin && !$is_valid_frontend) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'menumatic'));
        }

        $style_id = $this->normalize_menu_style_id($_POST['style_id'] ?? 'preset-modern');
        $stored_settings = get_option('menumatic_settings', array());
        if (!is_array($stored_settings)) {
            $stored_settings = array();
        }

        $stored_settings['default_menu_style_id'] = $style_id;
        $stored_settings['default_menu_style'] = $this->get_menu_style_definition($style_id)['preset_slug'];
        update_option('menumatic_settings', $stored_settings);

        wp_send_json_success(array(
            'style_id' => $style_id,
            'style_name' => $this->get_menu_style_definition($style_id)['name'],
        ));
    }

    /**
     * Preserve existing item images when refreshed JSON omits image fields.
     */
    private function merge_menu_json_preserving_images($new_raw_json, $existing_raw_json) {
        if (empty($new_raw_json) || empty($existing_raw_json)) {
            return $new_raw_json;
        }

        $new_data = json_decode($new_raw_json, true);
        $existing_data = json_decode($existing_raw_json, true);

        if (!is_array($new_data) || !is_array($existing_data)) {
            return $new_raw_json;
        }

        list($section_image_map, $name_image_map) = $this->build_item_image_maps($existing_data);
        if (empty($section_image_map) && empty($name_image_map)) {
            return $new_raw_json;
        }

        if (isset($new_data['sections']) && is_array($new_data['sections'])) {
            $this->apply_preserved_images_to_sections($new_data['sections'], $section_image_map, $name_image_map);
        }

        if (isset($new_data['content']) && is_array($new_data['content'])) {
            foreach ($new_data['content'] as &$content) {
                if (!is_array($content) || ($content['type'] ?? '') !== 'section') {
                    continue;
                }

                $section_title = $content['title'] ?? '';
                if (isset($content['items']) && is_array($content['items'])) {
                    $this->apply_preserved_images_to_items($content['items'], $section_title, $section_image_map, $name_image_map);
                }
            }
            unset($content);
        }

        $encoded = wp_json_encode($new_data);
        return ($encoded !== false) ? $encoded : $new_raw_json;
    }

    /**
     * Build lookup maps of existing item images.
     */
    private function build_item_image_maps($menu_data) {
        $section_image_map = array();
        $name_image_candidates = array();

        $collect_from_items = function($section_title, $items) use (&$section_image_map, &$name_image_candidates) {
            if (!is_array($items)) {
                return;
            }

            $normalized_section = $this->normalize_menu_item_value($section_title);

            foreach ($items as $item) {
                if (!is_array($item) || empty($item['image']) || empty($item['name'])) {
                    continue;
                }

                $normalized_name = $this->normalize_menu_item_value($item['name']);
                if ($normalized_name === '') {
                    continue;
                }

                $image = trim((string) $item['image']);
                if ($image === '') {
                    continue;
                }

                if ($normalized_section !== '') {
                    $section_key = $normalized_section . '|' . $normalized_name;
                    if (!isset($section_image_map[$section_key])) {
                        $section_image_map[$section_key] = $image;
                    }
                }

                if (!isset($name_image_candidates[$normalized_name])) {
                    $name_image_candidates[$normalized_name] = array();
                }
                if (!in_array($image, $name_image_candidates[$normalized_name], true)) {
                    $name_image_candidates[$normalized_name][] = $image;
                }
            }
        };

        if (isset($menu_data['sections']) && is_array($menu_data['sections'])) {
            foreach ($menu_data['sections'] as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $collect_from_items($section['title'] ?? '', $section['items'] ?? array());
            }
        }

        if (isset($menu_data['content']) && is_array($menu_data['content'])) {
            foreach ($menu_data['content'] as $content) {
                if (!is_array($content) || ($content['type'] ?? '') !== 'section') {
                    continue;
                }
                $collect_from_items($content['title'] ?? '', $content['items'] ?? array());
            }
        }

        $name_image_map = array();
        foreach ($name_image_candidates as $normalized_name => $images) {
            if (count($images) === 1) {
                $name_image_map[$normalized_name] = $images[0];
            }
        }

        return array($section_image_map, $name_image_map);
    }

    /**
     * Apply preserved images to all items in section arrays.
     */
    private function apply_preserved_images_to_sections(&$sections, $section_image_map, $name_image_map) {
        if (!is_array($sections)) {
            return;
        }

        foreach ($sections as &$section) {
            if (!is_array($section) || !isset($section['items']) || !is_array($section['items'])) {
                continue;
            }

            $section_title = $section['title'] ?? '';
            $this->apply_preserved_images_to_items($section['items'], $section_title, $section_image_map, $name_image_map);
        }
        unset($section);
    }

    /**
     * Apply preserved images to a flat list of menu items.
     */
    private function apply_preserved_images_to_items(&$items, $section_title, $section_image_map, $name_image_map) {
        if (!is_array($items)) {
            return;
        }

        $normalized_section = $this->normalize_menu_item_value($section_title);

        foreach ($items as &$item) {
            if (!is_array($item) || !empty($item['image']) || empty($item['name'])) {
                continue;
            }

            $normalized_name = $this->normalize_menu_item_value($item['name']);
            if ($normalized_name === '') {
                continue;
            }

            $preserved_image = '';
            if ($normalized_section !== '') {
                $section_key = $normalized_section . '|' . $normalized_name;
                if (isset($section_image_map[$section_key])) {
                    $preserved_image = $section_image_map[$section_key];
                }
            }

            if ($preserved_image === '' && isset($name_image_map[$normalized_name])) {
                $preserved_image = $name_image_map[$normalized_name];
            }

            if ($preserved_image !== '') {
                $item['image'] = $preserved_image;
            }
        }
        unset($item);
    }

    /**
     * Normalize text for stable menu item matching.
     */
    private function normalize_menu_item_value($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = wp_strip_all_tags($value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value);
        return strtolower($value);
    }

    /**
     * Menu options for editor selectors.
     */
    public function get_menu_block_options() {
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $menus = $wpdb->get_results("SELECT id, title FROM $table_name WHERE status = 'active' ORDER BY title ASC");

        if (!is_array($menus)) {
            return array();
        }

        return array_map(function($menu) {
            return array(
                'id' => intval($menu->id),
                'name' => (string) $menu->title,
            );
        }, $menus);
    }

    /**
     * Find a menu by exact title.
     */
    private function get_menu_by_name($menu_name) {
        $menu_name = trim((string) $menu_name);
        if ($menu_name === '') {
            return null;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE title = %s AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $menu_name
        ));
    }

    /**
     * Decode raw menu JSON into an array.
     */
    private function decode_menu_json_data($raw_json) {
        if (is_array($raw_json)) {
            return $raw_json;
        }

        if (!is_string($raw_json) || trim($raw_json) === '') {
            return array();
        }

        $decoded = json_decode($raw_json, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Extract menu sections from either sections or content arrays.
     */
    private function extract_menu_sections($menu_data) {
        if (!is_array($menu_data)) {
            return array();
        }

        if (!empty($menu_data['sections']) && is_array($menu_data['sections'])) {
            return $menu_data['sections'];
        }

        $sections = array();
        if (!empty($menu_data['content']) && is_array($menu_data['content'])) {
            foreach ($menu_data['content'] as $content) {
                if (!is_array($content) || ($content['type'] ?? '') !== 'section') {
                    continue;
                }

                $sections[] = array(
                    'title' => $content['title'] ?? '',
                    'items' => is_array($content['items'] ?? null) ? $content['items'] : array(),
                );
            }
        }

        return $sections;
    }

    /**
     * Extract non-section content rows from the ordered content array.
     */
    private function extract_non_section_content($menu_data) {
        if (!is_array($menu_data) || empty($menu_data['content']) || !is_array($menu_data['content'])) {
            return array();
        }

        $content_rows = array();
        foreach ($menu_data['content'] as $content) {
            if (!is_array($content) || ($content['type'] ?? '') === 'section') {
                continue;
            }
            $content_rows[] = $content;
        }

        return $content_rows;
    }

    /**
     * Extract sidebar other content rows.
     */
    private function extract_other_content_rows($menu_data) {
        if (!is_array($menu_data) || empty($menu_data['other_content']) || !is_array($menu_data['other_content'])) {
            return array();
        }

        return $menu_data['other_content'];
    }

    /**
     * Build section and name lookups for existing items.
     */
    private function build_menu_item_lookup_maps($menu_data) {
        $section_lookup = array();
        $name_candidates = array();
        $sections = $this->extract_menu_sections($menu_data);

        foreach ($sections as $section) {
            if (!is_array($section) || empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            $normalized_section = $this->normalize_menu_item_value($section['title'] ?? '');
            foreach ($section['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $normalized_name = $this->normalize_menu_item_value($item['name'] ?? '');
                if ($normalized_name === '') {
                    continue;
                }

                if ($normalized_section !== '') {
                    $section_lookup[$normalized_section . '|' . $normalized_name] = $item;
                }

                if (!isset($name_candidates[$normalized_name])) {
                    $name_candidates[$normalized_name] = array();
                }

                $name_candidates[$normalized_name][] = $item;
            }
        }

        $name_lookup = array();
        foreach ($name_candidates as $normalized_name => $items) {
            if (count($items) === 1) {
                $name_lookup[$normalized_name] = $items[0];
            }
        }

        return array($section_lookup, $name_lookup);
    }

    /**
     * Find a matching existing item by section and name.
     */
    private function find_matching_existing_item($section_title, $item_name, $section_lookup, $name_lookup) {
        $normalized_name = $this->normalize_menu_item_value($item_name);
        if ($normalized_name === '') {
            return null;
        }

        $normalized_section = $this->normalize_menu_item_value($section_title);
        if ($normalized_section !== '') {
            $section_key = $normalized_section . '|' . $normalized_name;
            if (isset($section_lookup[$section_key])) {
                return $section_lookup[$section_key];
            }
        }

        return $name_lookup[$normalized_name] ?? null;
    }

    /**
     * Merge a new item with an existing one, preserving image-backed content.
     */
    private function merge_menu_items_for_update($existing_item, $new_item) {
        $existing_item = is_array($existing_item) ? $existing_item : array();
        $new_item = is_array($new_item) ? $new_item : array();

        $existing_has_image = !empty($existing_item['image']);
        if ($existing_has_image) {
            $merged_item = $existing_item;
            if (empty($merged_item['name']) && !empty($new_item['name'])) {
                $merged_item['name'] = $new_item['name'];
            }

            return $this->resolve_merged_pricing($merged_item, $new_item, $existing_item);
        }

        $merged_item = array_merge($existing_item, $new_item);
        if (empty($merged_item['image']) && !empty($existing_item['image'])) {
            $merged_item['image'] = $existing_item['image'];
        }

        foreach (array('name', 'description', 'price', 'additional_info') as $field) {
            if (isset($new_item[$field]) && trim((string) $new_item[$field]) !== '') {
                $merged_item[$field] = $new_item[$field];
            } elseif (!isset($merged_item[$field]) && isset($existing_item[$field])) {
                $merged_item[$field] = $existing_item[$field];
            }
        }

        if (!empty($new_item['dietary_indicators']) && is_array($new_item['dietary_indicators'])) {
            $merged_item['dietary_indicators'] = $new_item['dietary_indicators'];
        } elseif (!empty($existing_item['dietary_indicators'])) {
            $merged_item['dietary_indicators'] = $existing_item['dietary_indicators'];
        }

        return $this->resolve_merged_pricing($merged_item, $new_item, $existing_item);
    }

    /**
     * Reconcile single price vs. multiple price options when merging items.
     * Multi-price options take precedence; a single price clears stale options.
     */
    private function resolve_merged_pricing($merged_item, $new_item, $existing_item) {
        $new_item = is_array($new_item) ? $new_item : array();
        $existing_item = is_array($existing_item) ? $existing_item : array();

        if (!empty($new_item['price_options']) && is_array($new_item['price_options'])) {
            $merged_item['price_options'] = $new_item['price_options'];
            $merged_item['price'] = '';
        } elseif (array_key_exists('price', $new_item) && trim((string) $new_item['price']) !== '') {
            $merged_item['price'] = $new_item['price'];
            unset($merged_item['price_options']);
        } elseif (!empty($existing_item['price_options']) && is_array($existing_item['price_options'])) {
            $merged_item['price_options'] = $existing_item['price_options'];
        }

        return $merged_item;
    }

    /**
     * Merge sidebar or inline content rows without duplicates.
     */
    private function merge_menu_content_rows($existing_rows, $new_rows) {
        $existing_rows = is_array($existing_rows) ? $existing_rows : array();
        $new_rows = is_array($new_rows) ? $new_rows : array();
        $merged_rows = $existing_rows;
        $seen = array();

        foreach ($existing_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row_key = $this->normalize_menu_item_value(($row['type'] ?? $row['content_type'] ?? '')) . '|' . $this->normalize_menu_item_value($row['content'] ?? '');
            if ($row_key !== '|') {
                $seen[$row_key] = true;
            }
        }

        foreach ($new_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row_key = $this->normalize_menu_item_value(($row['type'] ?? $row['content_type'] ?? '')) . '|' . $this->normalize_menu_item_value($row['content'] ?? '');
            if ($row_key === '|' || isset($seen[$row_key])) {
                continue;
            }

            $merged_rows[] = $row;
            $seen[$row_key] = true;
        }

        return $merged_rows;
    }

    /**
     * Rebuild menu data arrays after section merging.
     */
    private function rebuild_menu_data($existing_data, $sections, $other_content_rows, $non_section_content_rows) {
        $data = is_array($existing_data) ? $existing_data : array();
        $sections = is_array($sections) ? $sections : array();
        $other_content_rows = is_array($other_content_rows) ? $other_content_rows : array();
        $non_section_content_rows = is_array($non_section_content_rows) ? $non_section_content_rows : array();

        $data['sections'] = $sections;
        $data['other_content'] = $other_content_rows;

        $content = array();
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $content[] = array(
                'type' => 'section',
                'title' => $section['title'] ?? '',
                'items' => is_array($section['items'] ?? null) ? $section['items'] : array(),
            );
        }

        foreach ($non_section_content_rows as $row) {
            if (is_array($row)) {
                $content[] = $row;
            }
        }

        if (!empty($content)) {
            $data['content'] = $content;
        }

        return $data;
    }

    /**
     * Merge uploaded menu JSON into an existing menu.
     */
    private function merge_menu_json_for_upload($new_raw_json, $existing_raw_json, $update_mode = 'overwrite') {
        $new_data = $this->decode_menu_json_data($new_raw_json);
        $existing_data = $this->decode_menu_json_data($existing_raw_json);

        if (empty($new_data)) {
            return $new_raw_json;
        }

        if (empty($existing_data)) {
            return $new_raw_json;
        }

        $update_mode = ($update_mode === 'add') ? 'add' : 'overwrite';
        $new_sections = $this->extract_menu_sections($new_data);
        $existing_sections = $this->extract_menu_sections($existing_data);
        list($section_lookup, $name_lookup) = $this->build_menu_item_lookup_maps($existing_data);

        if ($update_mode === 'overwrite') {
            foreach ($new_sections as &$section) {
                if (!is_array($section) || empty($section['items']) || !is_array($section['items'])) {
                    continue;
                }

                foreach ($section['items'] as &$item) {
                    $existing_item = $this->find_matching_existing_item($section['title'] ?? '', $item['name'] ?? '', $section_lookup, $name_lookup);
                    if ($existing_item) {
                        $item = $this->merge_menu_items_for_update($existing_item, $item);
                    }
                }
                unset($item);
            }
            unset($section);

            $rebuilt_data = $this->rebuild_menu_data(
                $new_data,
                $new_sections,
                !empty($new_data['other_content']) ? $this->extract_other_content_rows($new_data) : $this->extract_other_content_rows($existing_data),
                !empty($new_data['content']) ? $this->extract_non_section_content($new_data) : $this->extract_non_section_content($existing_data)
            );
            $encoded = wp_json_encode($rebuilt_data);

            return ($encoded !== false) ? $encoded : $new_raw_json;
        }

        $merged_sections = $existing_sections;
        $section_indexes = array();
        foreach ($merged_sections as $index => $section) {
            $section_indexes[$this->normalize_menu_item_value($section['title'] ?? '')] = $index;
        }

        foreach ($new_sections as $new_section) {
            if (!is_array($new_section)) {
                continue;
            }

            $section_key = $this->normalize_menu_item_value($new_section['title'] ?? '');
            if ($section_key !== '' && isset($section_indexes[$section_key])) {
                $existing_index = $section_indexes[$section_key];
                $existing_items = is_array($merged_sections[$existing_index]['items'] ?? null) ? $merged_sections[$existing_index]['items'] : array();
                $item_indexes = array();

                foreach ($existing_items as $item_index => $existing_item) {
                    if (!is_array($existing_item)) {
                        continue;
                    }
                    $item_key = $this->normalize_menu_item_value($existing_item['name'] ?? '');
                    if ($item_key !== '') {
                        $item_indexes[$item_key] = $item_index;
                    }
                }

                foreach (($new_section['items'] ?? array()) as $new_item) {
                    if (!is_array($new_item)) {
                        continue;
                    }

                    $item_key = $this->normalize_menu_item_value($new_item['name'] ?? '');
                    if ($item_key !== '' && isset($item_indexes[$item_key])) {
                        $existing_item_index = $item_indexes[$item_key];
                        $existing_items[$existing_item_index] = $this->merge_menu_items_for_update($existing_items[$existing_item_index], $new_item);
                    } else {
                        $existing_items[] = $new_item;
                    }
                }

                $merged_sections[$existing_index]['items'] = $existing_items;
            } else {
                $merged_sections[] = $new_section;
            }
        }

        $rebuilt_data = $this->rebuild_menu_data(
            $existing_data,
            $merged_sections,
            $this->merge_menu_content_rows(
                $this->extract_other_content_rows($existing_data),
                $this->extract_other_content_rows($new_data)
            ),
            $this->merge_menu_content_rows(
                $this->extract_non_section_content($existing_data),
                $this->extract_non_section_content($new_data)
            )
        );

        $encoded = wp_json_encode($rebuilt_data);
        return ($encoded !== false) ? $encoded : $new_raw_json;
    }
    
    /**
     * Wrap menu HTML with styling
     */
    private function wrap_menu_html($html, $style_settings = 'modern', $menu_id = 0) {
        $resolved_style = $this->resolve_menu_render_style($style_settings);
        $layout = $resolved_style['layout'];
        $instance_id = 'menu-' . ($menu_id ? intval($menu_id) : uniqid());
        $classes = array(
            'menumatic-menu',
            'style-' . esc_attr($resolved_style['preset_slug']),
            'layout-columns-' . esc_attr($layout['columns']),
            'price-' . esc_attr($layout['price_position']),
            'text-' . esc_attr($layout['text_layout']),
        );

        $style_tag = $this->render_scoped_menu_custom_css($resolved_style['custom_css'], $instance_id);

        return $style_tag . '<div class="' . implode(' ', $classes) . '" data-menu-id="' . intval($menu_id) . '" data-style-id="' . esc_attr($resolved_style['style_id']) . '" data-style-slug="' . esc_attr($resolved_style['preset_slug']) . '" data-style-instance="' . esc_attr($instance_id) . '">' . $html . '</div>';
    }

    /**
     * Render custom CSS scoped to a specific menu instance.
     */
    private function render_scoped_menu_custom_css($custom_css, $instance_id) {
        $custom_css = trim((string) $custom_css);
        if ($custom_css === '') {
            return '';
        }

        $scope_selector = '.menumatic-menu[data-style-instance="' . esc_attr($instance_id) . '"]';
        if (strpos($custom_css, '{{scope}}') !== false) {
            $scoped_css = str_replace('{{scope}}', $scope_selector, $custom_css);
        } else {
            $scoped_css = $scope_selector . " {\n" . $custom_css . "\n}";
        }

        return '<style class="menumatic-custom-style">' . wp_strip_all_tags($scoped_css, true) . '</style>';
    }
    
    /**
     * Cleanup temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/menumatic/temp';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < (time() - 86400)) { // 24 hours old
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Update an existing menu via AJAX
     */
    public function update_menu() {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'menumatic'));
        }

        $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
        if (!$menu_id) {
            wp_send_json_error(__('Invalid menu ID.', 'menumatic'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $existing_menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        if (!$existing_menu) {
            wp_send_json_error(__('Menu not found.', 'menumatic'));
        }

        // Prepare data for update
        $update_data = [];
        
        // Update title if provided
        if (isset($_POST['edit_menu_title'])) {
            $update_data['title'] = sanitize_text_field($_POST['edit_menu_title']);
        }

        // Check for and process a new image
                    if (isset($_FILES['edit_menu_image']) && $_FILES['edit_menu_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['edit_menu_image'];
                
                // Temporary upload file to a secure location
                $upload = wp_handle_upload($file, ['test_form' => false]);

                if (isset($upload['file'])) {
                    $openai = new MenuMatic_OpenAI_API();
                    // We don't need extra options for the edit re-processing
                    $result = $openai->process_menu_image($upload['file'], []); 

                    // Delete the temporary file
                    @unlink($upload['file']);

                    if (is_wp_error($result)) {
                        wp_send_json_error($result->get_error_message());
                    }

                    $merged_menu_json = $this->merge_menu_json_preserving_images($result['raw_json'], $existing_menu->menu_data ?: '');
                    $regenerated_html = $openai->build_html_from_json($merged_menu_json);
                    if (is_wp_error($regenerated_html)) {
                        wp_send_json_error($regenerated_html->get_error_message());
                    }

                    $update_data['processed_html'] = $regenerated_html;
                    $update_data['menu_data'] = $merged_menu_json;
                } else {
                    wp_send_json_error(__('Error handling uploaded file.', 'menumatic') . ' ' . ($upload['error'] ?? ''));
                }
            }

        // Only update if there's something to change
        if (empty($update_data)) {
            wp_send_json_error(__('Nothing to update. Please provide a new title or a new image.', 'menumatic'));
        }

        // Add the updated timestamp
        $update_data['updated_at'] = current_time('mysql');

        // Perform the database update
        $result = $wpdb->update($table_name, $update_data, ['id' => $menu_id]);

        if ($result === false) {
            wp_send_json_error(__('Failed to update menu in the database.', 'menumatic') . ' ' . $wpdb->last_error);
        }

        wp_send_json_success(['message' => __('Menu updated successfully.', 'menumatic')]);
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('menumatic_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'menumatic'));
        }
        
        $openai = new MenuMatic_OpenAI_API();
        $result = $openai->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $provider_label = $this->get_ai_provider_config()['provider_label'];
        wp_send_json_success(sprintf(__('%s API connection successful!', 'menumatic'), $provider_label));
    }
    
    /**
     * Frontend upload menu via AJAX
     */
    public function frontend_upload_menu() {
        // Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_frontend_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        // Check if API is configured
        $settings = $this->get_settings();

        if (!$this->is_ai_configured($settings)) {
            wp_send_json_error(__('MenuMatic is not configured properly.', 'menumatic'));
        }
        
        // Validate file upload
        if (!isset($_FILES['menu_image']) || $_FILES['menu_image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Please select a valid image file.', 'menumatic'));
        }
        
        // Get menu selection
        $menu_id = sanitize_text_field($_POST['menu_id'] ?? '');
        if (empty($menu_id)) {
            wp_send_json_error(__('Please select a menu to update.', 'menumatic'));
        }
        
        // Check if creating new menu
        $is_new_menu = ($menu_id === 'new');
        
        // Validate menu title for new menus
        if ($is_new_menu) {
            $menu_title = sanitize_text_field($_POST['menu_title'] ?? '');
            if (empty($menu_title)) {
                wp_send_json_error(__('Please provide a menu title for the new menu.', 'menumatic'));
            }
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
            
            // If updating existing menu, verify it exists
            if (!$is_new_menu) {
                $existing_menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($menu_id)));
                if (!$existing_menu) {
                    wp_send_json_error(__('Selected menu not found.', 'menumatic'));
                }
                $menu_title = $existing_menu->title;
            }
            
            // Handle file upload
            $file = $_FILES['menu_image'];
            $upload = wp_handle_upload($file, ['test_form' => false]);
            
            if (isset($upload['error'])) {
                throw new Exception($upload['error']);
            }
            
            $file_path = $upload['file'];
            
            // Process with the configured AI provider
            $openai = new MenuMatic_OpenAI_API();
            $options = [
                'detect_dietary' => true,
                'preserve_formatting' => true
            ];
            
            $result = $openai->process_menu_image($file_path, $options);
            
            if (is_wp_error($result)) {
                @unlink($file_path);
                throw new Exception($result->get_error_message());
            }

            $final_raw_json = $result['raw_json'];
            $final_html = $result['html'];
            $update_mode = sanitize_key($_POST['update_mode'] ?? 'overwrite');
            if ($update_mode !== 'add') {
                $update_mode = 'overwrite';
            }
            $requested_style_settings = $this->normalize_menu_style_settings(array(
                'style_id' => $this->normalize_menu_style_id($_POST['menu_style'] ?? ($settings['default_menu_style_id'] ?? 'preset-modern')),
                'layout' => $this->get_default_menu_layout_options(),
                'override_css' => '',
            ));
            
            if ($is_new_menu) {
                // Create new menu
                $inserted = $wpdb->insert($table_name, [
                    'title' => $menu_title,
                    'original_image_path' => $file_path,
                    'processed_html' => $final_html,
                    'menu_data' => $final_raw_json,
                    'style_settings' => $this->encode_menu_style_settings($requested_style_settings),
                    'user_id' => get_current_user_id(),
                    'status' => 'active'
                ]);
                
                if ($inserted === false) {
                    @unlink($file_path);
                    throw new Exception(__('Failed to save menu to database.', 'menumatic'));
                }
                
                $final_menu_id = $wpdb->insert_id;
                $message = __('New menu created successfully!', 'menumatic');
                
            } else {
                $final_raw_json = $this->merge_menu_json_for_upload($result['raw_json'], $existing_menu->menu_data ?: '', $update_mode);
                $merged_html = $openai->build_html_from_json($final_raw_json);
                if (is_wp_error($merged_html)) {
                    @unlink($file_path);
                    throw new Exception($merged_html->get_error_message());
                }
                $final_html = $merged_html;

                // Update existing menu
                $existing_style_settings = $this->normalize_menu_style_settings($existing_menu->style_settings ?: '{}');
                $style_settings_to_save = !empty($_POST['menu_style']) || isset($_POST['menu_custom_css'])
                    ? $requested_style_settings
                    : $existing_style_settings;
                $update_data = [
                    'original_image_path' => $file_path,
                    'processed_html' => $final_html,
                    'menu_data' => $final_raw_json,
                    'style_settings' => $this->encode_menu_style_settings($style_settings_to_save),
                    'updated_at' => current_time('mysql')
                ];
                
                // Clean up old image file if it exists
                if (!empty($existing_menu->original_image_path) && file_exists($existing_menu->original_image_path)) {
                    @unlink($existing_menu->original_image_path);
                }
                
                $updated = $wpdb->update($table_name, $update_data, ['id' => intval($menu_id)]);
                
                if ($updated === false) {
                    @unlink($file_path);
                    throw new Exception(__('Failed to update menu in database.', 'menumatic'));
                }
                
                $final_menu_id = intval($menu_id);
                $message = ($update_mode === 'add')
                    ? __('Menu items added successfully!', 'menumatic')
                    : __('Menu updated successfully!', 'menumatic');
            }
            
            wp_send_json_success([
                'menu_id' => $final_menu_id,
                'html' => $this->wrap_menu_html($final_html, $is_new_menu ? $requested_style_settings : $style_settings_to_save, $final_menu_id),
                'title' => $menu_title,
                'message' => $message,
                'is_new' => $is_new_menu
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Frontend delete menu via AJAX
     */
    public function frontend_delete_menu() {
        // Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_frontend_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id'] ?? 0);
        if (!$menu_id) {
            wp_send_json_error(__('Invalid menu ID.', 'menumatic'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        
        // Get menu to check ownership if needed
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        if (!$menu) {
            wp_send_json_error(__('Menu not found.', 'menumatic'));
        }
        
        // Delete menu
        $deleted = $wpdb->delete($table_name, ['id' => $menu_id]);
        
        if ($deleted === false) {
            wp_send_json_error(__('Failed to delete menu.', 'menumatic'));
        }
        
        // Clean up image file if it exists
        if (!empty($menu->original_image_path) && file_exists($menu->original_image_path)) {
            @unlink($menu->original_image_path);
        }
        
        wp_send_json_success(__('Menu deleted successfully.', 'menumatic'));
    }
    
    /**
     * Frontend regenerate menu via AJAX
     */
    public function frontend_regenerate_menu() {
        // Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_frontend_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id'] ?? 0);
        if (!$menu_id) {
            wp_send_json_error(__('Invalid menu ID.', 'menumatic'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        
        // Get existing menu
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        if (!$menu) {
            wp_send_json_error(__('Menu not found.', 'menumatic'));
        }
        
        // Check if original image still exists
        if (empty($menu->original_image_path) || !file_exists($menu->original_image_path)) {
            wp_send_json_error(__('Original image not found. Cannot regenerate menu.', 'menumatic'));
        }
        
        try {
            // Process with the configured AI provider again
            $openai = new MenuMatic_OpenAI_API();
            $options = json_decode($menu->menu_data ?: '{}', true) ?: [
                'detect_dietary' => true,
                'preserve_formatting' => true
            ];
            
            $result = $openai->process_menu_image($menu->original_image_path, $options);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $merged_menu_json = $this->merge_menu_json_preserving_images($result['raw_json'], $menu->menu_data ?: '');
            $merged_html = $openai->build_html_from_json($merged_menu_json);
            if (is_wp_error($merged_html)) {
                throw new Exception($merged_html->get_error_message());
            }
            
            // Update database
            $updated = $wpdb->update(
                $table_name,
                [
                    'processed_html' => $merged_html,
                    'menu_data' => $merged_menu_json,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $menu_id]
            );
            
            if ($updated === false) {
                throw new Exception(__('Failed to update menu in database.', 'menumatic'));
            }
            
            $style_settings = $this->normalize_menu_style_settings($menu->style_settings ?: '{}');
            
            wp_send_json_success([
                'html' => $this->wrap_menu_html($merged_html, $style_settings, $menu_id),
                'message' => __('Menu regenerated successfully!', 'menumatic')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Update menu JSON via AJAX
     */
    public function update_menu_json() {
        // Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id'] ?? 0);
        $json_data = $_POST['json_data'] ?? '';
        $style_settings_payload = $_POST['style_settings'] ?? '';
        
        if (!$menu_id || empty($json_data)) {
            wp_send_json_error(__('Invalid menu ID or JSON data.', 'menumatic'));
        }
        
        // Fix double-escaped JSON (common with AJAX transmission)
        if (!empty($json_data) && strpos($json_data, '\"') !== false) {
            $json_data = stripslashes($json_data);
        }
        
        // Validate JSON
        $decoded = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON format: ', 'menumatic') . json_last_error_msg());
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        if (!$menu) {
            wp_send_json_error(__('Menu not found.', 'menumatic'));
        }

        $merged_json_data = $this->merge_menu_json_preserving_images($json_data, $menu->menu_data ?: '');
        
        $update_fields = array(
            'menu_data' => $merged_json_data,
            'updated_at' => current_time('mysql')
        );

        if ($style_settings_payload !== '') {
            $decoded_style_settings = json_decode(stripslashes($style_settings_payload), true);
            if (is_array($decoded_style_settings)) {
                $update_fields['style_settings'] = $this->encode_menu_style_settings($decoded_style_settings);
            }
        }

        // Update the JSON data and optional style settings
        $updated = $wpdb->update(
            $table_name,
            $update_fields,
            ['id' => $menu_id]
        );
        
        if ($updated === false) {
            wp_send_json_error(__('Failed to update menu JSON.', 'menumatic'));
        }
        
        wp_send_json_success(__('Menu JSON updated successfully.', 'menumatic'));
    }
    
    /**
     * Regenerate HTML from JSON via AJAX
     */
    public function regenerate_from_json() {
        // Security check for both admin and frontend
        $nonce_field = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $is_valid_admin = wp_verify_nonce($nonce_field, 'menumatic_nonce');
        $is_valid_frontend = wp_verify_nonce($nonce_field, 'menumatic_frontend_nonce');
        
        if (!$is_valid_admin && !$is_valid_frontend) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        // Only require manage_options for admin users
        if ($is_valid_admin && !current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id'] ?? 0);
        $json_data = $_POST['json_data'] ?? '';
        $style_settings_payload = $_POST['style_settings'] ?? '';
        
            if (!$menu_id || empty($json_data)) {
                wp_send_json_error(__('Invalid menu ID or JSON data.', 'menumatic'));
            }
        
        // Fix double-escaped JSON (common with AJAX transmission)
        if (!empty($json_data) && strpos($json_data, '\"') !== false) {
            $json_data = stripslashes($json_data);
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
            
            // Get current menu for style
            $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
            if (!$menu) {
                throw new Exception(__('Menu not found.', 'menumatic'));
            }

            $merged_json_data = $this->merge_menu_json_preserving_images($json_data, $menu->menu_data ?: '');
            $style_settings = $this->normalize_menu_style_settings($menu->style_settings ?: '{}');
            if ($style_settings_payload !== '') {
                $decoded_style_settings = json_decode(stripslashes($style_settings_payload), true);
                if (is_array($decoded_style_settings)) {
                    $style_settings = $this->normalize_menu_style_settings($decoded_style_settings);
                }
            }

            // Build HTML from JSON
            $openai = new MenuMatic_OpenAI_API();
            $new_html = $openai->build_html_from_json($merged_json_data);
            
            if (is_wp_error($new_html)) {
                throw new Exception($new_html->get_error_message());
            }
            
            // Update both HTML and JSON
            $updated = $wpdb->update(
                $table_name,
                [
                    'processed_html' => $new_html,
                    'menu_data' => $merged_json_data,
                    'style_settings' => $this->encode_menu_style_settings($style_settings),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $menu_id]
            );
            
            if ($updated === false) {
                throw new Exception(__('Failed to update menu in database.', 'menumatic'));
            }
            
            wp_send_json_success([
                'html' => $this->wrap_menu_html($new_html, $style_settings, $menu_id),
                'message' => __('Menu regenerated from JSON successfully!', 'menumatic')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get menu data for visual editor via AJAX
     */
    public function get_menu_data() {
        // Security check for both admin and frontend
        $nonce_field = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $is_valid_admin = wp_verify_nonce($nonce_field, 'menumatic_nonce');
        $is_valid_frontend = wp_verify_nonce($nonce_field, 'menumatic_frontend_nonce');
        
        if (!$is_valid_admin && !$is_valid_frontend) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        $menu_id = intval($_POST['menu_id'] ?? 0);
        if (!$menu_id) {
            wp_send_json_error(__('Invalid menu ID.', 'menumatic'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . MENUMATIC_TABLE_NAME;
        
        // Get menu data
        $menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
        if (!$menu) {
            wp_send_json_error(__('Menu not found.', 'menumatic'));
        }
        
        // Parse JSON data
        $menu_data = json_decode($menu->menu_data ?: '{}', true);
        if (!$menu_data) {
            wp_send_json_error(__('Invalid menu data format.', 'menumatic'));
        }

        wp_send_json_success(array(
            'title' => $menu->title,
            'menu_data' => $menu_data,
            'style_settings' => $this->normalize_menu_style_settings($menu->style_settings ?: '{}'),
            'rendered_html' => $this->wrap_menu_html($menu->processed_html ?: '', $menu->style_settings ?: '{}', $menu_id),
        ));
    }
    
    /**
     * Upload item image via AJAX
     */
    public function upload_item_image() {
        // Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'menumatic_item_image_nonce')) {
            wp_send_json_error(__('Security check failed.', 'menumatic'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'menumatic'));
        }
        
        if (!isset($_FILES['item_image'])) {
            wp_send_json_error(__('No image file uploaded.', 'menumatic'));
        }
        
        $file = $_FILES['item_image'];
        $menu_id = intval($_POST['menu_id'] ?? 0);
        
        if (!$menu_id) {
            wp_send_json_error(__('Invalid menu ID.', 'menumatic'));
        }
        
        // Validate file
        $settings = $this->get_settings();
        $allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : array('jpg', 'jpeg', 'png', 'webp');
        $max_size = isset($settings['max_file_size']) ? $settings['max_file_size'] * 1024 * 1024 : 5 * 1024 * 1024;
        
        if (empty($file['name'])) {
            wp_send_json_error(__('Invalid file name.', 'menumatic'));
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error(__('Invalid file type. Allowed types: ', 'menumatic') . implode(', ', $allowed_types));
        }
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('File size too large. Maximum size: ', 'menumatic') . ($max_size / 1024 / 1024) . 'MB');
        }
        
        // Create upload directory for menu item images
        $upload_dir = wp_upload_dir();
        $menumatic_dir = $upload_dir['basedir'] . '/menumatic/items';
        
        if (!file_exists($menumatic_dir)) {
            wp_mkdir_p($menumatic_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($menumatic_dir . '/.htaccess', $htaccess_content);
        }
        
        // Generate unique filename
        $filename = 'item_' . $menu_id . '_' . uniqid() . '.' . $file_extension;
        $file_path = $menumatic_dir . '/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/menumatic/items/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_success(array(
                'url' => $file_url,
                'path' => $file_path,
                'filename' => $filename
            ));
        } else {
            wp_send_json_error(__('Failed to save uploaded file.', 'menumatic'));
        }
    }
}

// Initialize the plugin
MenuMatic::get_instance(); 
