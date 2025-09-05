<?php

namespace WhatJobsFeeder;

/**
 * WhatJobs API integration
 */
class API {
    
    private const BASE_URL = 'https://api.whatjobs.com/api/v1/jobs.xml';
    private const TIMEOUT = 30;
    
    private static ?API $instance = null;
    
    public static function instance(): API {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Build API URL with parameters
     */
    public function build_url(array $params = []): string {
        $publisher_id = Core::get_option('publisher_id');
        
        if (empty($publisher_id)) {
            return '';
        }
        
        $default_params = [
            'publisher' => $publisher_id,
            'user_ip' => Core::get_user_ip(),
            'user_agent' => Core::get_user_agent(),
            'limit' => 10,
            'page' => 1
        ];
        
        $params = wp_parse_args($params, $default_params);
        
        return add_query_arg($params, self::BASE_URL);
    }
    
    /**
     * Fetch jobs from WhatJobs API
     */
    public function fetch_jobs(array $params = []): array|\WP_Error {
        $url = $this->build_url($params);
        
        if (empty($url)) {
            return new \WP_Error(
                'missing_publisher_id',
                __('Publisher ID não configurado.', 'whatjobs-feeder')
            );
        }
        
        Core::log('Fetching jobs from API', ['url' => $url]);
        
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'User-Agent' => Core::get_user_agent()
            ]
        ]);
        
        if (is_wp_error($response)) {
            Core::log('API request failed', ['error' => $response->get_error_message()]);
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if (200 !== $code) {
            return new \WP_Error(
                'api_error',
                sprintf(__('Erro na API: %d', 'whatjobs-feeder'), $code)
            );
        }
        
        return $this->parse_xml_response($body);
    }
    
    /**
     * Parse XML response from API
     */
    private function parse_xml_response(string $xml_string): array|\WP_Error {
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if (false === $xml) {
            $errors = libxml_get_errors();
            $error_message = !empty($errors) ? $errors[0]->message : 'Invalid XML';
            
            return new \WP_Error(
                'xml_parse_error',
                sprintf(__('Erro ao processar XML: %s', 'whatjobs-feeder'), $error_message)
            );
        }
        
        $jobs = [];
        
        if (isset($xml->job)) {
            foreach ($xml->job as $job) {
                $jobs[] = $this->parse_job_data($job);
            }
        }
        
        Core::log('Jobs parsed from XML', ['count' => count($jobs)]);
        
        return $jobs;
    }
    
    /**
     * Parse individual job data
     */
    private function parse_job_data(\SimpleXMLElement $job): array {
        return [
            'title' => (string) $job->title,
            'description' => (string) $job->snippet,
            'company' => (string) $job->company,
            'location' => (string) $job->location,
            'url' => (string) $job->url,
            'salary' => (string) $job->salary,
            'job_type' => (string) $job->job_type,
            'site' => (string) $job->site,
            'logo' => (string) $job->logo,
            'age' => (string) $job->age,
            'postcode' => (string) $job->postcode,
            'date_posted' => $this->parse_job_date((string) $job->age)
        ];
    }
    
    /**
     * Parse job date from age string
     */
    private function parse_job_date(string $age): string {
        if (empty($age)) {
            return current_time('Y-m-d H:i:s');
        }
        
        // Try to extract days from age string
        if (preg_match('/(\d+)\s*day/i', $age, $matches)) {
            $days_ago = intval($matches[1]);
            return date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
        }
        
        // Try to extract hours from age string
        if (preg_match('/(\d+)\s*hour/i', $age, $matches)) {
            $hours_ago = intval($matches[1]);
            return date('Y-m-d H:i:s', strtotime("-{$hours_ago} hours"));
        }
        
        return current_time('Y-m-d H:i:s');
    }
    
    /**
     * Test API connection
     */
    public function test_connection(): bool|\WP_Error {
        $test_params = [
            'keyword' => 'test',
            'limit' => 1
        ];
        
        $result = $this->fetch_jobs($test_params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
}