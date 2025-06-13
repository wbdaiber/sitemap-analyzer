<?php
/**
 * Plugin Name: SEO Crawler Pro
 * Plugin URI: https://yoursite.com/seo-crawler-pro
 * Description: Professional SEO analysis tool with comprehensive crawling capabilities for WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: seo-crawler-pro
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SEO_CRAWLER_VERSION', '1.0.0');
define('SEO_CRAWLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEO_CRAWLER_PLUGIN_PATH', plugin_dir_path(__FILE__));

class SEOCrawlerPro {
    
    private $version = SEO_CRAWLER_VERSION;
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('SEOCrawlerPro', 'uninstall'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('seo-crawler-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_seo_crawl_start', array($this, 'ajax_start_crawl'));
        add_action('wp_ajax_seo_crawl_status', array($this, 'ajax_crawl_status'));
        add_action('wp_ajax_seo_get_history', array($this, 'ajax_get_history'));
        add_action('wp_ajax_seo_export_data', array($this, 'ajax_export_data'));
        
        // Public AJAX (for frontend widget)
        add_action('wp_ajax_nopriv_seo_crawl_start', array($this, 'ajax_start_crawl'));
        add_action('wp_ajax_nopriv_seo_get_history', array($this, 'ajax_get_history'));
        
        // Shortcode support
        add_shortcode('seo_crawler', array($this, 'shortcode_display'));
        
        // Background processing
        add_action('seo_crawler_process_batch', array($this, 'process_crawl_batch'));
    }
    
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        add_option('seo_crawler_settings', array(
            'max_crawl_pages' => 1000,
            'default_delay' => 3.0,
            'enable_schema_detection' => true,
            'enable_social_media_check' => true,
            'cleanup_days' => 30,
            'allow_frontend_access' => true
        ));
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('seo_crawler_cleanup');
        wp_clear_scheduled_hook('seo_crawler_process_batch');
    }
    
    public static function uninstall() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_crawler_results");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_crawler_sessions");
        delete_option('seo_crawler_settings');
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Crawl sessions table
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id varchar(50) NOT NULL,
            sitemap_url text NOT NULL,
            status varchar(20) DEFAULT 'pending',
            total_pages int DEFAULT 0,
            completed_pages int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            settings longtext,
            error_message text NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Crawl results table
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        $sql_results = "CREATE TABLE $table_results (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id mediumint(9) NOT NULL,
            url text NOT NULL,
            title text,
            title_length int DEFAULT 0,
            meta_description text,
            meta_description_length int DEFAULT 0,
            h1_content text,
            h1_count int DEFAULT 0,
            word_count int DEFAULT 0,
            flesch_reading_ease decimal(5,2) DEFAULT 0,
            internal_links int DEFAULT 0,
            external_links int DEFAULT 0,
            images int DEFAULT 0,
            images_without_alt int DEFAULT 0,
            load_time_ms int DEFAULT 0,
            status_code varchar(10),
            is_https boolean DEFAULT false,
            has_schema boolean DEFAULT false,
            schema_count int DEFAULT 0,
            has_og_tags boolean DEFAULT false,
            has_twitter_cards boolean DEFAULT false,
            social_tags_complete boolean DEFAULT false,
            has_viewport boolean DEFAULT false,
            has_favicon boolean DEFAULT false,
            canonical_url text,
            crawled_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_results);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('SEO Crawler', 'seo-crawler-pro'),
            __('SEO Crawler', 'seo-crawler-pro'),
            'manage_options',
            'seo-crawler-pro',
            array($this, 'admin_page'),
            'dashicons-search',
            30
        );
        
        add_submenu_page(
            'seo-crawler-pro',
            __('Crawl History', 'seo-crawler-pro'),
            __('History', 'seo-crawler-pro'),
            'manage_options',
            'seo-crawler-history',
            array($this, 'history_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'seo-crawler') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('seo-crawler-admin', SEO_CRAWLER_PLUGIN_URL . 'assets/admin.css', array(), $this->version);
        wp_enqueue_script('seo-crawler-admin', SEO_CRAWLER_PLUGIN_URL . 'assets/admin.js', array('jquery'), $this->version, true);
        
        wp_localize_script('seo-crawler-admin', 'seoCrawlerAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_crawler_nonce'),
            'plugin_url' => SEO_CRAWLER_PLUGIN_URL
        ));
    }
    
    public function enqueue_frontend_scripts() {
        global $post;
        if (!is_admin() && $post && has_shortcode($post->post_content, 'seo_crawler')) {
            wp_enqueue_script('jquery');
            wp_enqueue_style('seo-crawler-frontend', SEO_CRAWLER_PLUGIN_URL . 'assets/frontend.css', array(), $this->version);
            wp_enqueue_script('seo-crawler-frontend', SEO_CRAWLER_PLUGIN_URL . 'assets/frontend.js', array('jquery'), $this->version, true);
            
            wp_localize_script('seo-crawler-frontend', 'seoCrawlerAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_crawler_nonce')
            ));
        }
    }
    
    public function admin_page() {
        include plugin_dir_path(__FILE__) . 'admin-crawler-admin.php';
    }
    
    public function history_page() {
        include plugin_dir_path(__FILE__) . 'admin-crawler-history.php';
    }
    
    public function shortcode_display($atts) {
        $atts = shortcode_atts(array(
            'height' => '800px'
        ), $atts);
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'frontend-widget.php';
        return ob_get_clean();
    }
    
    public function ajax_start_crawl() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $sitemap_url = sanitize_url($_POST['sitemap_url']);
        $delay = floatval($_POST['delay']);
        $user_id = sanitize_text_field($_POST['user_id']);
        
        if (empty($sitemap_url)) {
            wp_die(json_encode(array('error' => 'Sitemap URL is required')));
        }
        
        $session_id = $this->start_crawl_session($sitemap_url, $delay, $user_id);
        
        wp_die(json_encode(array(
            'success' => true,
            'session_id' => $session_id,
            'message' => 'Crawl started successfully'
        )));
    }
    
    public function ajax_crawl_status() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $status = $this->get_crawl_status($session_id);
        
        wp_die(json_encode($status));
    }
    
    public function ajax_get_history() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $history = $this->get_crawl_history($user_id);
        
        wp_die(json_encode($history));
    }
    
    public function ajax_export_data() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $csv_data = $this->export_crawl_data($session_id);
        
        wp_die($csv_data);
    }
    
    private function start_crawl_session($sitemap_url, $delay, $user_id) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $wpdb->insert(
            $table_sessions,
            array(
                'user_id' => $user_id,
                'sitemap_url' => $sitemap_url,
                'status' => 'running',
                'settings' => json_encode(array('delay' => $delay))
            )
        );
        
        $session_id = $wpdb->insert_id;
        
        // Start background processing
        wp_schedule_single_event(time(), 'seo_crawler_process_batch', array($session_id));
        
        return $session_id;
    }
    
    private function get_crawl_status($session_id) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}seo_crawler_sessions WHERE id = %d",
            $session_id
        ));
        
        if (!$session) {
            return array('error' => 'Session not found');
        }
        
        return array(
            'status' => $session->status,
            'total_pages' => $session->total_pages,
            'completed_pages' => $session->completed_pages,
            'progress' => $session->total_pages > 0 ? ($session->completed_pages / $session->total_pages) * 100 : 0
        );
    }
    
    private function get_crawl_history($user_id) {
        global $wpdb;
        
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}seo_crawler_sessions 
             WHERE user_id = %s 
             ORDER BY created_at DESC 
             LIMIT 10",
            $user_id
        ));
        
        return $sessions;
    }
    
    private function export_crawl_data($session_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}seo_crawler_results WHERE session_id = %d",
            $session_id
        ));
        
        if (empty($results)) {
            return '';
        }
        
        $csv_headers = array(
            'URL', 'Title', 'Title Length', 'Meta Description', 'Meta Desc Length',
            'H1 Count', 'Word Count', 'Readability Score', 'Internal Links', 
            'External Links', 'Images', 'Images w/o Alt', 'Load Time (ms)',
            'Status Code', 'HTTPS', 'Has Schema', 'Schema Count', 'Has Open Graph',
            'Has Twitter Cards', 'Social Tags Complete', 'Has Viewport', 
            'Has Favicon', 'Canonical URL'
        );
        
        $csv_content = implode(',', $csv_headers) . "\n";
        
        foreach ($results as $row) {
            $csv_row = array(
                '"' . str_replace('"', '""', $row->url) . '"',
                '"' . str_replace('"', '""', $row->title) . '"',
                $row->title_length,
                '"' . str_replace('"', '""', $row->meta_description) . '"',
                $row->meta_description_length,
                $row->h1_count,
                $row->word_count,
                $row->flesch_reading_ease,
                $row->internal_links,
                $row->external_links,
                $row->images,
                $row->images_without_alt,
                $row->load_time_ms,
                $row->status_code,
                $row->is_https ? 'Yes' : 'No',
                $row->has_schema ? 'Yes' : 'No',
                $row->schema_count,
                $row->has_og_tags ? 'Yes' : 'No',
                $row->has_twitter_cards ? 'Yes' : 'No',
                $row->social_tags_complete ? 'Yes' : 'No',
                $row->has_viewport ? 'Yes' : 'No',
                $row->has_favicon ? 'Yes' : 'No',
                '"' . str_replace('"', '""', $row->canonical_url) . '"'
            );
            
            $csv_content .= implode(',', $csv_row) . "\n";
        }
        
        return $csv_content;
    }
    
    public function process_crawl_batch($session_id) {
        // Include the crawler engine
        include plugin_dir_path(__FILE__) . 'crawler-engine.php';
        seo_crawler_process_session($session_id);
    }
}

// Initialize the plugin
new SEOCrawlerPro();
?>