<?php
/**
 * Plugin Name: SEO Crawler Embed
 * Description: Embeds external SEO Crawler tool via iframe
 * Version: 1.0.0
 */

class SEOCrawlerEmbed {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('seo_crawler_embed', array($this, 'embed_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function init() {
        // Register settings
        add_option('seo_crawler_embed_url', 'https://your-domain.com/seo-crawler');
        add_option('seo_crawler_api_key', '');
    }
    
    public function embed_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '800px',
            'width' => '100%',
            'user_id' => '',
            'auto_theme' => 'true'
        ), $atts);
        
        $embed_url = get_option('seo_crawler_embed_url');
        $api_key = get_option('seo_crawler_api_key');
        
        // Generate user token for secure embedding
        $user_token = $this->generate_user_token($atts['user_id']);
        
        // Build iframe URL with parameters
        $iframe_params = array(
            'embed' => '1',
            'token' => $user_token,
            'theme' => $atts['auto_theme'] === 'true' ? $this->get_wp_theme() : 'light'
        );
        
        $iframe_url = add_query_arg($iframe_params, $embed_url);
        
        return sprintf(
            '<iframe src="%s" width="%s" height="%s" frameborder="0" class="seo-crawler-embed"></iframe>',
            esc_url($iframe_url),
            esc_attr($atts['width']),
            esc_attr($atts['height'])
        );
    }
    
    private function generate_user_token($user_id) {
        if (empty($user_id)) {
            $user_id = get_current_user_id() ?: 'anonymous_' . uniqid();
        }
        
        return wp_create_nonce('seo_crawler_' . $user_id);
    }
    
    private function get_wp_theme() {
        // Detect if WordPress is using dark theme
        $current_theme = wp_get_theme();
        $theme_name = strtolower($current_theme->get('Name'));
        
        if (strpos($theme_name, 'dark') !== false) {
            return 'dark';
        }
        
        return 'light';
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'seo-crawler-embed',
            plugin_dir_url(__FILE__) . 'assets/embed.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'seo-crawler-embed',
            plugin_dir_url(__FILE__) . 'assets/embed.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }
}

new SEOCrawlerEmbed();
?>