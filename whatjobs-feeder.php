<?php
<?php
/**
 * Plugin Name: WhatJobs Feeder
 * Plugin URI: https://github.com/yourname/whatjobs-feeder
 * Description: Plugin para importar vagas automaticamente do WhatJobs API para o WP Job Manager
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com
 * Text Domain: whatjobs-feeder
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WHATJOBS_FEEDER_VERSION', '1.0.0');
define('WHATJOBS_FEEDER_PLUGIN_FILE', __FILE__);
define('WHATJOBS_FEEDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHATJOBS_FEEDER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WHATJOBS_FEEDER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class WhatJobsFeeder {
    
    /**
     * Plugin instance
     */
    private static ?WhatJobsFeeder $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance(): WhatJobsFeeder {
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
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies(): void {
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'includes/class-whatjobs-feeder-core.php';
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'includes/class-whatjobs-feeder-api.php';
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'includes/class-whatjobs-feeder-importer.php';
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'includes/class-whatjobs-feeder-cron.php';
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/class-whatjobs-feeder-admin.php';
        require_once WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/class-whatjobs-feeder-feeds-table.php';
    }
    
    /**
     * Initialize plugin
     */
    public function init(): void {
        // Check if WP Job Manager is active
        if (!class_exists('WP_Job_Manager')) {
            add_action('admin_notices', [$this, 'wp_job_manager_missing_notice']);
            return;
        }
        
        // Initialize core components
        WhatJobsFeeder\Core::instance();
        WhatJobsFeeder\Admin::instance();
        WhatJobsFeeder\Cron::instance();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'whatjobs-feeder',
            false,
            dirname(WHATJOBS_FEEDER_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate(): void {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        if (!wp_next_scheduled('whatjobs_feeder_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'whatjobs_feeder_cron_hook');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('whatjobs_feeder_cron_hook');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'whatjobs_feeds';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'whatjobs',
            keyword varchar(255) DEFAULT '',
            location varchar(255) DEFAULT '',
            limit_jobs int(11) DEFAULT 10,
            page_number int(11) DEFAULT 1,
            author_id bigint(20) NOT NULL,
            job_category varchar(255) DEFAULT '',
            job_type varchar(255) DEFAULT '',
            frequency varchar(20) NOT NULL DEFAULT '24h',
            status varchar(20) NOT NULL DEFAULT 'active',
            last_run datetime DEFAULT NULL,
            next_run datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options(): void {
        $defaults = [
            'whatjobs_feeder_publisher_id' => '',
            'whatjobs_feeder_default_status' => 'publish',
            'whatjobs_feeder_version' => WHATJOBS_FEEDER_VERSION
        ];
        
        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Show notice if WP Job Manager is not active
     */
    public function wp_job_manager_missing_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <?php 
                printf(
                    __('WhatJobs Feeder requer o plugin %s para funcionar.', 'whatjobs-feeder'),
                    '<strong>WP Job Manager</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function whatjobs_feeder(): WhatJobsFeeder {
    return WhatJobsFeeder::instance();
}

// Start the plugin
whatjobs_feeder();