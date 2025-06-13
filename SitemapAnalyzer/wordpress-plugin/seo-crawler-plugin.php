<?php
/**
 * Plugin Name: SEO Crawler Plugin
 * Description: Complete SEO crawler that replicates Streamlit interface functionality
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SEO_CRAWLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEO_CRAWLER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SEO_CRAWLER_PLUGIN_VERSION', '1.0.0');

// Main plugin class
class SEO_Crawler_Plugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('wp_ajax_seo_crawler_start', array($this, 'ajax_start_crawl'));
        add_action('wp_ajax_seo_crawler_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_seo_crawler_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_seo_crawler_export', array($this, 'ajax_export_data'));
        add_action('wp_ajax_seo_crawler_validate', array($this, 'ajax_validate_sitemap'));
        add_action('wp_ajax_seo_crawler_history', array($this, 'ajax_get_history'));
        add_action('wp_ajax_seo_delete_session', array($this, 'ajax_delete_session'));
        add_action('wp_ajax_seo_stop_crawl', array($this, 'ajax_stop_crawl'));
        add_action('wp_ajax_nopriv_seo_crawler_start', array($this, 'ajax_start_crawl'));
        add_action('wp_ajax_nopriv_seo_crawler_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_nopriv_seo_crawler_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_nopriv_seo_crawler_export', array($this, 'ajax_export_data'));
        add_action('wp_ajax_nopriv_seo_crawler_validate', array($this, 'ajax_validate_sitemap'));
        add_action('wp_ajax_nopriv_seo_crawler_history', array($this, 'ajax_get_history'));
        
        // Register shortcode
        add_shortcode('seo_crawler', array($this, 'shortcode_display'));
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Include required files
        require_once SEO_CRAWLER_PLUGIN_PATH . 'includes/class-seo-crawler-admin.php';
        require_once SEO_CRAWLER_PLUGIN_PATH . 'includes/class-seo-crawler-engine.php';
        require_once SEO_CRAWLER_PLUGIN_PATH . 'includes/class-seo-crawler-frontend.php';
        
        // Initialize classes
        new SEO_Crawler_Admin();
        new SEO_Crawler_Engine();
        new SEO_Crawler_Frontend();
    }
    
    public function admin_menu() {
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
            'Crawl History',
            'History',
            'manage_options',
            'seo-crawler-history',
            array($this, 'history_page')
        );
    }
    
    public function admin_page() {
        include SEO_CRAWLER_PLUGIN_PATH . 'templates/streamlit-interface.php';
    }
    
    public function history_page() {
        include SEO_CRAWLER_PLUGIN_PATH . 'templates/history-page.php';
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'seo-crawler') !== false) {
            wp_enqueue_style('seo-crawler-admin', SEO_CRAWLER_PLUGIN_URL . 'assets/css/streamlit-style.css', array(), SEO_CRAWLER_PLUGIN_VERSION);
            wp_enqueue_style('seo-crawler-admin-custom', SEO_CRAWLER_PLUGIN_URL . 'assets/css/admin.css', array(), SEO_CRAWLER_PLUGIN_VERSION);
            wp_enqueue_script('seo-crawler-admin', SEO_CRAWLER_PLUGIN_URL . 'assets/js/streamlit-ui.js', array('jquery'), SEO_CRAWLER_PLUGIN_VERSION, true);
            wp_enqueue_script('seo-crawler-admin-custom', SEO_CRAWLER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SEO_CRAWLER_PLUGIN_VERSION, true);
            
            wp_localize_script('seo-crawler-admin', 'seoCrawlerAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_crawler_nonce')
            ));
        }
    }
    
    public function frontend_scripts() {
        wp_enqueue_style('seo-crawler-frontend', SEO_CRAWLER_PLUGIN_URL . 'assets/css/streamlit-style.css', array(), SEO_CRAWLER_PLUGIN_VERSION);
        wp_enqueue_script('seo-crawler-frontend', SEO_CRAWLER_PLUGIN_URL . 'assets/js/streamlit-ui.js', array('jquery'), SEO_CRAWLER_PLUGIN_VERSION, true);
        
        wp_localize_script('seo-crawler-frontend', 'seoCrawlerAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_crawler_nonce')
        ));
    }
    
    public function shortcode_display($atts) {
        $atts = shortcode_atts(array(
            'height' => 'auto'
        ), $atts);
        
        ob_start();
        include SEO_CRAWLER_PLUGIN_PATH . 'templates/crawler-form.php';
        return ob_get_clean();
    }
    
    public function ajax_start_crawl() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $sitemap_url = sanitize_url($_POST['sitemap_url']);
        $delay = floatval($_POST['delay']);
        $max_pages = intval($_POST['max_pages']);
        $user_id = sanitize_text_field($_POST['user_id']);
        
        if (empty($sitemap_url)) {
            wp_die(json_encode(array('success' => false, 'error' => 'Sitemap URL is required')));
        }
        
        $engine = new SEO_Crawler_Engine();
        $session_id = $engine->start_crawl($sitemap_url, $delay, $max_pages, $user_id);
        
        if ($session_id) {
            wp_die(json_encode(array('success' => true, 'session_id' => $session_id)));
        } else {
            wp_die(json_encode(array('success' => false, 'error' => 'Failed to start crawl')));
        }
    }
    
    public function ajax_get_status() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $engine = new SEO_Crawler_Engine();
        $status = $engine->get_crawl_status($session_id);
        
        wp_die(json_encode($status));
    }
    
    public function ajax_get_results() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $engine = new SEO_Crawler_Engine();
        $results = $engine->get_crawl_results($session_id);
        
        wp_die(json_encode($results));
    }
    
    public function ajax_export_data() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $engine = new SEO_Crawler_Engine();
        $csv_data = $engine->export_to_csv($session_id);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="seo-crawl-results.csv"');
        echo $csv_data;
        wp_die();
    }
    
    public function ajax_validate_sitemap() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $sitemap_url = sanitize_url($_POST['sitemap_url']);
        
        if (empty($sitemap_url)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sitemap URL is required')));
        }
        
        $engine = new SEO_Crawler_Engine();
        $result = $engine->validate_sitemap($sitemap_url);
        
        wp_die(json_encode($result));
    }
    
    public function ajax_get_history() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $admin = new SEO_Crawler_Admin();
        $history = $admin->get_user_sessions($user_id);
        
        wp_die(json_encode(array('success' => true, 'history' => $history)));
    }
    
    public function ajax_delete_session() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $admin = new SEO_Crawler_Admin();
        $result = $admin->delete_session($session_id);
        
        if ($result) {
            wp_die(json_encode(array('success' => true)));
        } else {
            wp_die(json_encode(array('success' => false, 'error' => 'Failed to delete session')));
        }
    }
    
    public function ajax_stop_crawl() {
        check_ajax_referer('seo_crawler_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'seo_crawler_sessions',
            array('status' => 'stopped'),
            array('id' => $session_id)
        );
        
        if ($result !== false) {
            wp_die(json_encode(array('success' => true)));
        } else {
            wp_die(json_encode(array('success' => false, 'error' => 'Failed to stop crawl')));
        }
    }
    
    public function activate() {
        $this->create_tables();
    }
    
    public function deactivate() {
        // Cleanup if needed
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id varchar(255) NOT NULL,
            sitemap_url text NOT NULL,
            settings text,
            status varchar(20) DEFAULT 'pending',
            total_pages int DEFAULT 0,
            completed_pages int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            error_message text,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Results table
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
            flesch_reading_ease float DEFAULT 0,
            internal_links int DEFAULT 0,
            external_links int DEFAULT 0,
            images int DEFAULT 0,
            images_without_alt int DEFAULT 0,
            load_time_ms float DEFAULT 0,
            status_code int DEFAULT 0,
            is_https tinyint(1) DEFAULT 0,
            has_schema tinyint(1) DEFAULT 0,
            schema_count int DEFAULT 0,
            has_og_tags tinyint(1) DEFAULT 0,
            has_twitter_cards tinyint(1) DEFAULT 0,
            social_tags_complete tinyint(1) DEFAULT 0,
            has_viewport tinyint(1) DEFAULT 0,
            has_favicon tinyint(1) DEFAULT 0,
            canonical_url text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_results);
    }
}

// Initialize the plugin
new SEO_Crawler_Plugin();