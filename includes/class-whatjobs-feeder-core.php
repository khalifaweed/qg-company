<?php

namespace WhatJobsFeeder;

/**
 * Core plugin functionality
 */
class Core {
    
    private static ?Core $instance = null;
    
    public static function instance(): Core {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        add_action('init', [$this, 'init']);
    }
    
    public function init(): void {
        // Initialize core functionality
    }
    
    /**
     * Get plugin option with default fallback
     */
    public static function get_option(string $key, $default = ''): mixed {
        return get_option("whatjobs_feeder_{$key}", $default);
    }
    
    /**
     * Update plugin option
     */
    public static function update_option(string $key, $value): bool {
        return update_option("whatjobs_feeder_{$key}", $value);
    }
    
    /**
     * Get user IP address
     */
    public static function get_user_ip(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Get user agent
     */
    public static function get_user_agent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'WhatJobsFeeder/1.0';
    }
    
    /**
     * Sanitize and validate feed data
     */
    public static function sanitize_feed_data(array $data): array {
        return [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'source' => sanitize_text_field($data['source'] ?? 'whatjobs'),
            'keyword' => sanitize_text_field($data['keyword'] ?? ''),
            'location' => sanitize_text_field($data['location'] ?? ''),
            'limit_jobs' => absint($data['limit_jobs'] ?? 10),
            'page_number' => absint($data['page_number'] ?? 1),
            'author_id' => absint($data['author_id'] ?? 1),
            'job_category' => sanitize_text_field($data['job_category'] ?? ''),
            'job_type' => sanitize_text_field($data['job_type'] ?? ''),
            'frequency' => sanitize_text_field($data['frequency'] ?? '24h'),
            'status' => sanitize_text_field($data['status'] ?? 'active')
        ];
    }
    
    /**
     * Log debug information
     */
    public static function log(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WhatJobs Feeder] %s %s',
                $message,
                !empty($context) ? json_encode($context) : ''
            ));
        }
    }
}