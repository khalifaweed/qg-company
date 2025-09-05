<?php

namespace WhatJobsFeeder;

/**
 * Cron job management
 */
class Cron {
    
    private static ?Cron $instance = null;
    
    public static function instance(): Cron {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        add_action('whatjobs_feeder_cron_hook', [$this, 'run_scheduled_imports']);
        add_filter('cron_schedules', [$this, 'add_custom_schedules']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_schedules(array $schedules): array {
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('A cada 6 horas', 'whatjobs-feeder')
        ];
        
        $schedules['every_12_hours'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('A cada 12 horas', 'whatjobs-feeder')
        ];
        
        return $schedules;
    }
    
    /**
     * Run scheduled imports
     */
    public function run_scheduled_imports(): void {
        global $wpdb;
        
        Core::log('Running scheduled imports');
        
        // Get feeds that need to run
        $feeds = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}whatjobs_feeds 
                 WHERE status = 'active' 
                 AND (next_run IS NULL OR next_run <= %s)",
                current_time('mysql')
            ),
            ARRAY_A
        );
        
        if (empty($feeds)) {
            Core::log('No feeds to process');
            return;
        }
        
        $importer = Importer::instance();
        
        foreach ($feeds as $feed) {
            Core::log('Processing feed', ['id' => $feed['id'], 'name' => $feed['name']]);
            
            $result = $importer->import_from_feed($feed['id']);
            
            if ($result['success']) {
                Core::log('Feed processed successfully', [
                    'feed_id' => $feed['id'],
                    'imported' => $result['imported'],
                    'duplicates' => $result['duplicates']
                ]);
            } else {
                Core::log('Feed processing failed', [
                    'feed_id' => $feed['id'],
                    'error' => $result['message']
                ]);
            }
        }
        
        Core::log('Scheduled imports completed');
    }
    
    /**
     * Schedule feed import
     */
    public function schedule_feed(int $feed_id, string $frequency): bool {
        $schedules = [
            '1h' => 'hourly',
            '6h' => 'every_6_hours',
            '12h' => 'every_12_hours',
            '24h' => 'daily'
        ];
        
        $schedule = $schedules[$frequency] ?? 'daily';
        
        $hook = "whatjobs_feeder_feed_{$feed_id}";
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook);
        
        // Schedule new event
        return wp_schedule_event(time(), $schedule, $hook);
    }
    
    /**
     * Unschedule feed import
     */
    public function unschedule_feed(int $feed_id): bool {
        $hook = "whatjobs_feeder_feed_{$feed_id}";
        return wp_clear_scheduled_hook($hook);
    }
    
    /**
     * Get next scheduled run for feed
     */
    public function get_next_run(int $feed_id): ?int {
        $hook = "whatjobs_feeder_feed_{$feed_id}";
        return wp_next_scheduled($hook) ?: null;
    }
}