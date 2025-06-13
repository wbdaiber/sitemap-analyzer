<?php
/**
 * Plugin Name: SEO Crawler Pro
 * Plugin URI: https://yoursite.com/seo-crawler
 * Description: Professional SEO analysis tool with comprehensive crawling capabilities
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SEOCrawlerProPlugin {
    
    private $version = '1.0.0';
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_seo_crawl_start', array($this, 'ajax_start_crawl'));
        add_action('wp_ajax_seo_crawl_status', array($this, 'ajax_crawl_status'));
        add_action('wp_ajax_seo_get_history', array($this, 'ajax_get_history'));
        add_action('wp_ajax_seo_export_data', array($this, 'ajax_export_data'));
        
        // Shortcode support
        add_shortcode('seo_crawler', array($this, 'shortcode_display'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        add_option('seo_crawler_settings', array(
            'max_crawl_pages' => 1000,
            'default_delay' => 3.0,
            'enable_schema_detection' => true,
            'enable_social_media_check' => true
        ));
    }
    
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('seo_crawler_cleanup');
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
            settings text,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Crawl results table
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        $sql_results = "CREATE TABLE $table_results (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id mediumint(9) NOT NULL,
            url text NOT NULL,
            title text,
            meta_description text,
            h1_content text,
            word_count int DEFAULT 0,
            load_time_ms int DEFAULT 0,
            status_code varchar(10),
            has_schema boolean DEFAULT false,
            has_og_tags boolean DEFAULT false,
            has_twitter_cards boolean DEFAULT false,
            is_https boolean DEFAULT false,
            technical_issues text,
            content_issues text,
            crawled_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            FOREIGN KEY (session_id) REFERENCES $table_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_results);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'SEO Crawler',
            'SEO Crawler',
            'manage_options',
            'seo-crawler',
            array($this, 'admin_page'),
            'dashicons-search',
            30
        );
        
        add_submenu_page(
            'seo-crawler',
            'New Crawl',
            'New Crawl',
            'manage_options',
            'seo-crawler',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'seo-crawler',
            'Crawl History',
            'History',
            'manage_options',
            'seo-crawler-history',
            array($this, 'history_page')
        );
        
        add_submenu_page(
            'seo-crawler',
            'Settings',
            'Settings',
            'manage_options',
            'seo-crawler-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        include plugin_dir_path(__FILE__) . 'admin/crawler-admin.php';
    }
    
    public function history_page() {
        include plugin_dir_path(__FILE__) . 'admin/crawler-history.php';
    }
    
    public function settings_page() {
        include plugin_dir_path(__FILE__) . 'admin/crawler-settings.php';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'seo-crawler') === false) {
            return;
        }
        
        wp_enqueue_script(
            'seo-crawler-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_enqueue_style(
            'seo-crawler-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            $this->version
        );
        
        wp_localize_script('seo-crawler-admin', 'seoCrawlerAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_crawler_nonce'),
            'rest_url' => rest_url('seo-crawler/v1/')
        ));
    }
    
    public function shortcode_display($atts) {
        $atts = shortcode_atts(array(
            'user_access' => 'true',
            'height' => '600px',
            'theme' => 'light'
        ), $atts);
        
        wp_enqueue_script('seo-crawler-frontend');
        wp_enqueue_style('seo-crawler-frontend');
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'frontend/crawler-widget.php';
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        register_rest_route('seo-crawler/v1', '/crawl', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_start_crawl'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('seo-crawler/v1', '/results/(?P<session_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_results'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    public function ajax_start_crawl() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $sitemap_url = sanitize_url($_POST['sitemap_url']);
        $delay = floatval($_POST['delay']);
        $user_id = sanitize_text_field($_POST['user_id']);
        
        // Start background crawl process
        $session_id = $this->start_crawl_session($sitemap_url, $delay, $user_id);
        
        wp_die(json_encode(array(
            'success' => true,
            'session_id' => $session_id,
            'message' => 'Crawl started successfully'
        )));
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
        
        // Schedule background processing
        wp_schedule_single_event(time(), 'seo_crawler_process', array($session_id));
        
        return $session_id;
    }
}

new SEOCrawlerProPlugin();
?>