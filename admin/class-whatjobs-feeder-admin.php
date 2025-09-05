<?php

namespace WhatJobsFeeder;

/**
 * Admin interface
 */
class Admin {
    
    private static ?Admin $instance = null;
    
    public static function instance(): Admin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_whatjobs_feeder_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_whatjobs_feeder_run_feed', [$this, 'ajax_run_feed']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('WhatJobs Feeder', 'whatjobs-feeder'),
            __('WhatJobs Feeder', 'whatjobs-feeder'),
            'manage_options',
            'whatjobs-feeder',
            [$this, 'feeds_page'],
            'dashicons-rss',
            30
        );
        
        add_submenu_page(
            'whatjobs-feeder',
            __('Feeds Automáticos', 'whatjobs-feeder'),
            __('Feeds Automáticos', 'whatjobs-feeder'),
            'manage_options',
            'whatjobs-feeder',
            [$this, 'feeds_page']
        );
        
        add_submenu_page(
            'whatjobs-feeder',
            __('Configurações', 'whatjobs-feeder'),
            __('Configurações', 'whatjobs-feeder'),
            'manage_options',
            'whatjobs-feeder-settings',
            [$this, 'settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('whatjobs_feeder_settings', 'whatjobs_feeder_publisher_id');
        register_setting('whatjobs_feeder_settings', 'whatjobs_feeder_default_status');
        
        add_settings_section(
            'whatjobs_feeder_api',
            __('Configurações da API', 'whatjobs-feeder'),
            null,
            'whatjobs_feeder_settings'
        );
        
        add_settings_field(
            'publisher_id',
            __('Publisher ID', 'whatjobs-feeder'),
            [$this, 'publisher_id_field'],
            'whatjobs_feeder_settings',
            'whatjobs_feeder_api'
        );
        
        add_settings_field(
            'default_status',
            __('Status padrão das vagas', 'whatjobs-feeder'),
            [$this, 'default_status_field'],
            'whatjobs_feeder_settings',
            'whatjobs_feeder_api'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts(string $hook): void {
        if (strpos($hook, 'whatjobs-feeder') === false) {
            return;
        }
        
        wp_enqueue_script(
            'whatjobs-feeder-admin',
            WHATJOBS_FEEDER_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            WHATJOBS_FEEDER_VERSION,
            true
        );
        
        wp_localize_script('whatjobs-feeder-admin', 'whatjobsFeeder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('whatjobs_feeder_nonce'),
            'strings' => [
                'testingConnection' => __('Testando conexão...', 'whatjobs-feeder'),
                'connectionSuccess' => __('Conexão bem-sucedida!', 'whatjobs-feeder'),
                'connectionFailed' => __('Falha na conexão:', 'whatjobs-feeder'),
                'runningFeed' => __('Executando feed...', 'whatjobs-feeder'),
                'feedCompleted' => __('Feed executado com sucesso!', 'whatjobs-feeder')
            ]
        ]);
        
        wp_enqueue_style(
            'whatjobs-feeder-admin',
            WHATJOBS_FEEDER_PLUGIN_URL . 'admin/css/admin.css',
            [],
            WHATJOBS_FEEDER_VERSION
        );
    }
    
    /**
     * Feeds page
     */
    public function feeds_page(): void {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
                $this->new_feed_form();
                break;
            case 'edit':
                $this->edit_feed_form();
                break;
            default:
                $this->feeds_list();
                break;
        }
    }
    
    /**
     * Settings page
     */
    public function settings_page(): void {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        include WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Feeds list
     */
    private function feeds_list(): void {
        if (isset($_POST['action']) && $_POST['action'] === 'create_feed') {
            $this->save_feed();
        }
        
        $feeds_table = new FeedsTable();
        $feeds_table->prepare_items();
        
        include WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/views/feeds-list.php';
    }
    
    /**
     * New feed form
     */
    private function new_feed_form(): void {
        include WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/views/feed-form.php';
    }
    
    /**
     * Edit feed form
     */
    private function edit_feed_form(): void {
        global $wpdb;
        
        $feed_id = absint($_GET['feed_id'] ?? 0);
        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}whatjobs_feeds WHERE id = %d",
            $feed_id
        ), ARRAY_A);
        
        if (!$feed) {
            wp_die(__('Feed não encontrado.', 'whatjobs-feeder'));
        }
        
        include WHATJOBS_FEEDER_PLUGIN_DIR . 'admin/views/feed-form.php';
    }
    
    /**
     * Save feed
     */
    private function save_feed(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'whatjobs_feeder_save_feed')) {
            wp_die(__('Nonce inválido.', 'whatjobs-feeder'));
        }
        
        global $wpdb;
        
        $data = Core::sanitize_feed_data($_POST);
        $data['name'] = $data['keyword'] . ' - ' . $data['location'];
        
        if (empty($data['keyword']) && empty($data['location'])) {
            add_settings_error(
                'whatjobs_feeder',
                'missing_params',
                __('Pelo menos um parâmetro (keyword ou location) é obrigatório.', 'whatjobs-feeder')
            );
            return;
        }
        
        $feed_id = absint($_POST['feed_id'] ?? 0);
        
        if ($feed_id) {
            // Update existing feed
            $result = $wpdb->update(
                $wpdb->prefix . 'whatjobs_feeds',
                $data,
                ['id' => $feed_id],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Create new feed
            $data['next_run'] = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'whatjobs_feeds',
                $data,
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );
            
            $feed_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            add_settings_error(
                'whatjobs_feeder',
                'feed_saved',
                __('Feed salvo com sucesso!', 'whatjobs-feeder'),
                'success'
            );
        } else {
            add_settings_error(
                'whatjobs_feeder',
                'save_error',
                __('Erro ao salvar feed.', 'whatjobs-feeder')
            );
        }
    }
    
    /**
     * Save settings
     */
    private function save_settings(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'whatjobs_feeder_settings')) {
            wp_die(__('Nonce inválido.', 'whatjobs-feeder'));
        }
        
        $publisher_id = sanitize_text_field($_POST['whatjobs_feeder_publisher_id'] ?? '');
        $default_status = sanitize_text_field($_POST['whatjobs_feeder_default_status'] ?? 'publish');
        
        update_option('whatjobs_feeder_publisher_id', $publisher_id);
        update_option('whatjobs_feeder_default_status', $default_status);
        
        add_settings_error(
            'whatjobs_feeder_settings',
            'settings_saved',
            __('Configurações salvas!', 'whatjobs-feeder'),
            'success'
        );
    }
    
    /**
     * Publisher ID field
     */
    public function publisher_id_field(): void {
        $value = Core::get_option('publisher_id');
        echo '<input type="text" name="whatjobs_feeder_publisher_id" value="' . esc_attr($value) . '" class="regular-text" required />';
        echo '<p class="description">' . __('Seu Publisher ID do WhatJobs (obrigatório).', 'whatjobs-feeder') . '</p>';
    }
    
    /**
     * Default status field
     */
    public function default_status_field(): void {
        $value = Core::get_option('default_status', 'publish');
        $options = [
            'publish' => __('Publicado', 'whatjobs-feeder'),
            'draft' => __('Rascunho', 'whatjobs-feeder'),
            'pending' => __('Pendente', 'whatjobs-feeder')
        ];
        
        echo '<select name="whatjobs_feeder_default_status">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection(): void {
        check_ajax_referer('whatjobs_feeder_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissão negada.', 'whatjobs-feeder'));
        }
        
        $api = API::instance();
        $result = $api->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Conexão bem-sucedida!', 'whatjobs-feeder'));
    }
    
    /**
     * AJAX run feed
     */
    public function ajax_run_feed(): void {
        check_ajax_referer('whatjobs_feeder_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissão negada.', 'whatjobs-feeder'));
        }
        
        $feed_id = absint($_POST['feed_id'] ?? 0);
        
        if (!$feed_id) {
            wp_send_json_error(__('ID do feed inválido.', 'whatjobs-feeder'));
        }
        
        $importer = Importer::instance();
        $result = $importer->import_from_feed($feed_id);
        
        if (!$result['success']) {
            wp_send_json_error($result['message']);
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('Importados: %d, Duplicados: %d, Erros: %d', 'whatjobs-feeder'),
                $result['imported'],
                $result['duplicates'],
                $result['errors']
            )
        ]);
    }
}