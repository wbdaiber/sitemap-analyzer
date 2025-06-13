<?php
/**
 * Plugin Name: SEO Crawler
 * Description: Comprehensive SEO analysis tool for WordPress
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SEOCrawlerPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_seo_crawl', array($this, 'handle_crawl_request'));
        add_action('wp_ajax_nopriv_seo_crawl', array($this, 'handle_crawl_request'));
        add_action('wp_ajax_get_crawl_history', array($this, 'get_crawl_history'));
        add_action('wp_ajax_nopriv_get_crawl_history', array($this, 'get_crawl_history'));
    }
    
    public function init() {
        // Register shortcode for ReactPress
        add_shortcode('seo_crawler', array($this, 'render_crawler_shortcode'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'seo_crawler_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_crawler_nonce')
        ));
    }
    
    public function render_crawler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '800px'
        ), $atts);
        
        return '<div id="seo-crawler-app" style="height: ' . esc_attr($atts['height']) . ';"></div>';
    }
    
    public function handle_crawl_request() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $sitemap_url = sanitize_url($_POST['sitemap_url']);
        $delay = floatval($_POST['delay']);
        $user_id = sanitize_text_field($_POST['user_id']);
        
        if (empty($sitemap_url)) {
            wp_die(json_encode(array('error' => 'Sitemap URL is required')));
        }
        
        // Run the crawl (you'll need to adapt the Python crawler logic to PHP)
        $results = $this->run_seo_crawl($sitemap_url, $delay);
        
        // Save results for this user
        $this->save_crawl_results($user_id, $results);
        
        wp_die(json_encode($results));
    }
    
    public function get_crawl_history() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $history = $this->load_crawl_history($user_id);
        
        wp_die(json_encode($history));
    }
    
    private function run_seo_crawl($sitemap_url, $delay) {
        // This is where you'd implement the crawling logic
        // You can either:
        // 1. Convert the Python code to PHP
        // 2. Use exec() to call a Python script
        // 3. Use a REST API to communicate with the Python backend
        
        // Example using WordPress HTTP API
        $response = wp_remote_get($sitemap_url);
        
        if (is_wp_error($response)) {
            return array('error' => 'Failed to fetch sitemap');
        }
        
        $sitemap_content = wp_remote_retrieve_body($response);
        $urls = $this->parse_sitemap($sitemap_content);
        
        $results = array();
        foreach ($urls as $url) {
            $page_data = $this->analyze_page($url);
            $results[] = $page_data;
            
            // Respect delay
            if ($delay > 0) {
                sleep($delay);
            }
        }
        
        return $results;
    }
    
    private function parse_sitemap($content) {
        $urls = array();
        $xml = simplexml_load_string($content);
        
        if ($xml) {
            foreach ($xml->url as $url_element) {
                $urls[] = (string)$url_element->loc;
            }
        }
        
        return $urls;
    }
    
    private function analyze_page($url) {
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return array('url' => $url, 'error' => 'Failed to fetch page');
        }
        
        $html = wp_remote_retrieve_body($response);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        // Extract SEO data
        $title = '';
        $title_elements = $dom->getElementsByTagName('title');
        if ($title_elements->length > 0) {
            $title = $title_elements->item(0)->textContent;
        }
        
        $meta_description = '';
        $meta_elements = $dom->getElementsByTagName('meta');
        foreach ($meta_elements as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                $meta_description = $meta->getAttribute('content');
                break;
            }
        }
        
        // Check for schema markup
        $has_schema = false;
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $has_schema = true;
                break;
            }
        }
        
        // Check for Open Graph tags
        $has_og_tags = false;
        foreach ($meta_elements as $meta) {
            if (strpos($meta->getAttribute('property'), 'og:') === 0) {
                $has_og_tags = true;
                break;
            }
        }
        
        return array(
            'url' => $url,
            'title' => $title,
            'title_length' => strlen($title),
            'meta_description' => $meta_description,
            'meta_description_length' => strlen($meta_description),
            'has_schema' => $has_schema,
            'has_og_tags' => $has_og_tags,
            'crawl_timestamp' => current_time('mysql')
        );
    }
    
    private function save_crawl_results($user_id, $results) {
        $history = $this->load_crawl_history($user_id);
        
        $new_entry = array(
            'timestamp' => current_time('mysql'),
            'pages_crawled' => count($results),
            'results' => $results
        );
        
        $history[] = $new_entry;
        
        // Keep only last 10 crawls
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        update_option('seo_crawler_history_' . $user_id, $history);
    }
    
    private function load_crawl_history($user_id) {
        return get_option('seo_crawler_history_' . $user_id, array());
    }
}

new SEOCrawlerPlugin();
?>