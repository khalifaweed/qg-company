<?php

namespace WhatJobsFeeder;

/**
 * Job importer functionality
 */
class Importer {
    
    private static ?Importer $instance = null;
    
    public static function instance(): Importer {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Import jobs from feed
     */
    public function import_from_feed(int $feed_id): array {
        global $wpdb;
        
        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}whatjobs_feeds WHERE id = %d",
            $feed_id
        ), ARRAY_A);
        
        if (!$feed) {
            return [
                'success' => false,
                'message' => __('Feed não encontrado.', 'whatjobs-feeder')
            ];
        }
        
        $api_params = [
            'keyword' => $feed['keyword'],
            'location' => $feed['location'],
            'limit' => $feed['limit_jobs'],
            'page' => $feed['page_number']
        ];
        
        $api = API::instance();
        $jobs = $api->fetch_jobs($api_params);
        
        if (is_wp_error($jobs)) {
            return [
                'success' => false,
                'message' => $jobs->get_error_message()
            ];
        }
        
        $imported = 0;
        $duplicates = 0;
        $errors = 0;
        
        foreach ($jobs as $job_data) {
            $result = $this->import_single_job($job_data, $feed);
            
            switch ($result['status']) {
                case 'imported':
                    $imported++;
                    break;
                case 'duplicate':
                    $duplicates++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }
        
        // Update feed last run
        $wpdb->update(
            $wpdb->prefix . 'whatjobs_feeds',
            [
                'last_run' => current_time('mysql'),
                'next_run' => $this->calculate_next_run($feed['frequency'])
            ],
            ['id' => $feed_id],
            ['%s', '%s'],
            ['%d']
        );
        
        return [
            'success' => true,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'total' => count($jobs)
        ];
    }
    
    /**
     * Import single job
     */
    private function import_single_job(array $job_data, array $feed): array {
        // Check for duplicates
        if ($this->job_exists($job_data['url'])) {
            return ['status' => 'duplicate'];
        }
        
        // Validate required fields
        if (empty($job_data['title']) || empty($job_data['description'])) {
            return ['status' => 'error', 'message' => 'Missing required fields'];
        }
        
        $post_data = [
            'post_title' => sanitize_text_field($job_data['title']),
            'post_content' => wp_kses_post($job_data['description']),
            'post_status' => Core::get_option('default_status', 'publish'),
            'post_type' => 'job_listing',
            'post_author' => $feed['author_id'],
            'post_date' => $job_data['date_posted']
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return ['status' => 'error', 'message' => $post_id->get_error_message()];
        }
        
        // Add job meta
        $this->add_job_meta($post_id, $job_data, $feed);
        
        // Add taxonomies
        $this->add_job_taxonomies($post_id, $feed);
        
        Core::log('Job imported', ['post_id' => $post_id, 'title' => $job_data['title']]);
        
        return ['status' => 'imported', 'post_id' => $post_id];
    }
    
    /**
     * Check if job already exists
     */
    private function job_exists(string $url): bool {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_application' AND meta_value = %s",
            $url
        ));
        
        return !empty($existing);
    }
    
    /**
     * Add job meta data
     */
    private function add_job_meta(int $post_id, array $job_data, array $feed): void {
        $meta_fields = [
            '_job_location' => $job_data['location'],
            '_company_name' => $job_data['company'],
            '_application' => $job_data['url'],
            '_job_salary' => $job_data['salary'],
            '_company_website' => $job_data['site'],
            '_whatjobs_feeder_imported' => 1,
            '_whatjobs_feeder_feed_id' => $feed['id'],
            '_whatjobs_feeder_source_url' => $job_data['url'],
            '_whatjobs_feeder_import_date' => current_time('mysql')
        ];
        
        // Add job expiry if available
        if (!empty($job_data['expires'])) {
            $meta_fields['_job_expires'] = $job_data['expires'];
        }
        
        foreach ($meta_fields as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }
    }
    
    /**
     * Add job taxonomies
     */
    private function add_job_taxonomies(int $post_id, array $feed): void {
        // Add job category
        if (!empty($feed['job_category'])) {
            wp_set_object_terms($post_id, $feed['job_category'], 'job_listing_category');
        }
        
        // Add job type
        if (!empty($feed['job_type'])) {
            wp_set_object_terms($post_id, $feed['job_type'], 'job_listing_type');
        }
    }
    
    /**
     * Calculate next run time based on frequency
     */
    private function calculate_next_run(string $frequency): string {
        $intervals = [
            '1h' => '+1 hour',
            '6h' => '+6 hours',
            '12h' => '+12 hours',
            '24h' => '+24 hours'
        ];
        
        $interval = $intervals[$frequency] ?? '+24 hours';
        
        return date('Y-m-d H:i:s', strtotime($interval));
    }
    
    /**
     * Clean up old imported jobs
     */
    public function cleanup_old_jobs(int $days = 30): int {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $old_jobs = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'job_listing'
             AND pm.meta_key = '_whatjobs_feeder_imported'
             AND pm.meta_value = '1'
             AND p.post_date < %s",
            $cutoff_date
        ));
        
        $deleted = 0;
        foreach ($old_jobs as $job_id) {
            if (wp_delete_post($job_id, true)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
}