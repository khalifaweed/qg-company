<?php

namespace WhatJobsFeeder;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Feeds list table
 */
class FeedsTable extends \WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'feed',
            'plural' => 'feeds',
            'ajax' => false
        ]);
    }
    
    /**
     * Get columns
     */
    public function get_columns(): array {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'whatjobs-feeder'),
            'name' => __('Nome', 'whatjobs-feeder'),
            'keyword' => __('Palavra-chave', 'whatjobs-feeder'),
            'location' => __('Localização', 'whatjobs-feeder'),
            'frequency' => __('Frequência', 'whatjobs-feeder'),
            'status' => __('Status', 'whatjobs-feeder'),
            'last_run' => __('Última execução', 'whatjobs-feeder'),
            'next_run' => __('Próxima execução', 'whatjobs-feeder')
        ];
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array {
        return [
            'id' => ['id', false],
            'name' => ['name', false],
            'frequency' => ['frequency', false],
            'status' => ['status', false],
            'last_run' => ['last_run', false],
            'next_run' => ['next_run', false]
        ];
    }
    
    /**
     * Prepare items
     */
    public function prepare_items(): void {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $orderby = $_GET['orderby'] ?? 'id';
        $order = $_GET['order'] ?? 'DESC';
        
        $allowed_orderby = ['id', 'name', 'frequency', 'status', 'last_run', 'next_run'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}whatjobs_feeds"
        );
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}whatjobs_feeds 
             ORDER BY {$orderby} {$order} 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);
        
        $this->items = $items;
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
    }
    
    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'name':
            case 'keyword':
            case 'location':
                return esc_html($item[$column_name]);
            
            case 'frequency':
                $frequencies = [
                    '1h' => __('1 hora', 'whatjobs-feeder'),
                    '6h' => __('6 horas', 'whatjobs-feeder'),
                    '12h' => __('12 horas', 'whatjobs-feeder'),
                    '24h' => __('24 horas', 'whatjobs-feeder')
                ];
                return $frequencies[$item[$column_name]] ?? $item[$column_name];
            
            case 'status':
                $status_class = $item[$column_name] === 'active' ? 'status-active' : 'status-inactive';
                $status_text = $item[$column_name] === 'active' ? __('Ativo', 'whatjobs-feeder') : __('Inativo', 'whatjobs-feeder');
                return '<span class="' . $status_class . '">' . $status_text . '</span>';
            
            case 'last_run':
            case 'next_run':
                if (empty($item[$column_name])) {
                    return '—';
                }
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item[$column_name]));
            
            default:
                return $item[$column_name] ?? '';
        }
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item): string {
        return sprintf('<input type="checkbox" name="feed_ids[]" value="%s" />', $item['id']);
    }
    
    /**
     * Column name with row actions
     */
    public function column_name($item): string {
        $edit_url = add_query_arg([
            'page' => 'whatjobs-feeder',
            'action' => 'edit',
            'feed_id' => $item['id']
        ], admin_url('admin.php'));
        
        $delete_url = add_query_arg([
            'page' => 'whatjobs-feeder',
            'action' => 'delete',
            'feed_id' => $item['id'],
            '_wpnonce' => wp_create_nonce('delete_feed_' . $item['id'])
        ], admin_url('admin.php'));
        
        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'whatjobs-feeder')),
            'run' => sprintf(
                '<a href="#" class="run-feed" data-feed-id="%d">%s</a>',
                $item['id'],
                __('Executar agora', 'whatjobs-feeder')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                $delete_url,
                __('Tem certeza que deseja excluir este feed?', 'whatjobs-feeder'),
                __('Excluir', 'whatjobs-feeder')
            )
        ];
        
        if ($item['status'] === 'active') {
            $actions['pause'] = sprintf(
                '<a href="#" class="pause-feed" data-feed-id="%d">%s</a>',
                $item['id'],
                __('Pausar', 'whatjobs-feeder')
            );
        } else {
            $actions['activate'] = sprintf(
                '<a href="#" class="activate-feed" data-feed-id="%d">%s</a>',
                $item['id'],
                __('Ativar', 'whatjobs-feeder')
            );
        }
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            $edit_url,
            esc_html($item['name']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions(): array {
        return [
            'delete' => __('Excluir', 'whatjobs-feeder'),
            'activate' => __('Ativar', 'whatjobs-feeder'),
            'deactivate' => __('Desativar', 'whatjobs-feeder')
        ];
    }
}