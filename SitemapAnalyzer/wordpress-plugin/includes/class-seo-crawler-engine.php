<?php
/**
 * SEO Crawler Engine Class
 * Handles Python script execution and crawling process management
 */

class SEO_Crawler_Engine {
    
    private $python_path;
    private $script_path;
    
    public function __construct() {
        $this->python_path = $this->get_python_path();
        $this->script_path = SEO_CRAWLER_PLUGIN_PATH . 'python/wp_crawler.py';
    }
    
    private function get_python_path() {
        // Try common Python paths
        $possible_paths = array('python3', 'python', '/usr/bin/python3', '/usr/bin/python');
        
        foreach ($possible_paths as $path) {
            $output = shell_exec("which $path 2>/dev/null");
            if (!empty($output)) {
                return trim($output);
            }
        }
        
        return 'python3'; // Fallback
    }
    
    public function start_crawl($sitemap_url, $delay = 1.0, $max_pages = 0, $user_id = '') {
        global $wpdb;
        
        // Validate inputs
        if (empty($sitemap_url) || !filter_var($sitemap_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Generate unique session ID
        $session_id = $this->create_session($sitemap_url, $delay, $max_pages, $user_id);
        
        if (!$session_id) {
            return false;
        }
        
        // Start background crawl process
        $this->start_background_crawl($session_id, $sitemap_url, $delay, $max_pages);
        
        return $session_id;
    }
    
    private function create_session($sitemap_url, $delay, $max_pages, $user_id) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $settings = json_encode(array(
            'delay' => $delay,
            'max_pages' => $max_pages
        ));
        
        $result = $wpdb->insert(
            $table_sessions,
            array(
                'user_id' => $user_id,
                'sitemap_url' => $sitemap_url,
                'settings' => $settings,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    private function start_background_crawl($session_id, $sitemap_url, $delay, $max_pages) {
        // Update session status to running
        $this->update_session_status($session_id, 'running');
        
        // Build Python command
        $command = sprintf(
            '%s %s --sitemap-url %s --delay %f --session-id %d',
            escapeshellarg($this->python_path),
            escapeshellarg($this->script_path),
            escapeshellarg($sitemap_url),
            $delay,
            $session_id
        );
        
        if ($max_pages > 0) {
            $command .= " --max-pages " . intval($max_pages);
        }
        
        // Execute in background
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // In debug mode, run synchronously for easier debugging
            $this->execute_crawl_sync($command, $session_id);
        } else {
            // Run asynchronously in production
            $this->execute_crawl_async($command, $session_id);
        }
    }
    
    private function execute_crawl_sync($command, $session_id) {
        $output = shell_exec($command . ' 2>&1');
        $this->process_crawl_output($output, $session_id);
    }
    
    private function execute_crawl_async($command, $session_id) {
        // Use WordPress cron or exec for background processing
        if (function_exists('exec')) {
            exec($command . ' > /dev/null 2>&1 &');
        } else {
            // Fallback to WordPress cron
            wp_schedule_single_event(time(), 'seo_crawler_process', array($session_id, $command));
        }
    }
    
    private function process_crawl_output($output, $session_id) {
        try {
            $result = json_decode($output, true);
            
            if ($result && $result['success']) {
                $this->save_crawl_results($session_id, $result['data']);
                $this->update_session_status($session_id, 'completed');
            } else {
                $error_message = isset($result['error']) ? $result['error'] : 'Unknown error occurred';
                $this->update_session_status($session_id, 'error', $error_message);
            }
        } catch (Exception $e) {
            $this->update_session_status($session_id, 'error', $e->getMessage());
        }
    }
    
    private function save_crawl_results($session_id, $results) {
        global $wpdb;
        
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        
        foreach ($results as $result) {
            $wpdb->insert(
                $table_results,
                array(
                    'session_id' => $session_id,
                    'url' => $result['url'] ?? '',
                    'title' => $result['title'] ?? '',
                    'title_length' => $result['title_length'] ?? 0,
                    'meta_description' => $result['meta_description'] ?? '',
                    'meta_description_length' => $result['meta_description_length'] ?? 0,
                    'h1_content' => $result['h1_content'] ?? '',
                    'h1_count' => $result['h1_count'] ?? 0,
                    'word_count' => $result['word_count'] ?? 0,
                    'flesch_reading_ease' => $result['flesch_reading_ease'] ?? 0,
                    'internal_links' => $result['internal_links'] ?? 0,
                    'external_links' => $result['external_links'] ?? 0,
                    'images' => $result['images'] ?? 0,
                    'images_without_alt' => $result['images_without_alt'] ?? 0,
                    'load_time_ms' => $result['load_time_ms'] ?? 0,
                    'status_code' => $result['status_code'] ?? 0,
                    'is_https' => $result['is_https'] ?? false,
                    'has_schema' => $result['has_schema'] ?? false,
                    'schema_count' => $result['schema_count'] ?? 0,
                    'has_og_tags' => $result['has_og_tags'] ?? false,
                    'has_twitter_cards' => $result['has_twitter_cards'] ?? false,
                    'social_tags_complete' => $result['social_tags_complete'] ?? false,
                    'has_viewport' => $result['has_viewport'] ?? false,
                    'has_favicon' => $result['has_favicon'] ?? false,
                    'canonical_url' => $result['canonical_url'] ?? '',
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        // Update session with total pages
        $this->update_session_pages($session_id, count($results));
    }
    
    private function update_session_status($session_id, $status, $error_message = null) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $update_data = array('status' => $status);
        
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }
        
        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }
        
        $wpdb->update(
            $table_sessions,
            $update_data,
            array('id' => $session_id)
        );
    }
    
    private function update_session_pages($session_id, $total_pages) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $wpdb->update(
            $table_sessions,
            array(
                'total_pages' => $total_pages,
                'completed_pages' => $total_pages
            ),
            array('id' => $session_id)
        );
    }
    
    public function get_crawl_status($session_id) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_sessions WHERE id = %d",
            $session_id
        ));
        
        if (!$session) {
            return array('error' => 'Session not found');
        }
        
        $progress = 0;
        if ($session->total_pages > 0) {
            $progress = round(($session->completed_pages / $session->total_pages) * 100);
        }
        
        return array(
            'status' => $session->status,
            'progress' => $progress,
            'total_pages' => intval($session->total_pages),
            'completed_pages' => intval($session->completed_pages),
            'error_message' => $session->error_message
        );
    }
    
    public function get_crawl_results($session_id) {
        $admin = new SEO_Crawler_Admin();
        
        $session_data = $this->get_crawl_status($session_id);
        
        if ($session_data['status'] !== 'completed') {
            return array(
                'success' => false,
                'error' => 'Crawl not completed yet'
            );
        }
        
        $results = $admin->get_detailed_results($session_id);
        $summary = $admin->get_session_summary($session_id);
        $recommendations = $admin->generate_seo_recommendations($session_id);
        
        return array(
            'success' => true,
            'results' => $results,
            'summary' => $summary,
            'recommendations' => $recommendations
        );
    }
    
    public function export_to_csv($session_id) {
        $admin = new SEO_Crawler_Admin();
        $results = $admin->get_detailed_results($session_id);
        
        if (empty($results)) {
            return '';
        }
        
        $output = fopen('php://output', 'w');
        
        // Header
        $header = array_keys($results[0]);
        fputcsv($output, $header);
        
        // Data rows
        foreach ($results as $row) {
            fputcsv($output, array_values($row));
        }
        
        $csv_content = ob_get_contents();
        ob_end_clean();
        fclose($output);
        
        return $csv_content;
    }
    
    public function validate_sitemap($sitemap_url) {
        $command = sprintf(
            '%s %s --sitemap-url %s --action validate',
            escapeshellarg($this->python_path),
            escapeshellarg($this->script_path),
            escapeshellarg($sitemap_url)
        );
        
        $output = shell_exec($command);
        $result = json_decode($output, true);
        
        return $result ?: array('success' => false, 'message' => 'Validation failed');
    }
}

// Register cron action for background processing
add_action('seo_crawler_process', function($session_id, $command) {
    $engine = new SEO_Crawler_Engine();
    $output = shell_exec($command);
    $engine->process_crawl_output($output, $session_id);
});